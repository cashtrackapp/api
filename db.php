<?php 

// ----------------
// DATABASE CONNECT
// ----------------

global $db_user;
global $db_password;
global $db_database;
global $db_server;

// establish a new mysqli connection
$db = new mysqli($db_server,$db_user,$db_password,$db_database);

if ( $err = $db->connect_error) {
	echo "Unable to connect to database! ".$err;
	exit("SQL Connect Error: ".$err);
}


// ----------------
// DATABASE HELPERS
// ----------------

function db_send($sql)
{
	global $db;
	if ( !$result = $db->query($sql)) {
		echo "<br />An error occurred while committing the last database command.";
		echo "<br />SQL: $sql<br />------";
		echo "<br />Error: ".$db->errno." - ".$db->error;
		echo "<br />------<br />";
		die("DATABASE_ERROR");
	}
	return true;
}

// ------------------------------
// DROP ALL TABLES AND START OVER
// ---------------

function resetDB()
{
	// ------------------------------
	// DROP ALL TABLES AND START OVER
	// ------------------------------
	
	// !! CAREFUL !!
	db_send("SET foreign_key_checks = 0;");
	if (db_send("DROP VIEW IF EXISTS search;",$db)) echo "View 'search' dropped!";
	if (db_send("DROP TABLE IF EXISTS users;",$db)) echo "Table 'users' dropped!";
	if (db_send("DROP TABLE IF EXISTS sessions;",$db)) echo "Table 'sessions' dropped!";
	if (db_send("DROP TABLE IF EXISTS friends;",$db)) echo "Table 'friends' dropped!";
	if (db_send("DROP TABLE IF EXISTS debts;",$db)) echo "Table 'debts' dropped!";
	if (db_send("DROP TABLE IF EXISTS messages;",$db)) echo "Table 'messages' dropped!";
	if (db_send("DROP TABLE IF EXISTS announcements;",$db)) echo "Table 'announcements' dropped!";
	if (db_send("DROP TABLE IF EXISTS history;",$db)) echo "Table 'history' dropped!";
	if (db_send("DROP TABLE IF EXISTS confirmation;",$db)) echo "Table 'confirmation' dropped!";
	db_send("SET foreign_key_checks = 1;");
	
	// --------------------
	// DATABASE TABLE SETUP
	// --------------------
	
	// USERS
	// keep track of all users
	$def_pass = DEFAULT_PASS;
	$sql =  "CREATE TABLE IF NOT EXISTS users( ".
			"user_id INT UNSIGNED AUTO_INCREMENT, ". 
			"username VARCHAR(25) UNIQUE, ". // not required for owned user
			"password_type VARCHAR(10) NOT NULL DEFAULT 'CLEAR', ".
			"password VARCHAR(128) NOT NULL DEFAULT '$def_pass', ".
			"fname VARCHAR(50), ".
			"lname VARCHAR(50), ".
			"verified BOOL NOT NULL DEFAULT FALSE, ". // 1 - real user, 0 - owned user
			"owner_id INT UNSIGNED, ". // if not a real user, the user_id of the owner
			"email VARCHAR(50) NOT NULL DEFAULT '', ".
			"email_valid BOOL NOT NULL DEFAULT FALSE, ".
			"email_notify BOOL NOT NULL DEFAULT TRUE, ".
			"phone VARCHAR(50) NOT NULL DEFAULT '', ".
			"phone_valid BOOL NOT NULL DEFAULT FALSE, ".
			"phone_notify BOOL NOT NULL DEFAULT FALSE, ".
			"photo VARCHAR(256) NOT NULL DEFAULT '', ".
			"created INT NOT NULL, ".
			"PRIMARY KEY (user_id) ) ENGINE=INNODB";
	db_send($sql,$db);

	// SEARCH VIEW
	// keep a list of users for searching
	$sql = "CREATE VIEW search AS SELECT user_id, username, email, phone, CONCAT_WS(' ',fname,lname) AS name FROM users";
	db_send($sql,$db);
	
	// SESSIONS
	// keep track of all login sessions
	$sql =	"CREATE TABLE IF NOT EXISTS sessions( ".
			"auth_key VARCHAR(255) NOT NULL UNIQUE, ".
			"user_id INT UNSIGNED NOT NULL, ".
			"timestamp INT UNSIGNED NOT NULL, ".
			"PRIMARY KEY (auth_key), ".
			"FOREIGN KEY (user_id) REFERENCES users (user_id) ON DELETE CASCADE ON UPDATE CASCADE".
			" ) ENGINE=INNODB";
	db_send($sql,$db);

	// FRIENDS
	// keep track of all friend relationships
	$sql =	"CREATE TABLE IF NOT EXISTS friends( ".
			"friend1 INT UNSIGNED NOT NULL, ".
			"friend2 INT UNSIGNED NOT NULL, ".
			"timestamp INT UNSIGNED NOT NULL, ".
			"confirmed TINYINT NOT NULL, ". // 0 - friend1, 1 - friend2, 2 - confirmed, 3 - removed (???)
			"FOREIGN KEY (friend1) REFERENCES users (user_id) ON DELETE CASCADE ON UPDATE CASCADE, ".
			"FOREIGN KEY (friend2) REFERENCES users (user_id) ON DELETE CASCADE ON UPDATE CASCADE, ".
			"PRIMARY KEY (friend1, friend2) ) ENGINE=INNODB";
	db_send($sql,$db);

	// DEBTS
	// keep track of all debts
	$sql =	"CREATE TABLE IF NOT EXISTS debts( ".
			"debt_id INT UNSIGNED UNIQUE NOT NULL AUTO_INCREMENT, ".
			"borrower_id INT UNSIGNED NOT NULL, ".
			"lender_id INT UNSIGNED NOT NULL, ".
			"amount DOUBLE(10,2) NOT NULL, ".
			"category VARCHAR(50), ".
			"details VARCHAR(250), ".
			"date INT UNSIGNED NOT NULL, ".
			"date_due INT UNSIGNED, ".
			"paid BOOL NOT NULL DEFAULT FALSE, ".
			"FOREIGN KEY (borrower_id) REFERENCES users (user_id) ON DELETE CASCADE ON UPDATE CASCADE, ".
			"FOREIGN KEY (lender_id) REFERENCES users (user_id) ON DELETE CASCADE ON UPDATE CASCADE, ".
			"PRIMARY KEY (debt_id), ".
			"INDEX(date), INDEX(date_due), INDEX(paid), INDEX user (borrower_id,lender_id) ) ENGINE=INNODB";
	db_send($sql,$db);

	// HISTORY
	// keep track of bill history
	$sql =	"CREATE TABLE IF NOT EXISTS history( ".
			"event_id INT UNSIGNED UNIQUE NOT NULL AUTO_INCREMENT, ".
			"debt_id INT UNSIGNED NOT NULL, ".
			"user_id INT UNSIGNED NOT NULL, ".
			"event VARCHAR(50) NOT NULL, ".
			"details VARCHAR(250), ".
			"timestamp INT UNSIGNED NOT NULL, ".
			"FOREIGN KEY (user_id) REFERENCES users (user_id) ON DELETE CASCADE ON UPDATE CASCADE, ".
			"FOREIGN KEY (debt_id) REFERENCES debts (debt_id) ON DELETE CASCADE ON UPDATE CASCADE, ".
			"PRIMARY KEY (event_id), ".
			"INDEX(debt_id), INDEX(user_id), INDEX(timestamp) ) ENGINE=INNODB";
	db_send($sql,$db);
			

	// MESSAGES
	// keep track of user messages, notification, and popups
	$sql =	"CREATE TABLE IF NOT EXISTS messages( ".
			"message_id INT UNSIGNED UNIQUE NOT NULL AUTO_INCREMENT, ".
			"user_id INT UNSIGNED NOT NULL, ".
			"from_id INT UNSIGNED, ".
			"viewed BOOL NOT NULL DEFAULT FALSE, ".
			"date_created INT UNSIGNED NOT NULL, ".
			"date_expire INT UNSIGNED, ".
			"subject VARCHAR(100) NOT NULL, ".
			"topic VARCHAR(50) NOT NULL DEFAULT 'MESSAGE', ".
			"body VARCHAR(2000) NOT NULL, ".
			"FOREIGN KEY (user_id) REFERENCES users (user_id) ON DELETE CASCADE ON UPDATE CASCADE, ".
			"FOREIGN KEY (from_id) REFERENCES users (user_id) ON DELETE CASCADE ON UPDATE CASCADE, ".
			"PRIMARY KEY (message_id), ".
			"INDEX view(user_id,viewed,date_created) ) ENGINE=INNODB";
	db_send($sql,$db);

	// ANNOUNCEMENTS
	// keep track of system announcments
	$sql =	"CREATE TABLE IF NOT EXISTS announcements( ".
			"announcement_id INT UNSIGNED UNIQUE NOT NULL AUTO_INCREMENT, ".
			"date_start INT UNSIGNED NOT NULL, ".
			"date_end INT UNSIGNED NOT NULL, ".
			"subject VARCHAR(50) NOT NULL, ".
			"text VARCHAR(5000) NOT NULL, ".
			"PRIMARY KEY (announcement_id) ) ENGINE=INNODB";
	db_send($sql,$db);

	// CONFIRMATION
	// keep track of email confirmation codes
	$sql =	"CREATE TABLE IF NOT EXISTS confirmation( ".
			"code VARCHAR(20) UNIQUE NOT NULL, ".
			"user_id INT UNSIGNED UNIQUE NOT NULL, ".
			"date_expire INT UNSIGNED NOT NULL, ".
			"FOREIGN KEY (user_id) REFERENCES users (user_id) ON DELETE CASCADE ON UPDATE CASCADE, ".
			"INDEX(code) ) ENGINE=INNODB";
	db_send($sql,$db);
	
}

?>
