<?php

// -----------------------
// DEBT HISTORY MANAGEMENT
// -----------------------

// HISTORY_BILL_PUT
// enter a new history item for a bill
function event_put($debt_id,$user_id,$event,$details) {
	
	global $db;
	$statement = $db->prepare("INSERT INTO history (debt_id,user_id,event,details,timestamp) VALUES (?,?,?,?,?)");
	$statement->bind_param("iissi",$debt_id,$user_id,$event,$details,time());
	
	if ( $statement->execute() ) {
		$statement->close();
		// create a message
		message_history_event_create($user_id,$debt_id,$event,$details);
		return true;
	}
	else return false;
	
}

// EVENT_DETAILS
// get details for a single debt history event
function event_details($event_id) {
	
	global $db;
	$statement = $db->prepare("SELECT event_id, debt_id, user_id, event, details, timestamp FROM history WHERE event_id = ?");
	$statement->bind_param("i",$event_id);
	
	if ( $statement->execute() ) {
		$e = array();
		$statement->bind_result($e["event_id"],$e["debt_id"],$e["user_id"],$e["event"],$e["details"],$e["timestamp"]);
		$statement->fetch();
		$statement->close();		
		// build return data
		$details = array();
		$details["event_id"] = $e["event_id"];
		$details["debt_id"] = $e["debt_id"];
		$details["user_id"] = $e["user_id"];
		$details["event"] = $e["event"];
		$details["timestamp"] = $e["timestamp"];
		global $remote_user;
		if ( $e["user_id"] == $remote_user ) $details["string"] = "You ".$e["details"];
		else {
			$user = user_details($e["user_id"]);
			$details["string"] = $user["fname"]." ".$e["details"];
		}
		return $details;
	}
	else return false;
	
}

// HISTORY_DEBT_ALL
// show details for all history items for a single bill
function history_debt_all($debt_id) {
	
	global $db;
	$statement = $db->prepare("SELECT event_id FROM history WHERE debt_id = ?");
	$statement->bind_param("i",$debt_id);
	
	if ( $statement->execute() ) {
		// get all matching event_ids
		$statement->bind_result($event_id);
		$ids = array();
		while ( $statement->fetch() ) {
			$ids[] = $event_id;
		}
		$statement->close();
		// get details for each event_id
		$events = array();
		foreach ( $ids as $event_id ) {
			$events[] = event_details($event_id);
		}
		return $events;
	}
	else return false;
	
}

// HISTORY_USER_ALL
// show details for all history items for all bills for a single user
function history_user_all($user_id) {
	
	global $db;
	$statement = $db->prepare("SELECT event_id FROM history WHERE debt_id IN (SELECT debt_id FROM debts WHERE (borrower_id = ? OR lender_id = ?))");
	$statement->bind_param("ii",$user_id,$user_id);
	
	if ( $statement->execute() ) {
		// get all matching event_ids
		$statement->bind_result($event_id);
		$ids = array();
		while ( $statement->fetch() ) {
			$ids[] = $event_id;
		}
		$statement->close();
		// get details for each event_id
		$events = array();
		foreach ( $ids as $event_id ) {
			$events[] = event_details($event_id);
		}
		return $events;
	}
	else return false;
	
}

?>