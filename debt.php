<?php

include_once "user.php";

// -------------------------
// DEBT MANAGEMENT FUNCTIONS
// -------------------------

// DEBT_ADD
// add a new debt
function debt_add($borrower_id,$lender_id,$amount,$category,$details,$date,$paid,$date_due) {
	
	// check fields
	$paid = ($paid) ? 1 : 0;
	if($date_due == null) $date_due=0;
	$details = ($details) ? $details : "";	
	
	global $db;
	$statement = $db->prepare("INSERT INTO debts (borrower_id, lender_id, amount, category, details, date, paid, date_due) " .
			"VALUES(?, ?, ?, ?, ?, ?, ?, ?)");
	$statement->bind_param("iidssiii",$borrower_id,$lender_id,$amount,$category,$details,$date,$paid,$date_due);
	if ( $statement->execute() ) {
		
		$debt_id = $statement->insert_id;
		$statement->close();
		// write an event to the history log
		global $remote_user;
		event_put($debt_id,$remote_user,"CREATE","created new debt.");
		// if resolved, update history
		if ( $paid ) event_put($debt_id,$remote_user,"RESOLVE","marked debt as paid.");
		return $debt_id;
	}
	
	else return false;
	
}

// DEBT_EDIT
// edit details for a debt
function debt_edit($debt_id,$borrower_id,$lender_id,$amount,$category,$details,$date,$paid,$date_due) {
	
	// check fields
	$paid = ($paid) ? 1 : 0;
	if($date_due == null) $date_due=0;	
	
	// check for differences
	$mods = "";
	$debt = debt_details($debt_id);
	if ( $debt["borrower_id"] != $borrower_id ) $mods = string_put($mods,"borrower name");
	if ( $debt["lender_id"] != $lender_id ) $mods = string_put($mods,"lender name");
	if ( $debt["amount"] != $amount ) $mods = string_put($mods,"amount");
	if ( $debt["category"] != $category ) $mods = string_put($mods,"category");
	if ( $debt["details"] != $details ) $mods = string_put($mods,"details");
	if ( $debt["date"] != $date ) $mods = string_put($mods,"date");
	if ( $debt["date_due"] != $date_due ) $mods = string_put($mods,"due date");
	$mods = string_finish($mods);
	
	global $db;
	$statement = $db->prepare("UPDATE debts SET borrower_id = ?, lender_id = ?, amount = ?, category = ?, ".
								"details = ?, date = ?, paid = ?, date_due = ? WHERE debt_id = ?");
	$statement->bind_param("iidssiiii",$borrower_id,$lender_id,$amount,$category,$details,$date,$paid,$date_due,$debt_id);
	
	if ( $statement->execute() ) {
		$statement->close();
		// write an event to the history log
		global $remote_user;
		if ( $mods ) event_put($debt_id,$remote_user,"EDIT","modified debt $mods.");
		return true;
	}
	else return false;

}

// DEBT_RESOLVE
// mark a debt as paid
function debt_resolve($debt_id){
	
	global $db;
	$statement = $db->prepare("UPDATE debts SET paid = TRUE WHERE debt_id = ?");
	$statement->bind_param("i",$debt_id);
	
	if ( $statement->execute() ) {
		$statement->close();
		// write an event to the history log
		global $remote_user;
		event_put($debt_id,$remote_user,"RESOLVE","marked debt as paid.");
		return true;
	}
	else return true;

}

// DEBT_REMOVE
// delete a debt
function debt_remove($debt_id){
	
	global $db;
	$statement = $db->prepare("DELETE FROM debts WHERE debt_id = ?");
	$statement->bind_param("i",$debt_id);
	
	if ( $statement->execute() ) {
		$statement->close();
		// delete all history items for this debt
		$statement = $db->prepare("DELETE FROM history WHERE debt_id = ?");
		$statement->bind_param("i",$debt_id);
		if ( $statement->execute() ) {
			$statement->close();
		}
		return true;
	}
	else return false;

}

// DEBT_DETAILS
// get all details for a debt
function debt_details($debt_id) {
	
	global $db;
	$statement = $db->prepare("SELECT debt_id, borrower_id, lender_id, amount, category, details, date, date_due, paid FROM debts WHERE debt_id = ?");
	$statement->bind_param("i",$debt_id);
	
	if ( $statement->execute() ) {
		$u = array();
		$statement->bind_result($u["debt_id"],$u["borrower_id"],$u["lender_id"],$u["amount"],$u["category"],$u["details"],$u["date"],$u["date_due"],$u["paid"]);
		$statement->fetch();
		$statement->close();
		return $u;
	}
	else return false;

}

// DEBT_LIST
// get an array of all debt ids
function debt_list($user_id) {
	
	global $db;
	$statement = $db->prepare("SELECT debt_id FROM debts WHERE borrower_id = ? OR lender_id = ? ORDER BY paid ASC, date DESC");
	$statement->bind_param("ii",$user_id,$user_id);
	
	if ( $statement->execute() ) {
		$statement->bind_result($id);
		// build array of debt_ids
		$debts = array();
		while ( $statement->fetch() ) {
			$debts[] = $id;
		}
		$statement->close();
		return $debts;
	}
	else return false;
	
}

// DEBT_ALL
// get an array of all debts with details
function debt_all2($user_id) {
	
	// this is not the most efficient want to do it, but with table indexing it should be pretty quick
	$all_debts = array();
	$list = debt_list($user_id);
	foreach ( $list as $debt_id ) {
		$debt = debt_details($debt_id);
		$borrower = user_details($debt["borrower_id"]);
		$lender = user_details($debt["lender_id"]);
		$debt["borrower_name"] = $borrower["fname"]." ".$borrower["lname"];
		$debt["lender_name"] = $lender["fname"]." ".$lender["lname"];
		$all_debts[] = $debt;
	}
	return $all_debts;

}
// DEBT_ALL
// get an array of all debts with details

function debt_all($user_id) {
	
	global $db;
			
	$statement = $db->prepare(
			"SELECT D.debt_id, D.borrower_id, D.lender_id, D.amount, D.category, D.details, D.date, D.date_due, D.paid, " .
				"B.fname as bfname, B.lname as blname, L.fname as lfname, L.lname as llname " .
			"FROM debts D " .
			"LEFT OUTER JOIN users B ON B.user_id = D.borrower_id " .
			"LEFT OUTER JOIN users L ON L.user_id = D.lender_id " .
			"WHERE (D.borrower_id = ? OR D.lender_id = ? )" .
			"ORDER BY D.paid ASC, D.date DESC");
			
	$statement->bind_param("ii",$user_id,$user_id);
	
	$all_debts = array();
	if( $statement->execute() ){
		$debt = array();
		
		$statement->bind_result($debt["debt_id"], $debt["borrower_id"], $debt["lender_id"], $debt["amount"], 
		$debt["category"], $debt["details"], $debt["date"], $debt["date_due"], $debt["paid"],
		$debt["bfname"], $debt["blname"], $debt["lfname"], $debt["llname"]);
		
		while( $statement->fetch() ){
			$final_debt["borrower_name"] = $debt["bfname"]." ".$debt["blname"];
			$final_debt["lender_name"] = $debt["lfname"]." ".$debt["llname"];
			$final_debt["debt_id"] = $debt["debt_id"];
			$final_debt["borrower_id"] = $debt["borrower_id"];
			$final_debt["lender_id"] = $debt["lender_id"];
			$final_debt["amount"] = $debt["amount"];
			$final_debt["category"] = $debt["category"];
			$final_debt["details"] = $debt["details"];
			$final_debt["date"] = $debt["date"];
			$final_debt["date_due"] = $debt["date_due"];
			$final_debt["paid"] = $debt["paid"];
			$all_debts[] = $final_debt;
		}
		$statement->close();
		
	}
	return $all_debts;

}

?>