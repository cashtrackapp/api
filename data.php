<?php

// load sample data into database
function loadDB()
{
	
	global $remote_user;
	
	// ----------------
	// LOAD SAMPLE DATA
	// ----------------
	
	// TEST USERS
	$password = "123456";
	$phone = "5555555555";
	$user_ids = array();
	$user_ids["abe"] = user_create("abe",$password,"Abe","Lincoln","test@test.com",$phone);
	$user_ids["ben"] = user_create("ben",$password,"Ben","Finch","test@test.com",$phone);
	$user_ids["carla"] = user_create("carla",$password,"Carla","Robinson","test@test.com",$phone);
	$user_ids["denna"] = user_create("denna",$password,"Denna","Morrison","test@test.com",$phone);
	$user_ids["fenrir"] = user_create("fenrir",$password,"Fenrir","Wolfy","test@test.com",$phone);
	$user_ids["george"] = user_create("george",$password,"George","Muffinhead","test@test.com",$phone);
	$user_ids["hugh"] = user_create("hugh",$password,"Hugh","Statty","test@test.com",$phone);
	
	// TEST FRIENDS
	$remote_user = $user_ids["abe"];
	friend_add($user_ids["abe"],$user_ids["carla"]);
	friend_add($user_ids["abe"],$user_ids["denna"]);
	friend_add($user_ids["abe"],$user_ids["fenrir"]);
	friend_add($user_ids["abe"],$user_ids["george"]);
	friend_add($user_ids["abe"],$user_ids["ben"]);
	$remote_user = $user_ids["ben"];
	friend_add($user_ids["ben"],$user_ids["carla"]);
	friend_add($user_ids["ben"],$user_ids["denna"]);
	friend_add($user_ids["ben"],$user_ids["fenrir"]);
	friend_add($user_ids["ben"],$user_ids["george"]);
	
	
	// TEST DEBTS
	$time = time();
	$remote_user = $user_ids["abe"];
	debt_add($user_ids["ben"],$user_ids["abe"],20,"Restaurant","jimmy johns",$time,false,0);
	debt_add($user_ids["ben"],$user_ids["abe"],600,"Rent","january",$time,false,0);
	debt_add($user_ids["ben"],$user_ids["abe"],35,"Shopping","cupcakes",$time,false,0);
	debt_add($user_ids["george"],$user_ids["abe"],75,"Shopping","kohls",$time,false,0);
	debt_add($user_ids["fenrir"],$user_ids["abe"],45,"Travel","bag",$time,false,0);
	$remote_user = $user_ids["ben"];
	debt_add($user_ids["abe"],$user_ids["ben"],350,"Other","junk stuff",$time,false,0);
	debt_add($user_ids["abe"],$user_ids["ben"],2000,"Rent","big money",$time,false,0);
	debt_add($user_ids["denna"],$user_ids["ben"],750,"Rent","june",$time,false,0);
	$remote_user = $user_ids["denna"];	
	debt_add($user_ids["ben"],$user_ids["denna"],45,"Restaurant","bar",$time,false,0);
	$remote_user = $user_ids["george"];	
	debt_add($user_ids["abe"],$user_ids["george"],235,"Other","pretzels",$time,false,0);
	
}

// copy all data from production database to dev database
// this should be run right before taking the new service live
function migrateDB() {
	
	// only runs in dev mode
	if (RUN_MODE != 0) return false;
	
	// setup database connections
	global $db_user;
	global $db_password;
	global $db_database;
	global $db_server;
	$prod_db = new mysqli($db_server,$db_user,$db_password,"cashtrack_prod");
	$dev_db = new mysqli($db_server,$db_user,$db_password,$db_database);
	
	// COPY USERS
	if ($result = $prod_db->query("SELECT * FROM users")) {
		// get each user
		while ( $row = $result->fetch_assoc() ) {
			
			$user_id = $row["user_id"];
			$username = $row["username"];
			$password_type = "MD5";
			$password = crypt_pass($row["password"]);
			$fname = $row["fname"];
			$lname = $row["lname"];
			$verified = $row["verified"];
			$email = $row["email"];
			$email_valid = $row["email_valid"];
			$email_notify = 0;
			$phone = ($row["phone"]) ? $row["phone"] : "";
			$phone_valid = 0;
			$phone_notify = 0;
			$created = time();
			
			// insert into dev database
			$sql = "INSERT INTO users(user_id,username,password_type,password,fname,lname,verified,email,email_valid,email_notify,phone,phone_valid,phone_notify,created) ".
				"VALUES($user_id,'$username','$password_type','$password','$fname','$lname',$verified,'$email',$email_valid,$email_notify,'$phone',$phone_valid,$phone_notify,$created)";
			echo "SQL: ".$sql."<br />";
			if ( !$dev_db->query($sql) ) {
				echo "SQL Error: ".$dev_db->error."<br />";
				return false;
			}
		}
		$result->close();		
	}
	else {
		echo "SQL Error: ".$prod_db->error."<br />";
		return false;
	}
	
	// COPY FRIENDS
	if ($result = $prod_db->query("SELECT * FROM friends")) {
		// get each friend relationship
		while ( $row = $result->fetch_assoc() ) {
			
			$friend1 = $row["owner_id"];
			$friend2 = $row["friend_id"];
			$confirmed = 2;
			
			// insert into dev database
			$sql = "INSERT INTO friends(friend1,friend2,confirmed) VALUES($friend1,$friend2,$confirmed)";
			echo "SQL: ".$sql."<br />";
			if ( !$dev_db->query($sql) ) {
				echo "SQL Error: ".$dev_db->error."<br />";
			}			
		}
		$result->close();		
	}
	else {
		echo "SQL Error: ".$prod_db->error."<br />";
		return false;
	}
	
	// COPY DEBTS
	if ($result = $prod_db->query("SELECT * FROM debts")) {
		// get each debt
		while ( $row = $result->fetch_assoc() ) {
			
			$debt_id = $row["debt_id"];
			$borrower_id = $row["borrower_id"];
			$lender_id = $row["lender_id"];
			$amount = $row["amount"];
			$category = $row["category"];
			$details = ($row["details"]) ? $row["details"] : "";
			$date = $row["date"];
			$date_due = ($row["date_due"]) ? $row["date_due"] : 0;
			$paid = $row["paid"];
			
			// insert into dev database
			$sql = "INSERT INTO debts(debt_id,borrower_id,lender_id,amount,category,details,date,date_due,paid) ".
				"VALUES($debt_id,$borrower_id,$lender_id,$amount,'$category','$details',$date,$date_due,$paid)";			
			echo "SQL: ".$sql."<br />";
			if ( !$dev_db->query($sql) ) {
				echo "SQL Error: ".$dev_db->error."<br />";
			}
			
		}
		$result->close();
	}
	else {
		echo "SQL Error: ".$prod_db->error."<br />";
		return false;
	}
	
	// sessions, history, messages, announcements, and confirmation tables are not copied!
	
	//cleanup excess users
	$sql = "DELETE FROM users WHERE verified = false AND user_id NOT IN (SELECT friend1 AS user_id FROM friends) ".
													 "AND user_id NOT IN (SELECT friend2 AS user_id FROM friends) ".
													 "AND user_id NOT IN (SELECT lender_id AS user_id FROM debts) ".
													 "AND user_id NOT IN (SELECT borrower_id AS user_id FROM debts)";
	echo "SQL: ".$sql."<br />";
	if ( !$dev_db->query($sql) ) {
		echo "SQL Error: ".$dev_db->error."<br />";
	}
	echo "Affected Rows: ".$dev_db->affected_rows."<br />";
	
	return true;
}

?>