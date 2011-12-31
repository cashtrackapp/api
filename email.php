<?php 

// -------------
// EMAIL SENDING
// -------------

// EMAIL_SEND
// send an email message
function email_send($to,$subject,$body) 
{
	$headers = "From: test@test.com\r\n"."X-Mailer: php";
	$subject = "CashTrack App - ".$subject;
	$body = "This is an automated message. Please do not reply.\n\n".$body;
	$body .= "\n\nThanks,\nThe CashTrack App Development Team\nhttp://www.cashtrackapp.com";
	$body .= "\n\nThanks for your continued patronage! Questions, comments, or suggestions can be directed to help@cashtrackapp.com.";
	return mail($to,$subject,$body,$headers);
	return true;
}

// ----------------------------------
// EMAIL ADDRESS MANAGEMENT FUNCTIONS
// ----------------------------------

// EMAIL_SET
// set a user's email address
function email_set($user_id,$email) {

	// get old email address
	global $db;
	$statement = $db->prepare("SELECT email FROM users WHERE user_id = ?");
	$statement->bind_param("i",$user_id);
	
	if ( $statement->execute() ) {
		$statement->bind_result($old_email);
		$statement->fetch();
		$statement->close();
		
		// compare addresses
		if ( $email != $old_email ) {
			if ( $email != "" ) {
				// check validity
				if ( !valid_email($email) ) return false;
				// send confirmation code if remote_user is actual user
				global $remote_user;
				if ( $remote_user == $user_id ) {
					email_verify($user_id,$email);
				}
			}
			// insert new address
			$statement = $db->prepare("UPDATE users SET email = ?, email_valid = FALSE WHERE user_id = ?");
			$statement->bind_param("si",$email,$user_id);
			if ( $statement->execute() ) {
				$statement->close();
				return true;
			}
			else return false;
		}
		// no changes needed
		else return true;
	}
	else return false;
	
}

// EMAIL_VERIFY
// create an email confirmation code and send it
function email_verify($user_id,$email) {
	
	// delete all previous keys for this user
	global $db;
	$statement = $db->prepare("DELETE FROM confirmation WHERE user_id = ?");
	$statement->bind_param("i",$user_id);
	$statement->execute();
	$statement->close();
	
	// generate a new confirmation code (loop if key is a duplicate)
	$statement = $db->prepare("INSERT INTO confirmation (code,user_id,date_expire) VALUES (?,?,?)");
	$new_code = "";
	do {
		$new_code = random_string(CONFIRM_LENGTH);
		$statement->reset();
		$expire = time()+CONFIRM_EXPIRE;		
		$statement->bind_param("sii",$new_code,$user_id,$expire);
	} while ( !$statement->execute() );
	
	// send code to email address
	$user = user_details($user_id);
	$subject = "Email Address Verification";
	$content = 	$user["fname"].",\n\n".
				"We've received a request to change your email address. ".
				"Before we can make this change, we need to verify that the address is actually yours.\n\n".
				"Here's your confirmation code:\n$new_code\n\n".
				"To verify your new address, simply visit your account page at the CashTrack App website and enter this code.";
	email_send($email,$subject,$content); 
	
}

// EMAIL_CONFIRM
// use a confirmation code to mark an email address as valid
function email_confirm($user_id,$code) {

	global $db;
	$statement = $db->prepare("SELECT date_expire FROM confirmation WHERE code = ?");
	$statement->bind_param("s",$code);
	if ( $statement->execute() ) {
		$success = false;
		$statement->bind_result($date_expire);
		$statement->fetch();
		$statement->close();
		// check code expiration date
		if ( time() < $date_expire ) {
			// mark email as valid
			$statement = $db->prepare("UPDATE users SET email_valid = true WHERE user_id = ?");
			$statement->bind_param("i",$user_id);
			$statement->execute();
			$statement->close();
			$success = true;
		}
		// delete code
		$statement = $db->prepare("DELETE FROM confirmation WHERE code = ? AND user_id = ?");
		$statement->bind_param("si",$code,$user_id);
		$statement->execute();
		$statement->close();
		return $success;
	}	
	else return false;
	
}

// -------------------------------
// EMAIL CANNED RESPONSE FUNCTIONS
// -------------------------------

// EMAIL_WELCOME
// send a welcome email
function email_welcome($user_id,$password,$email) {

	$user = user_details($user_id);
	$subject = "Welcome!";
	$content = 	$user["fname"].",\n\n".
				"Welcome to the CashTrack App community!\n".
				"Your account has been created and you can begin using the application right now.\n\n".
				"Here's your account information:\n".
				"Username: ".$user["username"]."\n".
				"Password: $password \n\n".
				"If you have any questions, drop us an email at help@cashtrackapp.com.";
	message_create($user_id,$subject,$content,1,0,true,"WELCOME");
	email_send($email,$subject,$content); 
	
}

// EMAIL_INVITE
// send an invite email
function email_invite($from_id,$user_id,$password,$email) {

	$user = user_details($user_id);
	$from = user_details($from_id);
	$subject = "Invitation to join from ".$from["fname"]." ".$from["lname"];
	$content = 	$user["fname"].",\n\n".
				"You have been invited to join the CashTrack community by your friend ".$from["fname"].".\n\n".
				"To claim your account, follow the link below:\n".
				"LINK\n\n".
				"Not interested? Just ignore this email and we won't bother you again.\n\n".
				"If you have any questions, drop us an email at help@cashtrackapp.com.";
	email_send($email,$subject,$content); 

}

// EMAIL_CANCEL
// send an email to confirm account close
function email_cancel($user_id) {
	
	$user = user_details($user_id);
	$subject = "Account Closed";
	$content = 	$user["fname"].",\n\n".
				"We're sorry to see you go./n/n".
				"Your account has been closed, effective immediately.\n".
				"Please note that this change is permanent and cannot be undone.\n\n".
				"If you have any questions, drop us an email at help@cashtrackapp.com.";
	email_send($user["email"],$subject,$content);
	
}

// EMAIL_FRIEND_CONFIRM
function email_friend_confirm($from_id,$to_id) {
	
	$from = user_details($from_id);
	$to = user_details($to_id);
	$subject = $from["fname"]." ".$from["lname"]." has sent you a friend request";
	$content = $to["fname"].",\n\n".
			   $from["fname"]." ".$from["lname"]." has sent you a friend request.\n\n".
			   "Please login to your account to either confirm or ignore the request.";
	message_create($to_id,$subject,$content,$from_id,0,true,"FRIEND");
	//email_send($to["email"],$subject,$content);
	
}

// MESSAGE_HISTORY_EVENT_CREATE
function message_history_event_create($actor_id,$debt_id,$event,$details) {
	global $db;
	// get debt details
	$debt = array();
	$statement = $db->prepare("SELECT debt_id, borrower_id, lender_id, amount, category, details, date, date_due, paid FROM debts WHERE debt_id = ?");
	$statement->bind_param("i",$debt_id);
	if ( $statement->execute() ) {
		$u = array();
		$statement->bind_result($u["debt_id"],$u["borrower_id"],$u["lender_id"],$u["amount"],$u["category"],$u["details"],$u["date"],$u["date_due"],$u["paid"]);
		$statement->fetch();
		$statement->close();
		$debt = $u;
	}
	// figure out who to send to
	$to_id = 0;
	if ( $actor_id == $debt["borrower_id"] ) $to_id = $debt["lender_id"];
	else $to_id = $debt["borrower_id"];
	// get user details
	$to = user_details($to_id);
	$actor = user_details($actor_id);
	// draft message
	$subject = $content = "";
	if ( $event == "CREATE" ) {
		// set subject
		if ( $to_id == $debt["borrower_id"] ) $subject = $actor["fname"]." says you owe $".$debt["amount"]." for ".$debt["category"];
		else $subject = $actor["fname"]." says that they owe you $".$debt["amount"]." for ".$debt["category"];
		// set content
		$content = $to["fname"].",\n\n";
		$content .= "Your friend ".$actor["fname"]." ".$actor["lname"];
		if ( $to_id == $debt["borrower_id"] ) $content .= " says you owe ";
		else $content .= " says that they owe you ";
		$content .= "$".$debt["amount"]." for ".$debt["category"]." (".$debt["details"].").\n\n";
		$content .= "Please login to your account for more details or to update this debt.";
	}
	if ( $event == "EDIT" ) {
		// set subject
		$subject = $actor["fname"]." has edited the debt details for ".$debt["category"];
		// set content
		$content = $to["fname"].",\n\n".
				   "Your friend ".$actor["fname"]." ".$actor["lname"]." has edited the details of ".$debt["category"]." (".$debt["details"].")\n\n".
				   "Please login to your account for more details or to update this debt.";
	}
	if ( $event == "RESOLVE" ) {
		// set subject
		$subject = $actor["fname"]." has marked the debt for ".$debt["category"]." as paid";
		// set content
		$content = $to["fname"].",\n\n";
		$content .= "Your friend ".$actor["fname"]." ".$actor["lname"]." says that ";
		if ( $to_id == $debt["borrower_id"] ) $content .= " you have paid them in full ($".$debt["amount"].") ";
		else $content .= " they have paid you in full ($".$debt["amount"].") ";
		$content .= "for ".$debt["category"]." (".$debt["details"]."). This means that the debt has been resolved and is now closed.\n\n";
		$content .= "Please login to your account for more details or to update this debt.";
	}
	// send message
	message_create($to_id,$subject,$content,$actor_id,0,true,"HISTORY");
}


?>