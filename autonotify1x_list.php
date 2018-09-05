<?php

/**
 * List and manage AutoNotify v1 DETs
 */

##
# © 2016 Partners HealthCare System, Inc. All Rights Reserved. 
##
/**
	
	Supporting functions for enhanced autonotification plugin.
 
  Originally based on Autonotify plugin by Andy Martin.
  As of June 2016, it support multiple notification emails in addition to multiple 
  triggers.  The new version offers the ability to upgrade existing autonitfy 
  configurations on first use.
	
	It must be used in conjunction with a data entry trigger to function in real-time.
	The settings for each project are stored as an encoded variable (an) in the query 
  string of the DET.
**/

require_once "../../redcap_connect.php";
require_once APP_PATH_DOCROOT . 'ProjectGeneral/header.php';

if(!isset($_GET['pid']) || !is_numeric($_GET['pid'])) {
    exit('Project ID is missing! Cannot continue!');
}

// Allow only super users to access this
if (defined('SUPER_USER') && SUPER_USER) {
    // Get a list of all current PIDs that are using the OLD AutoNotify DET
    // These can be identified by the "an=" in the data_entry_trigger_url database column
    
    $sql_users = "SELECT project_id,app_title,data_entry_trigger_url FROM redcap_projects WHERE data_entry_trigger_url IS NOT NULL AND data_entry_trigger_url like '%an=%';";
    $result = db_query ( $sql_users );
    $projects_list = array();
    while ($row = db_fetch_array($result)) {
	$projects_list[$row['project_id']] = array (
	    'app_title'			=> $row['app_title'],
	    'data_entry_trigger_url'	=> $row['data_entry_trigger_url']
	);
    }
    
    $table_headers = array (
	array (100, "Project ID", "center"),
	array (400, "Project Name", "center"),
	array (100, "View 2.0 Setup", "center"),
    );
    
    $table_data = array();
    foreach ( $projects_list as $proj_id => $proj_det ) {
	$view2x_config = RCView::button(array('class'=>'jqbuttonmed ui-button ui-widget ui-state-default ui-corner-all ui-button-text-only','onclick'=>'gotov2('.$proj_id.');', 'style'=>'margin:0px 10px;'), 'View 2.0 Setup');
	$table_data[] = array (
		RCView::div(array('class'=>"wrap", 'style'=>'font-weight:normal;padding:2px 0;', 'id'=>'row_1_'.$proj_id), $proj_id),
		RCView::div(array('class'=>"wrap", 'style'=>'font-weight:normal;padding:2px 0;', 'id'=>'row_2_'.$proj_id), $proj_det['app_title']),
		RCView::div(array('class'=>"wrap", 'style'=>'font-weight:normal;padding:2px 0;', 'id'=>'row_3_'.$proj_id), $view2x_config),
	);
    }
    
    $html .= renderGrid("AutoNotify", "AutoNotify v1.x to v2.x", 650, 'auto', $table_headers, $table_data, true, true, false);
    echo $html;
    
    $js = "<script type=\"text/javascript\">"
	    . "function gotov2 ( pid ) {"
	    . "	    window.location = 'index.php?pid='+pid;"
	    . "}"
	    . "</script>";
    echo $js;
}
else {
    exit('You need to be a super user to access this plugin!');
}

require_once APP_PATH_DOCROOT . 'ProjectGeneral/footer.php';