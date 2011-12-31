<?php 

// check remote ip address against blacklist
function blacklist_check($ip) 
{
	// blacklist an IP or network
	$blacklist = array();
	
	foreach( $blacklist as $block ) {
		// check for specific ip address
		if ( $block == $ip ) return true;
		// check for a network
		if ( substr_count($ip,$block,0) > 0 ) return true;
	}
	
	return false;
}

// get current server time as a float
function get_time()
{
	list($usec, $sec) = explode(" ", microtime());
    return ((float)$usec + (float)$sec);
}

?>