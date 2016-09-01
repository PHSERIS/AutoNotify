<?php
##
# © 2016 Partners HealthCare System, Inc. All Rights Reserved. 
##

	/**
	* This file contains some functions that were plucked out of the REDCap code from version 5.x.x
	* These functions were changed slightly in subsequent REDcap versions and as a result they have been extracted here for longevity
	*/

        // Function for decrypting
	/**
	 * 
	 * @global type $salt
	 * @param type $encrypted_data
	 * @param type $custom_salt
	 * @return boolean
	 */
        function decrypt_static($encrypted_data, $custom_salt=null)
        {
                if (!mcrypt_loaded_static()) return false;
                // $salt from db connection file
                global $salt;
                // If $custom_salt is not provided, then use the installation-specific $salt value
                $this_salt = ($custom_salt === null) ? $salt : $custom_salt;
                
                // This loop is for REDCap 5.x support.
                $iloops = 0;
                while ( strlen ( $this_salt ) < 32 ) {
                        $this_salt = $this_salt."".$this_salt; // append the stal to itself until it's over 32
                        if ( $iloops == 10 ) break;
                        $iloops+=1;
                }
                // If salt is longer than 32 characters, then truncate it to prevent issues
                if (strlen($this_salt) > 32) $this_salt = substr($this_salt, 0, 32);
                // Define an encryption/decryption variable beforehand
                defined("MCRYPT_IV") or define("MCRYPT_IV", mcrypt_create_iv(mcrypt_get_iv_size(MCRYPT_RIJNDAEL_256, MCRYPT_MODE_ECB), MCRYPT_RAND));
                // Decrypt and return
                return rtrim(mcrypt_decrypt(MCRYPT_RIJNDAEL_256, $this_salt, base64_decode($encrypted_data), MCRYPT_MODE_ECB, MCRYPT_IV),"\0");
        }

        // Function for checking if mcrypt PHP extension is loaded
        function mcrypt_loaded_static($show_error=false) {
		    if ((extension_loaded('mcrypt') || (function_exists('dl') && dl(((PHP_SHLIB_SUFFIX === 'dll') ? 'php_' : '') . (null ? null : 'mcrypt') . '.' . PHP_SHLIB_SUFFIX))) != 1) {
                        if ($show_error) {
                                exit('<div class="red"><b>ERROR:</b><br>The "mcrypt" PHP extension is not loaded but is required for encryption/decryption.<br>
                                  Please install the PHP extension "mcrypt" on your server, reboot your server, and then reload this page.</div>');
                        } else {
                                return false;
                        }
                } else {
                        return true;
                }
        }

        // Function for encrypting
        function encrypt_static($data, $custom_salt=null)
        {
                if (!mcrypt_loaded_static()) return false;
                // $salt from db connection file
                global $salt;
                // If $custom_salt is not provided, then use the installation-specific $salt value
                $this_salt = ($custom_salt === null) ? $salt : $custom_salt;

                // This loop is for REDCap 5.x support.
                $iloops = 0;
                while ( strlen ( $this_salt ) < 32 ) {
                        $this_salt = $this_salt."".$this_salt; // append the salt to itself until it's over 32
                        if ( $iloops == 10 ) break; // don't be silly - have an exit strategy
                        $iloops+=1;
                }
                // If salt is longer than 32 characters, then truncate it to prevent issues
                if (strlen($this_salt) > 32) $this_salt = substr($this_salt, 0, 32);
                // Define an encryption/decryption variable beforehand
                defined("MCRYPT_IV") or define("MCRYPT_IV", mcrypt_create_iv(mcrypt_get_iv_size(MCRYPT_RIJNDAEL_256, MCRYPT_MODE_ECB), MCRYPT_RAND));
                // Encrypt and return
                return rtrim(base64_encode(mcrypt_encrypt(MCRYPT_RIJNDAEL_256, $this_salt, $data, MCRYPT_MODE_ECB, MCRYPT_IV)),"\0");
        }
?>
