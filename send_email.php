<?php 

// this script allows the sending of email to all users

require_once "globals.php";
require_once "helper.php";
require_once "db.php";
require_once "email.php";
require_once "message.php";
require_once "user.php";
require_once "history.php";
require_once "debt.php";
require_once "data.php";

$limit = 100;
// set to true to disable sending of emails
$test = true;

if ( $test ) echo "<h3>TEST MODE - SENDING DISABLED</h3>";

function count_users()
{
	global $db;
	db_send("use cashtrack_prod;");
	$statement = $db->prepare("SELECT count(*) FROM users WHERE email != ''");
	$count = 0;
	if ( $statement->execute() ) {
		$statement->bind_result($count);
		$statement->fetch();
		$statement->close();
	}
	return $count;
}

function num_batches() 
{
	global $limit;
	return ceil(count_users()/$limit);
}

function send_batch($body,$batch = 0) {
	global $db;
	global $limit;
	$start = $batch*$limit;
	db_send("use cashtrack_prod;");
	$statement = $db->prepare("SELECT email FROM users WHERE email != '' LIMIT ?,?");
	$statement->bind_param("ii",$start,$limit);
	if ( $statement->execute() ) {
		// get email addresses
		$users = array();
		$statement->bind_result($email);
		while ( $statement->fetch() ) {
			$users[] = $email;
		}
		$statement->close();
		// send email
		foreach ($users as $email) {
			echo "<ul>";
			echo "<li>Mailing user $email: ";
			if ( valid_email($email) ) {
				global $test;
				if ( !$test ) {
					if (email_send($email,"Announcement",$body)) echo "<strong>OK</strong></li>";
					else echo "<strong>ERROR!</strong></li>";
				} else echo "<strong>Sending disabled!</strong></li>";
			}
			else echo "<strong>Invalid email!</strong></li>";
			echo "</ul>";
		}
	}
}

if ( isset($_REQUEST["batch"]) and isset($_REQUEST["body"]) )
{
	echo "<h2>Message:</h2>";
	$body = $_REQUEST["body"];
	echo "<form><fieldset><pre>";
	echo $body;
	echo "</pre></fieldset></form>";
	$batch = $_REQUEST["batch"];
	$next = $batch+1;
	if ( $next < num_batches() ) echo "<form action='send_email.php' method='post'><input type='hidden' name='body' value='$body' /><input type='hidden' name='batch' value='$next' /><input type='submit' value='>>> Send batch ".($next+1)." >>>' /></form>";
	$num_batches = num_batches();
	echo "<h2>Sending batch ".($batch+1)." of $num_batches:</h2>";
	send_batch($body,$batch);
}
else 
{
	echo "<h2>Enter your message to send:</h2>";
	$next = 0;
	echo "<form action='send_email.php' method='post'>";
	echo "<textarea rows='20' cols='80' name='body'></textarea>";
	echo "<input type='hidden' name='batch' value='$next' />";
	echo "<br /><input type='submit' value='>>> Send batch $next >>>' /></form>";
	echo "<br /><strong>WARNING: EMAILS WILL GET SENT AS SOON AS YOU CLICK THIS BUTTON!</strong>";
}

?>