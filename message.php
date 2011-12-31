<?php

// get details of a single message
function message_details($message_id,$user_id) {

	global $db;
	$statement = $db->prepare("SELECT message_id, user_id, from_id, viewed, date_created, date_expire, subject, body, topic "
								." FROM messages WHERE message_id = ?");
	$statement->bind_param("i",$message_id);
	
	if ( $statement->execute() ) {
		$m = array();
		$statement->bind_result($m["message_id"],$m["user_id"],$m["from_id"],$m["viewed"],
								$m["date_created"],$m["date_expire"],$m["subject"],$m["body"],$m["topic"]);
		$statement->fetch();
		$statement->close();
		
		// check for ownership
		if ( $user_id != $m["user_id"] && $user_id != $m["from_id"] ) return false;
		else return $m;
	}
	else return false;
	
}

// list all message_ids
function message_list($user_id) {
	
	global $db;
	
	$statement = $db->prepare("SELECT message_id FROM messages WHERE user_id = ? ORDER BY date_created desc");
	$statement->bind_param("i",$user_id);
	
	if ( $statement->execute() ) {
		$statement->bind_result($message_id);
		$messages = array();
		while ( $statement->fetch() ) {
			$messages[] = $message_id;
		}
		$statement->close();
		return $messages;
	}
	else return false;

}

// list all unread messages_ids
function message_list_unread($user_id) {
	
	global $db;
	$statement = $db->prepare("SELECT message_id FROM messages WHERE user_id = ? AND viewed = false ORDER BY date_created desc");
	$statement->bind_param("i",$user_id);

	if ( $statement->execute() ) {
		$statement->bind_result($message_id);
		$messages = array();
		while ( $statement->fetch() ) {
			$messages[] = $message_id;
		}
		$statement->close();
		return $messages;
	}
	else return false;
	
}

// list all sent message_ids
function message_list_sent($user_id) {
	
	global $db;
	$statement = $db->prepare("SELECT message_id FROM messages WHERE from_id = ? ORDER BY date_created desc");
	$statement->bind_param("i",$user_id);
	
	if ( $statement->execute() ) {
		$statement->bind_result($message_id);
		$messages = array();
		while ( $statement->fetch() ) {
			$messages[] = $message_id;
		}
		$statement->close();
		return $messages;
	}
	else return false;
	
}

// list all message details
function message_all($user_id) {
	
	// get all message_ids
	$ids = message_list($user_id);

	// get details for each message_id
	$messages = array();
	if ( $ids ) {
		foreach ( $ids as $id ) {
			$messages[] = message_details($id,$user_id);
		}
	}
	return $messages;
	
}

//get all unread message details
function message_all_unread($user_id) {

	// get all unread message_ids
	$ids = message_list_unread($user_id);
	// get details for each message_id
	$messages = array();
	if ( $ids ) {
		foreach ( $ids as $id ) {
			$messages[] = message_details($id,$user_id);
		}
	}
	return $messages;
	
}

// get all sent message details
function message_all_sent($user_id) {
	
	// get all sent message_ids
	$ids = message_list_sent($user_id);
	// get details for each message_id
	$messages = array();
	if ( $ids ) {
		foreach ( $ids as $id ) {
			$messages[] = message_details($id,$user_id);
		}
	}
	return $messages;
	
}

// mark a message as viewed
function message_viewed($message_id,$user_id) {

	// mark a message as viewed
	global $db;
	$statement = $db->prepare("UPDATE messages SET viewed = true WHERE message_id = ? AND user_id = ?");
	$statement->bind_param("ii",$message_id,$user_id);
	
	if ( $statement->execute() ) {
		$statement->close();
		return true;
	}
	else return false;
	
}

// create a new message
function message_create($user_id,$subject,$body,$from_id = 1,$date_expire = 0,$email = false,$topic = "MESSAGE") {

	// send a new message to $user_id	
	global $db;
	$statement = $db->prepare("INSERT INTO messages (user_id,from_id,date_created,date_expire,subject,body,topic) VALUES (?,?,?,?,?,?,?)");
	$statement->bind_param("iiiisss",$user_id,$from_id,time(),$date_expire,$subject,$body,$topic);
	
	if ( $statement->execute() ) {
		$message_id = $statement->insert_id;
		$statement->close();
		// send email, if requested
		if ( $email ) {
			$to_user = user_details($user_id);
			$to_email = $to_user["email"];
			if ( $to_email != "" ) email_send($to_email,$subject,$body);
		}
		return $message_id;		
	}
	else return false;
	
}
	

?>