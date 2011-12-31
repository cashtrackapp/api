<?php 

// ------------------------
// CHECK SERVER FOR UPDATES
// ------------------------

// UPDATE CHECK
// check database for new messages, friends, and debts
function update_check($user_id,$timestamp) {
	global $db;
	
	$result = array();
	$result["new_friend"] = false;
	$result["new_debt"] = false;
	$result["new_message"] = false;
	
	// check for any unconfirmed friends since timestamp
	$statement = $db->prepare("SELECT timestamp FROM friends WHERE friend2 = ? AND confirmed = 1 AND timestamp > ?");
	$statement->bind_param("ii",$user_id,$timestamp);
	if ( $statement->execute() ) {
		$statement->store_result();
		if ( $statement->num_rows > 0 ) $result["new_friend"] = true;
	}
	$statement->close();
	
	// check for any debts created since timestamp
	$statement = $db->prepare("SELECT debt_id FROM debts WHERE (borrower_id = ? OR lender_id = ?) AND paid = false AND date > ?");
	$statement->bind_param("iii",$user_id,$user_id,$timestamp);
	if ( $statement->execute() ) {
		$statement->store_result();
		if ( $statement->num_rows > 0 ) $result["new_debt"] = true;
	}
	$statement->close();
	
	// check for any unread messages since timestamp
	$statement = $db->prepare("SELECT message_id FROM messages WHERE user_id = ? AND viewed = false AND date_created > ?");
	$statement->bind_param("ii",$user_id,$timestamp);
	if ( $statement->execute() ) {
		$statement->store_result();
		if ( $statement->num_rows > 0 ) $result["new_message"] = true;
	}
	$statement->close();
	
	return $result;
}



?>