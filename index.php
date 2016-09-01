<?php
##
# © 2016 Partners HealthCare System, Inc. All Rights Reserved. 
##
/**
	
	This plugin is designed to help create automatic email notifications when sensitive
	responses are received from a survey.  
  
  Originally based on Autonotify plugin by ANdy Martin.
  As of June 1 2016, this plugin was enhanced to support multiple notification 
  emails in addition to multiple triggers.  The new version offers the ability 
  to upgrade existing autonitfy configurations on first use.
	
	It must be used in conjunction with a data entry trigger to function in real-time.
	The settings for each project are stored as an encoded variable (an) in the query 
  string of the DET.

**/

error_reporting(E_ALL);

// File path and prefix for log file - make sure web user has write permissions
// If you're on windows, be sure to use forward slashes as in "d:/redcap/temp/autonotify"
// If you're not sure about what path to use, goto your configuration settings and just append off your redcap-temp folder.
$log_prefix = "/var/www/html/redcap/autonotify_log/autonotify";

$action = '';	// Script action

##### RUNNING AS DET - PART 1 #####
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_GET['an'])) {
	$action = 'det';
	define('NOAUTH',true);	// Turn off Authentication - running on server
	$_GET['pid'] = $_POST['project_id'];	// Set the pid from POST so context is Project rather than Global
}

// Include required files
require_once "../../redcap_connect.php";
require_once "common.php";

// Display version and exit, if requested
if (isset($_GET['version'])) exit('Email Notification via DET (autonotify) version: ' . AutoNotify::PLUGIN_VERSION);

$an = new AutoNotify();

##### RUNNING AS DET - PART 2 #####
if ($action == 'det') {
	// Execute AutoNotify script if called from DET trigger
	$an->loadFromDet();
	$an->execute();
	exit;
}

##### VALIDATION #####
# Make sure user has permissions for project or is a super user
$these_rights = REDCap::getUserRights(USERID);
$my_rights = $these_rights[USERID];
if (!$my_rights['design'] && !SUPER_USER) {
	showError('Project Setup rights are required to add/modify automatic notifications');
	exit;
}
# Make sure the user's rights have not expired for the project
if ($my_rights['expiration'] != "" && $my_rights['expiration'] < TODAY) {
	showError('Your user account has expired for this project.  Please contact the project admin.');
	exit;
}

##### TEST POST #####
# Check to see if we are running a test if the logic (called from AJAX - be sure to include PID in ajax call!)
# logic = condition to be tested
# record = record to test with
# ** TO BE IMPROVED **
if (isset($_POST['test']) && $_POST['test']) {
	$logic = htmlspecialchars_decode($_POST['logic'], ENT_QUOTES);
	$record = $_POST['record'];
	$event_id = $_POST['event_id'];
	$an->record = $record;
	$an->redcap_event_name = REDCap::getEventNames(TRUE,FALSE,$event_id);	
	echo $an->testLogic($logic);
	exit;
}

#### ADD TRIGGER ####
# Called by Ajax
if (isset($_POST['addTrigger'])) {
	$index = $_POST['addTrigger'] + 1;
	echo AutoNotify::renderTrigger($index);
	exit;
}

#### ADD NOTIFICATION ####
# Called by Ajax
if (isset($_POST['addNotificationEmail'])) {
	$index = $_POST['addNotificationEmail'] + 1;
	echo AutoNotify::renderNotificationEmail($index);
	exit;
}


##### BEGIN NORMAL PAGE RENDERING #####
# Display the project header
require_once APP_PATH_DOCROOT . 'ProjectGeneral/header.php';

# Inject the plugin tabs (must come after including tabs.php)
include APP_PATH_DOCROOT . "ProjectSetup/tabs.php";
injectPluginTabs($pid, $_SERVER["REQUEST_URI"], 'AutoNotify');

# Check to see if we are saving a previously posted trigger
if (isset($_POST['save']) && $_POST['save']) {

  // Save Trigger
	$params = $_POST;
	unset($params['save']);
	$params['last_saved'] = date('y-m-d h:i:s');
	$params['modified_by'] = USERID;
	$encoded = $an->encode($params);
	$an->updateDetUrl($encoded);
	renderTemporaryMessage('The automatic notification has been updated', 'Automatic Notification Saved');
}

# Load an existing template from the DET if present in the query string
if (!empty($data_entry_trigger_url)) {
	$query = parse_url($data_entry_trigger_url, PHP_URL_QUERY);
	if ($query) parse_str($query,$params);
	$config_encoded = isset($params['an']) ? $params['an'] : '';
	
	if ($config_encoded) {
		$an->loadEncodedConfig($config_encoded);
	} else {
		$html = RCView::div(array('id'=>$id,'class'=>'red','style'=>'margin-top:20px;padding:10px 10px 15px;'),
				RCView::div(array('style'=>'text-align:center;font-size:20px;font-weight:bold;padding-bottom:5px;'), "Warning: Existing DET Defined").
				RCView::div(array(), "A data entry trigger was already defined for this project: <b>$data_entry_trigger_url</b><br />If you save this AutoNotification configuration you will replace this DET.  Your old DET has been moved to the pre-notification area and will be executed before this script unless otherwise changed.")
		);
		echo $html;
		$an->config['pre_script_det_url'] = $data_entry_trigger_url;
	}
}


######## HTML PAGE ###########
?>
<style type='text/css'>
	td.td1 {vertical-align:text-top;}
	td.td2 {vertical-align:text-top; padding-top: 5px; padding-right:15px; width:70px;}
	td.td2 label {font-variant:small-caps; font-size: 12px;}
	div.desc {font-variant:normal; font-style:italic; font-size: smaller; padding-top:5px; width:70px;}
	table.tbi input {width: 500px; display: inline; height:20px}
	table.tbi input[type='radio'] {width: 14px; display: normal;}
	table.tbi textarea {width: 500px; display:inline; height:50px;}
</style>

<?php

$instructions = "The Auto-Notification plugin is tested each time a record is saved in your project.  When the condition is 'true' a message will be sent as configured.  A message is written to the log recording the record, event, and name of the trigger.  A given record-event-trigger will only be fired once.";

$notification_instructions = RCView::p(array(), 'Notification emails include a link to the record and will therefore include the value of the <b>'.REDCap::getRecordIdField().'</b> field.  For this reason, the '.REDCap::getRecordIdField().' field <b>SHOULD NOT INCLUDE PHI</b>.  It is recommended to use an auto-numbering first field as best practice and include PHI as a secondary identifier.');

$section = '' .
    
$an->renderNotifications().
  RCView::div(array('style' => 'margin-bottom: 30px;'),
  RCView::button(array('class'=>'jqbuttonmed ui-button ui-widget ui-state-default ui-corner-all ui-button-text-only','onclick'=>'javascript:addNotificationEmail();'), RCView::img(array('src'=>'add.png', 'class'=>'imgfix')).' Add another notification email').
  RCView::button(array('class'=>'jqbuttonmed ui-button ui-widget ui-state-default ui-corner-all ui-button-text-only','onclick'=>'javascript:save();'), 'Save')    
).
    
$an->renderTriggers().
  RCView::div(array('style' => 'margin-bottom: 30px;'),
  RCView::button(array('class'=>'jqbuttonmed ui-button ui-widget ui-state-default ui-corner-all ui-button-text-only','onclick'=>'javascript:addTrigger();'), RCView::img(array('src'=>'add.png', 'class'=>'imgfix')).' Add another trigger')
).

  RCView::div(array('class'=>'round chklist','id'=>'det_config'),
	// Pre and Post AutoNotification -DAG URL to be executed
	RCView::div(array('class'=>'chklisthdr','style'=>'color:rgb(128,0,0);margin-top:10px;'), "Pre- and Post- AutoNotification DET Triggers").
	RCView::p(array(), 'You can run another Data Entry Trigger before or after this auto-notification test by inserting the complete DET urls below.<br /><i>Please note that these DET urls will be called each time this DET is called, whether the conditional logic evaluates to true or not.</i>').
	RCView::table(array('cellspacing'=>'5', 'class'=>'tbi'),
		AutoNotify::renderRow('pre_script_det_url','Pre-notification DET Url',$an->config['pre_script_det_url']).
		AutoNotify::renderRow('post_script_det_url','Post-notification DET Url',$an->config['post_script_det_url'])
	)
	
);

$page = RCView::div(array('class'=>'autonotify_config'),
	RCView::h3(array(),'AutoNotify: a DET-based Notification Plugin').
  $notification_instructions.
	$section.
	RCView::div(array(),
		RCView::button(array('class'=>'jqbuttonmed ui-button ui-widget ui-state-default ui-corner-all ui-button-text-only','onclick'=>'javascript:save();'), RCView::img(array('src'=>'bullet_disk.png', 'class'=>'imgfix')).'<b>Save Configuration</b>').
		" ".
		RCView::button(array('class'=>'jqbuttonmed ui-button ui-widget ui-state-default ui-corner-all ui-button-text-only','onclick'=>'javascript:refresh();'), 'Refresh')
	)
);

print $page;
print AutoNotify::renderHelpDivs();

?>


<script type="text/javascript">

	// Take all input elements inside our autonotify_config div and post them to this page
	function save() {
    
    // Perform basic validation
    if (! validConfiguration()) return;
    
		var params = new Object;	// Info to save
    
		// Loop through each trigger
		var triggers = new Object;
		i=0;
		$('div.trigger', '#triggers_config').each (function (index1, value1) {
			i++;
			triggers[i] = new Object;
			$('*:input', $(this)).each (function (index2, value2) {
				// Skip buttons or other attributes that don't have an ID
				if ($(this).attr('id')) {
					// Replace any -x suffix in the stored array (e.g. logic-1 becomes logic)
					triggers[i][$(this).attr('id').replace(/\-.*/,'')] = $(this).val();
				}
			});
			triggers[i]['enabled'] = $('input[name^=enabled]:checked', $(this)).val();
		});
		params['triggers'] = JSON.stringify(triggers);
    
    // Loop through each notification email
		var notification_emails = new Object;
		i=0;
		$('div.notification_email', '#notifications_config').each (function (index1, value1) {
			i++;
			notification_emails[i] = new Object;
			$('*:input', $(this)).each (function (index2, value2) {
				// Skip buttons or other attributes that don't have an ID
				if ($(this).attr('id')) {
					// Replace any -x suffix in the stored array (e.g. logic-1 becomes logic)
					notification_emails[i][$(this).attr('id').replace(/\-.*/,'')] = $(this).val();
				}
			});
		});
		params['notification_emails'] = JSON.stringify(notification_emails);
    
		
		// Get the DET settings
		$('*:input', '#det_config').each (function (index, value) {
			if ($(this).attr('id')) params[$(this).attr('id')] = $(this).val();
		});
		params['save'] = 1;
    post('', params);

	}

  // This function does some very basic validation:
  // Ensure all Notification Email and Trigger fields have SOMETHING in them
  // Ensure Notification Email labels are not dupicated
  // Alerts user with a "message" of issues
  // Returns true if configuration valid, otherwise false
  function validConfiguration(){
    
    var valid_config = true;
    var notification_emails_with_issues = [];  // Array of notification emails
    var notification_email_label = []; // Array of notification email labels
    
    // Check for notification email issues
    $('div.notification_email', '#notifications_config').each (function (index1, value1) {
      
      var missing = []; // Array of field names that have empty fields
      var parts;
      
      // Walk through each input field
			$('*:input', $(this)).each (function (index2, value2) {
				if ($(this).attr('id')) {
					
          // Check for empty fields; they are of the form fieldname-notificationIndex
          if (! $(this).val()) {
            parts = $(this).attr('id').split("-");
            missing.push(parts[0]); 
          }
          
          // Record the value of the notification email label field to check for duplicates
          if ($(this).attr('id') === 'label-'+(index1+1)){ 
            notification_email_label.push($(this).val());
          }
				}
			});
      
      // Build the notification email object, noting the empty fields
      if (missing.length){
        notification_emails_with_issues.push({
          name: 'notification_email',
          key: index1+1,
          missing: missing
        });
      }
      
		});
    
    // Any duplicate notification email labels?
    duplicates = checkForDuplicates(notification_email_label);
    if (duplicates && (duplicates !='')) {
      valid_config = false;
      msg = 'Notification Email labels must be unique. The following duplicates were found:';
      var i;
      for (i=0; i<duplicates.length; i++) { 
        msg += '\n' + duplicates[i]; 
      }
      alert (msg);
      
      // Fix duplicates before going any further
      return valid_config;
    }
    
    if (notification_emails_with_issues.length) {
      valid_config = false;
      
      // Build message informing user of issues
      msg = 'All Notification Email fields are required. Missing entries were detected:\n';
      var n;
      for (n=0; n<notification_emails_with_issues.length; n++){
        msg += '\nFor Notification Email ' + notification_emails_with_issues[n].key;
        
        var f;
        for (f=0; f<notification_emails_with_issues[n].missing.length; f++){
          msg += '\nField: ' + notification_emails_with_issues[n].missing[f];
        }
      }
      alert(msg);
    }
    
    return valid_config;
    
  }
  
  
  // Returns an array of elements for which duplicate exist in the passed-in array
  function checkForDuplicates(arr){
    var cache = {};
    var results = [];
    
    for (var i=0, len=arr.length; i<len; i++) {
      if(cache[arr[i]] === true){
        results.push(arr[i]);
      }else{
        cache[arr[i]] = true;
      }  
    }
    
   return results;
  }


	function refresh() {
		window.location = window.location.href;
	}


  function addNotificationEmail() {
		max = 0;
		$('div.notification_email', '#notifications_config').each(
			function (index,value) {
				idx = parseInt($(this).attr('idx'));
				if (idx > max) {
					max = idx;
				};
			}
		);
		$.post('',{addNotificationEmail: max, project_id: pid },
			function(data) {
				$('#notifications_config').append(data);
				updatePage();
			}
		);
	}
  
  function removeNotificationEmail(id) {

    triggers = triggersUsingNotificationEmail(id);
    
		if ($('div.notification_email').length == 1) {
			alert ('You can not delete the last notification email.');
		} else if (triggers.length) { 
      msg = 'You must first remove this notification email from the following trigger(s):';
      var i;
      for (i=0; i<triggers.length; i++) {
        msg += '\n' + triggers[i];
      }
      alert (msg);
    } else {
			$('div.notification_email[idx='+id+']').remove();
      save();
		}
	}
  
	function addTrigger() {
		max = 0;
		$('div.trigger', '#triggers_config').each(
			function (index,value) {
				idx = parseInt($(this).attr('idx'));
				if (idx > max) {
					max = idx;
				};
			}
		);
		$.post('',{addTrigger: max, project_id: pid },
			function(data) {
				$('#triggers_config').append(data);
				updatePage();
			}
		);
	}

	function removeTrigger(id) {
		if ($('div.trigger').length == 1) {
			alert ('You can not delete the last trigger.');
		} else {
			$('div.trigger[idx='+id+']').remove();
      save();
		}
	}

	function testLogic(trigger) {
		var record = $('#test_record-'+trigger).val();
		var event_id = $('#test_event-'+trigger).val();
		var logic = $('#logic-'+trigger).val();
		var dest = $('#result-'+trigger);
		$(dest).html('<img src="'+app_path_images+'progress_circle.gif" class="imgfix"> Evaluating...');
		
		$.post('',{test: 1, record: record, event_id: event_id, logic: logic, project_id: pid },
			function(data) {
				var msg = data;
				$(dest).html(msg);
			}
		);
	}

	// Post to the provided URL with the specified parameters.
	function post(path, params) {
		var form = $('<form></form>');
		form.attr("method", "POST");
		form.attr("action", path);
		$.each(params, function(key, value) {
			var field = $('<input />');
			field.attr("type", "hidden");
			field.attr("name", key);
			field.attr("value", value);
			form.append(field);
		});
		// The form needs to be a part of the document in
		// order for us to be able to submit it.
		$(document.body).append(form);
		form.submit();
	}

	function updatePage() {
		// Prepare help buttons
		$('a.info').off('click').click(function(){
			var e = $(this).attr("info");
			$('#'+e).dialog({ title: 'AutoNotification Help', bgiframe: true, modal: true, width: 400, 
				open: function(){fitDialog(this)}, 
				buttons: { Close: function() { $(this).dialog('close'); } } });
		});		
		$('#title').css('font-weight','bold');
	}

  // If any trigger has a notification_email with a selection option equal to
  // the notification being removed, disallow
  function triggersUsingNotificationEmail(notification_email_id){
    
    var found_trigger_indices = [];
    $('div.trigger', '#triggers_config').each(
			function (index,value) {
        
        $('*:input', $(this)).each (function (index2, value2) {
          if ($(this).attr('id') === 'notification_email-'+(index+1)) {
            
            if ($(this).val() === notification_email_id) {
              found_trigger_indices.push(index+1); // Index starts at 0!
            }
          }
        });
      }
    );
    return found_trigger_indices;
  }
  
  
	// Add click event to all help buttons to show help
	$(document).ready(function() {
		updatePage();
	});


</script>


<?php
//Display the project footer
require_once APP_PATH_DOCROOT . 'ProjectGeneral/footer.php';
?>
