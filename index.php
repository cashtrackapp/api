<?php

require_once "preloadhelper.php";
$time_start = get_time();

// ---------------
// RUN WEB SERVICE
// ---------------

// Service will respond to either POST or GET requests, however POST is preffered since
// we will run into max_url_length issues with GET requests

// ----------------------
// SERVICE RUN CONDITIONS
// ----------------------

// Check service status
if ( SERVICE_STATUS == 1 ) {
	$response["error"] = true;
	$response["error_detail"] = "Service is offline for maintenance.";
}

// Check 5 minute load average
$load = sys_getloadavg();
if ( $load[1] > 2.0 ) {
	$response["error"] = true;
	$response["error_detail"] = "Service load is too high. Please try again later.";
}

// Check for ip blacklist
if ( blacklist_check($_SERVER["REMOTE_ADDR"]) ) {
	$response["error"] = true;
	$response["error_detail"] = "Service denied. Remote address has been blacklisted.";
}

// CHECK FOR ERRORS

if ( !$response["error"] ) 
{
	// -----------
	// LOAD SYSTEM
	// -----------
	
	require_once "globals.php";
	require_once "helper.php";
	require_once "db.php";
	require_once "email.php";
	require_once "message.php";
	require_once "user.php";
	require_once "history.php";
	require_once "debt.php";
	require_once "data.php";
	require_once "updates.php";
	
	// --------------------
	// RUN DATABASE ACTIONS
	// --------------------
	
	RunDBBackup();
	RunDBReset();
	RunDBMigrate();
	
	// -----------------
	// DEFINE PARAMETERS
	// -----------------
	
	$action 	 = sanitize($_REQUEST["action"],"string","");	// action to be called		
	$amount		 = sanitize($_REQUEST["amount"],"float",0.0);	// amount of debt	
	$body		 = sanitize($_REQUEST["body"],"string","");		// message body	
	$borrower_id = sanitize($_REQUEST["borrower_id"],"int",0);	// borrower's id				
	$category	 = sanitize($_REQUEST["category"],"string","");	// debt category	
	$code		 = sanitize($_REQUEST["code"],"string","");		// confirmation code		
	$date		 = sanitize($_REQUEST["date"],"int",0);			// date timestamp
	$date_due	 = sanitize($_REQUEST["date_due"],"int",0);		// debt due date		
	$date_expire = sanitize($_REQUEST["date_expire"],"int",0);	// message expire date				
	$debt_id	 = sanitize($_REQUEST["debt_id"],"int",0);		// debt id		
	$details	 = sanitize($_REQUEST["details"],"string","");	// debt details		
	$dkey 		 = sanitize($_REQUEST["dkey"],"string","");		// developer's authentication key	
	$email		 = sanitize($_REQUEST["email"],"string","");	// user's email address	
	$event_id	 = sanitize($_REQUEST["event_id"],"int",0);		// history event id	
	$fname		 = sanitize($_REQUEST["fname"],"string","");	// user's first name	
	$format		 = sanitize($_REQUEST["format"],"string","");	// server response type
	$friend_id	 = sanitize($_REQUEST["friend_id"],"int",0);	// friend's id		
	$lender_id	 = sanitize($_REQUEST["lender_id"],"int",0);	// lender's user id		
	$lname		 = sanitize($_REQUEST["lname"],"string","");	// user's last name	
	$message_id	 = sanitize($_REQUEST["message_id"],"int",0);	// message id		
	$name		 = sanitize($_REQUEST["name"],"string","");		// user's whole name	
	$new_pass	 = sanitize($_REQUEST["new_pass"],"string","");	// user's new password			
	$paid		 = sanitize($_REQUEST["paid"],"int",0);			// bill status
	$password	 = sanitize($_REQUEST["password"],"string","");	// user's password			
	$phone		 = sanitize($_REQUEST["phone"],"string","");	// user's phone number	
	$photo		 = sanitize($_REQUEST["photo"],"string","");	// user's photo location	
	$search 	 = sanitize($_REQUEST["search"],"string","");	// search term		
	$subject	 = sanitize($_REQUEST["subject"],"string","");	// message subject	
	$timestamp 	 = sanitize($_REQUEST["timestamp"],"time",0);	// timestamp
	$ukey 		 = sanitize($_REQUEST["ukey"],"string","");		// user's authentication key	
	$user_id	 = sanitize($_REQUEST["user_id"],"int",0);		// user's id	
	$username	 = sanitize($_REQUEST["username"],"string","");	// user's username			
	
	// Set application preferences
	$format		= ( $format ) ? $format : "JSON";
	$response	= array( "execution_time" => 0, "action" => $action, "error" => false );
	
	// -------------------------
	// AUTHENTICATION CONDITIONS
	// -------------------------
	
	// Check developer key
	if ( !check_dkey($dkey) ) {
		$response["error"] = true;
		$response["error_detail"] = "Invalid developer key.";
	}
	
	// Check user key
	if ( $action != "login" and $action != "user_create" and $action != "welcome" ) {
		$remote_user = get_user($ukey);
		if ( !$remote_user ) {
			$response["error"] = true;
			$response["error_detail"] = "Invalid user key.";
		}
	}
	
	// CHECK FOR ERRORS
	
	if ( !$response["error"] ) 
	{
	
		// ------------------
		// RUN SERVER ACTIONS
		// ------------------
		
		// LOGIN
		// login to server, auth key provided upon success
		if ( $action == "login" ) {
			if ( $key = login($username,$password) ) {
				$response["error"] = false;
				$response["user_id"] = get_user($key);
				$response["key"] = $key;
			} else {
				$response["error"] = true;
				$response["error_detail"] = "Invalid username or password.";
			}
		}
		// WELCOME
		// show welcome message, set client variables
		elseif ( $action == "welcome" ) {
			$response["error"] = false;
			$response["return"] = welcome();
		}
		// UPDATE_CHECK
		// check for new debts, messages, and friends 
		elseif ( $action == "update_check" ) {
			$response["return"] = update_check($remote_user,$timestamp);
			$response["error"] = false;
		}
		// USER_SEARCH
		// search users database
		elseif ( $action == "user_search" ) {
			if ( $findings = user_search($search) ) {
				$response["error"] = false;
				$response["return"] = $findings;
			} else {
				$response["error"] = true;
				$response["error_detail"] = "An error occurred when searching.";
			}
		}
		// USER_SEARCH_FIELDS
		// search specific user fields
		elseif ( $action == "user_search_fields" ) {
			if ( $findings = user_search_fields($username,$email,$phone,$name) ) {
				$response["error"] = false;
				$response["return"] = $findings;
			} else {
				$response["error"] = true;
				$response["error_detail"] = "An error occurred when searching fields.";
			}
		}
		// USER_DETAILS
		// show user details
		elseif ( $action == "user_details" ) {
			if ( $details = user_details($user_id) ) {
				$response["error"] = false;
				$response["return"] = $details;
			} else {
				$response["error"] = true;
				$response["error_detail"] = "Invalid user_id.";
			}
		}
		// USER_UPDATE
		// update user details
		elseif ( $action == "user_update" ) {
			$details = user_update($user_id,$username,$password,$new_pass,$fname,$lname,$email,$phone,$photo);
			if ( $details == 0 ) {
				$response["error"] = false;
				$response["result"] = $details;
			} else {
				$response["error"] = true;
				$response["result"] = $details;
				$response["error_code"] = $details;
			}
		}
		// USER_CREATE
		// create a new 'real' user
		elseif ( $action == "user_create" ) {
			if ( $new_id = user_create($username,$password,$fname,$lname,$email,$phone) ) {
				$response["error"] = false;
				$response["return"] = $new_id;
			} else {
				$response["error"] = true;
				$response["error_detail"] = "Error creating user.";
			}
		}
		// USER_CREATE_SPECIAL
		// create a new 'owned' user
		elseif ( $action == "user_create_special" ) {
			if ( $new_id = user_create_special($remote_user,$fname,$lname,$email,$phone) ) {
				$response["error"] = false;
				$response["return"] = $new_id;
			} else {
				$response["error"] = true;
				$response["error_detail"] = "Error creating user.";
			}
			
		}	
		// EMAIL SET 
		// set a user's email address
		elseif ( $action == "email_set" ) {
			if ( email_set($remote_user,$email) ) {
				$response["error"] = false;
			} else {
				$response["error"] = true;
				$response["error_detail"] = "Error changing email address.";
			}
		}
		// EMAIL_CONFIRM 
		// input a unique code and confirm an email address
		elseif ( $action == "email_confirm" ) {
			if ( email_confirm($remote_user,$code) ) {
				$response["error"] = false;
			} else {
				$response["error"] = true;
				$response["error_detail"] = "Error confirming email address.";
			}
		}
		// EMAIL VERIFY
		// send an email verification code
		elseif ( $action == "email_verify") {
			email_verify($user_id,$email);
			$response["error"] = false;
		}	
		// FRIEND_LIST
		// list all friends
		elseif ( $action == "friend_list" ) {
			$friends = friend_list($remote_user);
			if ( $friends === false ) {
				$response["error"] = true;
				$response["error_detail"] = "Error listing friends.";
			} else {
				$response["error"] = false;
				$response["return"] = $friends;
			}
		}
		// FRIEND_ADD
		// add a new friend
		elseif ( $action == "friend_add" ) {
			$details = friend_add($remote_user, $friend_id);
			if ( $details == 0 ) {
				$response["error"] = false;
			} elseif($details == 1) {
				$response["error"] = true;
				$response["error_detail"] = "Already Friends";
				$response["error_code"] = $details;
			} elseif($details == 2) {
				$response["error"] = true;
				$response["error_detail"] = "Error adding Friend.";
				$response["error_code"] = $details;
			} elseif($details == 3) {
				$response["error"] = true;
				$response["error_detail"] = "Cannot friend yourself";
				$response["error_code"] = $details;
			}
		}
		// FRIEND_REMOVE
		// remove a friend
		elseif ( $action == "friend_remove" ) {
			if ( friend_remove($remote_user,$friend_id) ) {
				$response["error"] = false;
				$response["return"] = true;
			} else {
				$response["error"] = true;
				$response["error_detail"] = "Error removing friend.";
			}
		}
		// FRIEND_CONFIRM
		// confirm a new friend
		elseif ( $action == "friend_confirm" ) {
			if ( friend_confirm($remote_user,$friend_id) ) {
				$response["error"] = false;
				$response["return"] = true;
			} else {
				$response["error"] = true;
				$response["error_detail"] = "Error confirming friend.";
			}
		}
		// FRIEND_CHECK (new)
		elseif ( $action == "friend_check" ) {
			if ( $result = friend_check($remote_user,$friend_id) ) {
				$response["error"] = false;
				$response["return"] = $result;
			} else {
				$response["error"] = true;
			}
		}
		// FRIEND_DENY (new)
		// deny a new friend request
		elseif ( $action == "friend_deny" ) {
	
		}
		// FRIEND_BLOCK (new)
		// block all future friend requests
		elseif ( $action == "friend_deny" ) {
		
		}
		// MESSAGE_ALL 
		// show all messages for user
		elseif ( $action == "message_all" ) {
			$messages = message_all($remote_user); 
			$response["error"] = false;
			$response["return"] = $messages;
		}
		// MESSAGE_ALL_UNREAD 
		// show all unread messages for user
		elseif ( $action == "message_all_unread" ) {
			$messages = message_all_unread($remote_user);
			$response["error"] = false;
			$response["return"] = $messages;
		}
		// MESSAGE_ALL_SENT 
		// show all sent messages from user
		elseif ( $action == "message_all_sent" ) {
			$messages = message_all_sent($remote_user);
			$response["error"] = false;
			$response["return"] = $messages;
		}
		// MESSAGE_DETAILS 
		// show details of one message
		elseif ( $action == "message_details" ) {
			if ( $details = message_details($message_id,$remote_user) ) {
				$response["error"] = false;
				$response["return"] = $details;
			} else {
				$response["error"] = true;
				$response["error_detail"] = "Error retrieving message.";
			}
		}
		// MESSAGE_VIEWED
		// mark a messages as viewed
		elseif ( $action == "message_viewed" ) {
			if ( $result = message_viewed($message_id,$remote_user) ) {
				$response["error"] = false;
				$response["return"] = $result;
			} else {
				$response["error"] = true;
				$response["error_detail"] = "Error marking message as read.";
			}
		}
		// MESSAGE_CREATE
		// create a new message
		elseif ( $action == "message_create" ) {
			if ( $result = message_create($user_id,$subject,$body,$remote_user,$date_expire,true,"MESSAGE") ) {
				$response["error"] = false;
				$response["return"] = $result;
			} else {
				$response["error"] = true;
				$response["error_detail"] = "Error creating message.";
			}
		}
		// DEBT_ALL
		// get all debts
		elseif ( $action == "debt_all" ) {
			$debts = debt_all($user_id);
			$response["error"] = false;
			$response["return"] = $debts;
		}
		// DEBT_ADD
		// add a new debt
		elseif ( $action == "debt_add" ) {
			if ( $debt_id = debt_add($borrower_id, $lender_id, $amount, 
					$category, $details, $date, $paid, $date_due) ) {
				$response["error"] = false;
				$response["return"] = $debt_id;
			} else {
				$response["error"] = true;
				$response["error_detail"] = "Error adding debt.";
			}
		}
		// DEBT_EDIT
		// edit details for one debt
		elseif ( $action == "debt_edit" ) {
			if ( debt_edit($debt_id, $borrower_id, $lender_id, $amount, 
				$category, $details, $date, $paid, $date_due) ) {
				$response["error"] = false;
				$response["return"] = true;
			} else {
				$response["error"] = true;
				$response["error_detail"] = "Error editing debt.";
			}
		}
		// DEBT_RESOLVE
		// mark a debt as resolved
		elseif ( $action == "debt_resolve" ) {
			if ( debt_resolve($debt_id) ) {
				$response["error"] = false;
				$response["return"] = true;
			} else {
				$response["error"] = true;
				$response["error_detail"] = "Error resolving debt.";
			}
		}
		// DEBT_DETAILS
		// get details for one debt
		elseif ( $action == "debt_details" ) {
			if ( $details = debt_details($debt_id) ) {
				$response["error"] = false;
				$response["return"] = $details;
			} else {
				$response["error"] = true;
				$response["error_detail"] = "Error retrieving debt details.";
			}
		}
		// DEBT_REMOVE
		// remove a debt
		elseif ( $action == "debt_remove" ) {
			if ( debt_remove($debt_id) ) {
				$response["error"] = false;
				$response["return"] = true;
			} else {
				$response["error"] = true;
				$response["error_detail"] = "Error removing debt.";
			}
		}
		// EVENT_DETAILS
		// show the details of a single event
		elseif ( $action == "event_details" ) {
			if ( $event = event_details($event_id) ) {
				$response["error"] = false;
				$response["return"] = $action;
			} else {
				$response["error"] = true;
				$response["error_detail"] = "Error retrieving event details.";
			}
		}
		// HISTORY_DEBT_ALL
		// get all events for a single bill
		elseif ( $action == "history_debt_all" ) {
			if ( $events = history_debt_all($debt_id) )	{
				$response["error"] = false;
				$response["return"] = $events;
			} else {
				$response["error"] = true;
				$response["error_detail"] = "Error retrieving bill history.";
			}
		}	
		// HISTORY_USER_ALL
		// get all events for a single user
		elseif ( $action == "history_user_all" ) {
			if ( $events = history_user_all($debt_id) )	{
				$response["error"] = false;
				$response["return"] = $events;
			} else {
				$response["error"] = true;
				$response["error_detail"] = "Error retrieving bill history.";
			}
		}		
		// INVALID ACTION
		else {
			$response["error"] = true;
			$response["error_detail"] = "Invalid action.";
		}
		
		// close database connection
		global $db;
		$db->close();
	}
}

// compute total execution time
$time_end = get_time();
$time = $time_end - $time_start;
$response["execution_time"] = $time;

// --------------------
// SHOW SERVER RESPONSE
// --------------------

if ( $format == "JSON" ) {
	header("Content-type: application/json");
	echo json_encode($response);
}
elseif ( $format == "XMLRPC" ) {
	header("Content-type: text/xml");
	echo xmlrpc_encode($response);
}
else {
	header("Content-type: text/html");
	echo "Unknown format type. Showing response in cleartext.<br /><pre>";
	print_r($response);
	echo "</pre>";
}

?>
