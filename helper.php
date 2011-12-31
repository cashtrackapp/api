<?php

// --------------
// KEY MANAGEMENT
// --------------

// generate a random string of length $length
function random_string($length)
{
	// setup available characters
	$characters = "0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ"; 
	// assemble random key
	$key = "";
	for($pos = 0; $pos < $length; $pos++) {
		$key .= $characters[rand(0,strlen($characters)-1)];
	}
	return $key;
}

// remove old user keys
function expire_keys($user_id)
{
	global $db;
	$timeout = KEY_EXPIRE;
	$expire = time()-$timeout;
	$statement = $db->prepare("DELETE FROM sessions WHERE user_id = ? AND timestamp < ?");
	$statement->bind_param("ii",$user_id,$expire);
	$statement->execute();
	$statement->close();
}

// saves and returns a new authentication key for $user
function generate_key($user_id)
{
	// expire old user keys
	expire_keys($user_id);

	// create a new authentication key (loop if key is a duplicate)
	global $db;
	$statement = $db->prepare("INSERT INTO sessions (auth_key,user_id,timestamp) VALUES (?,?,?)");
	$new_key = "";
	do {
		$new_key = random_string(KEY_LENGTH);
		$statement->reset();
		$timestamp = time();		
		$statement->bind_param("sii",$new_key,$user_id,$timestamp);
	} while ( !$statement->execute() );
		
	// return new key
	$statement->close();
	return $new_key;
}

// ---------------------
// USER HELPER FUNCTIONS
// ---------------------

// encrypt a user password
function crypt_pass($password) 
{
	$salt = SALT;
	return md5($password.$salt);
}

// returns user associated with a key, false on error
function get_user($key)
{
	// expire old user keys
	expire_keys($user_id);
	
	// lookup authentication key
	global $db;
	$statement = $db->prepare("SELECT user_id FROM sessions WHERE auth_key = ?");
	$statement->bind_param("s",$key);
	
	if ( $statement->execute() ) {
		$statement->bind_result($user_id);
		$statement->fetch();
		$statement->close();
		return $user_id;
	}
	else return false;	
}

// checks user,password combination, returns false on failure
function authenticate($user,$user_pass,$auth_type = "CLEAR")
{

	if ( $user == "" or $user_pass == "" ) return false;
	
	// lookup password for comparison
	global $db;
	$statement = $db->prepare("SELECT user_id, password_type, password FROM users WHERE username = ?");
	$statement->bind_param("s",$user);
	
	if ( $statement->execute() ) {
		$statement->bind_result($user_id,$db_pass_type,$db_pass);
		$statement->fetch();
		$statement->close();
		
		// check password		
		switch($auth_type) {
			case "MD5":
				// user provided MD5 encrypted password
				switch($db_pass_type) {
					case "MD5":
						// db password is MD5 encrypted
						if ($user_pass == $db_pass) return $user_id;
						break;
					default:
						// db password is in cleartext
						if ($user_pass == crypt_pass($db_pass)) return $user_id;
						break;
				};
				break;
			case "CLEAR":
				// user provided cleartext password
				switch($db_pass_type) {
					case "MD5":
						// db password is MD5 encrypted
						if (crypt_pass($user_pass) == $db_pass) return $user_id;
						break;
					default:
						// db password is in cleartext
						if ($user_pass == $db_pass) return $user_id;
						break;
				}
				break;
			default:
				// invalid auth type
				return false;
		}
		// bad username password combination
		return false;		

	}
	else return false;
}

// clean up a phone number string
function phone_clean($phone)
{
	return ereg_replace("[^0-9]", "", $phone );
}

// check for valid email address
function valid_email($email)
{
	if ( strlen($email) < 5) return false;
	
	// check for '@' and valid length
	if (!ereg("^[^@]{1,64}@[^@]{1,255}$", $email)) {
	        return false;
	}
	// split parts
	$email_array = explode("@", $email);
	$local_array = explode(".", $email_array[0]);
	for ($i = 0; $i < sizeof($local_array); $i++) {
		if (!ereg("^(([A-Za-z0-9!#$%&'*+/=?^_`{|}~-][A-Za-z0-9!#$%&'*+/=?^_`{|}~\.-]{0,63})|(\"[^(\\|\")]{0,62}\"))$", $local_array[$i])) {
			return false;
		}
	}    
	if (!ereg("^\[?[0-9\.]+\]?$", $email_array[1])) { 
		// check for valid domain
		$domain_array = explode(".", $email_array[1]);
		if (sizeof($domain_array) < 2) {
			return false; 
		}
		for ($i = 0; $i < sizeof($domain_array); $i++) {
			if (!ereg("^(([A-Za-z0-9][A-Za-z0-9-]{0,61}[A-Za-z0-9])|([A-Za-z0-9]+))$", $domain_array[$i])) {
				return false;
			}
		}
	}
	return true;
}

// check for valid phone number
function valid_phone($phone)
{
	$phone = phone_clean($phone);
	if ( strlen($phone) == 10 ) return true;
	else return false;
}

// ---------------
// SERVICE HELPERS
// ---------------

// check and sanitize server input
// valid expected types: 'bool', 'int', 'float', 'string', 'null', 'time'
function sanitize($input,$expected,$default) 
{
	global $db;
	
	// check isset 
	if ( !isset($input) ) $input = $default;
	
	// sanitize input
	if ( isset($input) ) {
		switch ( $expected ){
			case "null":
				$clean = null; 
				break;
			case "bool":
				$clean = ($input === "true" or $input === 1 or $input === true ) ? true : false;
				break;
			case "int":
				$clean = (int) $input;
				if ( !is_int($clean) ) return $default;
				break;
			case "float":
				$clean = (float) $input; 
				if ( !is_float($clean) ) return $default;
				break;
			case "string";
				$clean = (string) $db->real_escape_string($input);
				if ( strlen($clean) == 0 ) return $default;
				break;
			case "time":
				$clean = (int) $input;
				if ( !is_int($clean) ) return $default;
				if ( $clean < 0 ) $clean = 0;
				if ( $clean > time() ) $clean = time();
				break;
		}
		return $clean;
	} else return null;
}

// welcome function
// gives client important information
function welcome()
{
	$array = array();
	// show a welcome message to the client
	if ( RUN_MODE == 0 ) {
		$array["message_show"] = true;
		$array["message_text"] = "Welcome to CashTrack 1.2! This server is currently running in developer mode.";
	} else {
		$array["message_show"] = false;
		$array["message_text"] = "";
	}
	// inform the client to use another server instead of this one
	$array["server_valid"] = true;
	$array["server_new"] = "";
	
	// tell the client the current server time
	$array["server_time"] = time();

	return $array;
}

// check developer key
function check_dkey($dkey) 
{
	switch ($dkey) {
		case "V0upnOgR1mg3POJDdrHMcTFy2QFEOb9qIahdlgrAHKX1Dr5T6pP7rQoah5ahxZP":
			// CashTrack 1.2
			return true;
		default:
			// invalid developer key
			//return false;
			return true; // TEMP TEMP TEMP
	}
}

// add an item to a comma deliminated string
function string_put($old,$add)
{
	if ( strlen($old) > 0 ) return $old.", ".$add;
	else return $add;
}

// add 'and' before last iterm in comma deliminated string
function string_finish($old)
{
	if ( $pos = strripos($old,", ") ) {
		$begin = substr($old,0,$pos);
		$end = substr($old,$pos+2);
		$old = $begin." and ".$end;
	}
	return $old;
}

// ------------------------------------------------
// DATABASE MAINTENANCE FUNCTIONS (here be dragons)
// ------------------------------------------------

// perform a full database backup
function backupDB()
{
	// get globals
	global $db_server;
	global $db_user;
	global $db_password;
	global $db_database;
	
	// set backup file location
	$dir = BACKUP_LOC;
	$mode = ( RUN_MODE == 0 ) ? "dev" : "prod";
	$file = "cashtrack-$mode-backup-".time().".sql";
	// prepare a unique mysql database backuo
	$path = $dir.$file;
	// prepare table dump command
	$command = "/usr/bin/mysqldump --opt --host=$db_server --user=$db_user --password=".$db_password." $db_database > $path";
	$output = shell_exec($command);
	if ( $output ) return false;
	else return $path;	
}

function RunDBBackup()
{
	// check action request
	if ( $_REQUEST["action"] != "backupdb") return;
	
	echo "NOTICE: DATABASE BACKUP HAS BEEN REQUESTED.<br />";
	if ( $file = backupDB() ) echo "Backup successful. All tables and data saved to '$file'.<br />";
	else echo "Backup operation failed.";
	exit(0);
}

// check if database needs to be reloaded
function RunDBReset()
{
	// check for action request
	if ( $_REQUEST["action"] != "resetdb") return;

	echo "NOTICE: DATABASE RESET HAS BEEN REQUESTED.<br />";
	// only allow reset when in dev mode
	if (ALLOW_DB_RESET == 1 and RUN_MODE == 0) {
		echo "ATTEMPTING BACKUP OPERATION NOW.<br />";
		if ( $file = backupDB() ) {
			echo "Backup successful. All tables and data saved to '$file'.<br />";
			echo "WARNING: DATABASE IS NOW BEING RESET. ALL TABLES WILL BE DROPPED!<br />";
			resetDB();
			loadDB();
			echo "All operation completed.";
			exit();
		}
		else echo "Backup operation failed.<br />Database reset has been cancelled.";
	}
	else echo "Database reset is not allowed. Set global ALLOW_DB_RESET = 1 to allow operation.<br />Database reset has been cancelled.";
	exit(0);
	
}

// check if database needs to be migrated
// i.e. copy all data from prod to dev database
function RunDBMigrate()
{
	// check for action request
	if ( $_REQUEST["action"] != "migratedb") return;
	
	echo "NOTICE: DATABASE MIGRATE HAS BEEN REQUESTED.<br />";
	if (ALLOW_DB_RESET == 1 and RUN_MODE == 0) {
		echo "ATTEMPTING BACKUP OPERATION NOW.<br />";
		if ( $file = backupDB() ) {
			echo "Backup successful. All tables and data saved to '$file'.<br />";
			echo "WARNING: DATABASE IS NOW BEING RESET. ALL TABLES WILL BE DROPPED!<br />";
			resetDB();
			echo "All tables have been dropped. Starting database migration.<br />";
			if ( migrateDB() ) echo "Data migration has been completed. All production data copied to dev database.";
			else echo "Data migration has failed. ";
		}
		else echo "Backup operation failed.<br />Database migrate has been cancelled.";
	}
	else echo "Database reset is not allowed. Set global ALLOW_DB_RESET = 1 to allow operation.<br />Database reset has been cancelled.";
	exit(0);
}

?>