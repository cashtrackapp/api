<?php

// ----------------------------
// GLOBAL VARIABLES AND OBJECTS
// ----------------------------

// DATABASE RESET OPTION
// 0: normal operation
// 1: allow database reset operations
define ('ALLOW_DB_RESET',0);

// SERVICE STATUS OPTION
// 0: normal operation
// 1: service offline for maintenance
define ('SERVICE_STATUS',0);

// SERVICE TYPE OPTION
// 0: Development mode
// 1: Production mode
define ('RUN_MODE',1);

// AUTHENTICATION KEY LENGTH
// 120 preferred for production 
define ('KEY_LENGTH',120);

// AUTHENTICATION KEY EXPIRATION
// number of seconds before ukey expires
// default is 1 month = 2592000 seconds
define ('KEY_EXPIRE',2592000);

// CONFIRMATION CODE LENGTH
define ('CONFIRM_LENGTH',10);

// CONFIRMATION CODE EXPIRATION TIME
// in seconds from the time of issue
// 7 days preffered (7*24*60*60=604800)
define ('CONFIRM_EXPIRE',604800);

// PASSWORD ENCRYPTION SALT
// used to securely perform one-way password encryption
// DO NOT CHANGE! Doing so will break all user passwords!
define ('SALT','');

// DEFAULT PASSWORD
// default password for non-user accounts
define('DEFAULT_PASS','');

// DATABASE BACKUP LOCATION
// set the location to store database backups
define('BACKUP_LOC','/home/cashtrack/web/backup/');

// SQL DATABASE ACCESS OPTIONS
$db_user = "";
$db_password = "";
$db_server = "";

// SQL DATABASE AUTO SELECTION
if ( RUN_MODE == 1 ) $db_database = "cashtrack_prod";
else $db_database = "cashtrack_dev";

?>