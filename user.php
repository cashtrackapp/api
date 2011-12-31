<?php

// -------------------------
// USER MANAGEMENT FUNCTIONS
// -------------------------

// LOGIN
// start a new login session with cleartext password
function login($username,$password)
{
	$user_id = authenticate($username,$password,"CLEAR");
	if ( $user_id ) return generate_key($user_id);
	else return false;
}

// LOGIN_SECURE
// start a new login session with MD5 encrypted password
function login_secure($username,$password)
{
	$user_id = authenticate($username,$password,"MD5");
	if ( $user_id ) return generate_key($user_id);
	else return false;
}

// USER_CREATE
// create a new 'real' user
function user_create($username,$password,$fname,$lname,$email,$phone)
{
	// check for required fields
	if ( !$username or !$password or !$fname or !$lname ) return false;
	
	// clean up user data
	$verified = true;
	$email = ($email) ? $email : "";
	$phone = phone_clean($phone);
	$password_clear = $password;
	$password = crypt_pass($password);
	$password_type = "MD5";
	
	// insert new user entry
	global $db;
	$statement = $db->prepare("INSERT INTO users (username, password, fname, lname, phone, verified, created, password_type) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
	$statement->bind_param("sssssiis",$username,$password,$fname,$lname,$phone,$verified,time(),$password_type);
	
	if ( $statement->execute() ) {
		$id = $statement->insert_id;
		$statement->close();
		// send confirmation email
		email_welcome($id,$password_clear,$email);
		// set user's email address
		email_set($id,$email);
		return $id;
	}
	else return false;

}

// USER_CREATE_SPECIAL
// create a new 'owned' user
function user_create_special($owner_id,$fname,$lname,$email,$phone)
{
	// check for required fields
	if ( !$fname or !$lname ) return false;
	
	// clean up user data
	$verified = 0;
	$email = ($email) ? $email : "";
	$phone = phone_clean($phone);
	
	// insert new user entry
	global $db;
	$statement = $db->prepare("INSERT INTO users (owner_id,fname,lname,email,phone,created,verified) VALUES (?,?,?,?,?,?,?)");
	$statement->bind_param("issssi",$owner_id,$fname,$lname,$email,$phone,time(),$verified);
	
	if ( $statement->execute() ) {
		$id = $statement->insert_id; 
		$statement->close();
		// send confirmation email
		email_invite($owner_id,$id,$password,$email);
		return $id;
	}
	else return false;
}

// USER_UPDATE
// update user details
function user_update($user_id,$username,$pass,$new_pass,$fname,$lname,$email,$phone,$photo="")
{
	// get user details
	$user = user_details($user_id);
	
	// check for user validity
	global $remote_user;
	if ( $remote_user != $user_id and $remote_user != $user["owner_id"] ) return 1;

	// set new user details
	global $db;
	
	$statement = null;
	$pass_type = "MD5";
	if ( strlen($new_pass) < 1) {
		// no password update
		$statement = $db->prepare("UPDATE users SET fname = ?, lname = ?, phone = ?, password_type = ?, photo = ? WHERE user_id = ?");
		$statement->bind_param("sssssi",$fname,$lname,$phone,$pass_type,$photo,$user_id);
	}
	else {
		// encrypt password
		$new_pass = crypt_pass($new_pass);
		// update password
		$statement = $db->prepare("UPDATE users SET password = ?, fname = ?, lname = ?, phone = ?, password_type = ?, photo = ? WHERE user_id = ?");
		$statement->bind_param("ssssssi",$new_pass,$fname,$lname,$phone,$pass_type,$photo,$user_id);
	} 
		
	if ( $statement->execute() ) {
		$statement->close();
		// set user's email address
		email_set($user_id,$email);
		return 0;
	} 
	else return 2;

}

// USER_DETAILS
// get all details for a user
function user_details($user_id)
{
	// get user details
	global $db;
	$statement = $db->prepare("SELECT user_id, username, fname, lname, email, phone, photo, owner_id, email_valid FROM users WHERE user_id = ?");
	$statement->bind_param("i",$user_id);
	
	if ( $statement->execute() ) {
		$d = array();
		$statement->bind_result($d["user_id"],$d["username"],$d["fname"],$d["lname"],$d["email"],$d["phone"],$d["photo"],$d["owner_id"],$d["email_valid"]);
		$statement->fetch();
		$statement->close();
		return $d;
	}
	else return false;
	
}

// USER_SEARCH
// search for an existing user, or create a new one if needed
function user_search($term)
{
	// prepare search term
	$term = "%".$term."%";
	
	// search for users that match the term
	global $db;
	$statement = $db->prepare("SELECT user_id FROM users WHERE username LIKE ? OR fname LIKE ? OR lname LIKE ? OR email LIKE ? OR phone LIKE ?");
	$statement->bind_param("sssss",$term,$term,$term,$term,$term);
	
	if ( $statement->execute() ) {
		$list = array();
		$statement->bind_result($user_id);
		// retrieve ids for each found user
		while ( $statement->fetch() ) {
			$list[] = $user_id;
		}
		$statement->close();
		// retrieve details for each user
		$results = array();
		foreach ( $list as $user ) {
			$results[] = user_details($user);
		}
		return $results;
	}
	else return false;
	
}

// USER_SEARCH_FIELDS
// allow searhing of specific fields
function user_search_fields($username,$email,$phone,$name)
{
	// don't allow all blank search
	if ( !$username and !$email and !$phone and !$name ) return false;
	
	// prepare search terms
	$username = "%".$username."%";
	$email = "%".$email."%";
	$phone = "%".$phone."%";
	$name = "%".$name."%";
	
	global $db;
	$statement = $db->prepare("SELECT user_id FROM search WHERE username LIKE ? AND email LIKE ? AND phone LIKE ? AND name LIKE ?");
	$statement->bind_param("ssss",$username,$email,$phone,$name);
	
	if ( $statement->execute() ) {
		$list = array();
		$statement->bind_result($user_id);
		// retrieve ids for each found user
		while ( $statement->fetch() ) {
			$list[] = $user_id;
		}
		$statement->close();
		// retrieve details for each user
		$results = array();
		foreach ( $list as $user ) {
			$results[] = user_details($user);
		}
		return $results;
	}
	else return false;
	
}

// ---------------------------
// FRIEND MANAGEMENT FUNCTIONS
// ---------------------------

// FRIEND_ADD
// add a friend relationship
function friend_add($u1,$u2)
{
	// check for self friending
	if ( $u1 == $u2 ) return 3;
	
	// check for an existing relationship
	global $db;
	$statement = $db->prepare("SELECT * FROM friends WHERE (friend1 = ? AND friend2 = ?) OR (friend1 = ? AND friend2 = ?)");
	$statement->bind_param("iiii",$u2,$u1,$u1,$u2);
	
	if ( $statement->execute() ) {
		$statement->store_result(); 
		if ( $statement->num_rows > 0 ) {
			// relationship already exists
			$statement->close();
			return 1;
		}
	}
	$statement->close();
	
	// insert a new relationship
	$time = time();
	$statement = $db->prepare("INSERT INTO friends (friend1, friend2, confirmed, timestamp) VALUES (?, ?, 1, ?)");
	$statement->bind_param("iii",$u1,$u2,$time);
	
	if ( $statement->execute() ) {
		$statement->close();
		// send confirmation email
		email_friend_confirm($u1,$u2);
		return 0;
	} 
	else {
		$statement->close();
		return 2;
	}

}

// FRIEND_CONFIRM
// confirm a friend relationship
function friend_confirm($u1,$u2)
{
	global $db;
	
	$statement = $db->prepare("UPDATE friends SET confirmed = 2 WHERE friend1 = ? AND friend2 = ?");
	$statement->bind_param("ii",$u2,$u1);
	
	if ( $statement->execute() ) {
		$statement->close();
		return true;
	}
	else return false;

}

// FRIEND_REMOVE
// remove a friend
function friend_remove($u1,$u2)
{
	global $db;
	
	$statement = $db->prepare("DELETE FROM friends WHERE (friend1 = ? AND friend2 = ?) OR (friend1 = ? AND friend2 = ?)");
	$statement->bind_param("iiii",$u1,$u2,$u2,$u1);
	
	if ( $statement->execute() ) {
		$statement->close();
		return true;
	}
	else return false;

}

// FRIEND_LIST
// list all of a user's friends (with details)
function friend_list($owner_id)
{
	global $db;
	
	$statement = $db->prepare("SELECT fname, lname, friend1, friend2, confirmed, timestamp FROM users, friends WHERE (user_id = friend1 AND friend2 = ?) OR (user_id = friend2 AND friend1 = ?)");
	$statement->bind_param("ii",$owner_id,$owner_id);

	if ( $statement->execute() ) {
		$statement->bind_result($fname,$lname,$friend1,$friend2,$confirmed,$timestamp);
		// set information for each user
		$list = array();
		while ( $statement->fetch() ) {
			$friendship = array();
			$friendship["timestamp"] = $timestamp;
			// set friend information
			$friendship["fname"] = $fname;
			$friendship["lname"] = $lname;
			// set friend_id
			if ( $owner_id == $friend1 )  $friendship["user_id"] = $friend2;
			else $friendship["user_id"] = $friend1;
			// set confirmation status
			if ( $confirmed == 0 ) {
				if ( $owner_id == $friend1 ) $friendship["confirmed"] = 0;
				else $friendship["confirmed"] = 1;
			}
			elseif ( $confirmed == 1 ) {
				if ( $owner_id == $friend2 ) $friendship["confirmed"] = 0;
				else $friendship["confirmed"] = 1;
			}
			else $friendship["confirmed"] = 2;

			$list[] = $friendship;
		}
		$statement->close();
		
		return $list;
	}
	else return false;
	
}

// FRIEND_CHECK
// check friend relationship status
function friend_check($user_id,$friend_id) 
{
	// 0,1 - needs confirmation, 2-confirmed, 3-deleted, 4-not friends
	$status = 4;
	
	global $db;
	$statement = $db->prepare("SELECT confirmed FROM friends WHERE (friend1 = ? AND friend2 = ?) OR (friend1 = ? AND friend2 = ?)");
	$statement->bind_param("iiii",$user_id,$friend_id,$friend_id,$user_id);
	if ( $statement->execute() ) {
		$statement->bind_result($confirmed);
		if ( $statement->fetch() ) {
			$status = $confirmed;
		}
	}
	$statement->close();
	return $status;
}

?>