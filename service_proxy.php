<?php

// A simple stand-alone proxy service for the cashtrack API based on Curl.

$api_url = "http://old.api.cashtrackapp.com/index.php";
$params = $_REQUEST;

// prepare error response
$response	= array();
$response["error"] = true;

// parameterize data
$post_data = "format=JSON";
$keys = array_keys($params);
foreach($keys as $key) {
	$post_data .= "&".$key."=".urlencode($params[$key]);
}

// prepare curl session
$ch = curl_init();
curl_setopt($ch,CURLOPT_URL,$api_url);
curl_setopt($ch,CURLOPT_CONNECTTIMEOUT,5);
curl_setopt($ch,CURLOPT_RETURNTRANSFER,1);
curl_setopt($ch,CURLOPT_POST,1);
curl_setopt($ch,CURLOPT_POSTFIELDS,$post_data);
// execute curl request
$output = curl_exec($ch);
// check for curl errors
if ( !$output ) {
	$response["error_detail"] = "API Forward Service: CURL error. ".curl_error($ch);
	echo json_encode($response);
	exit(1);
}
// end curl session
curl_close($ch);

// output raw json data
echo $output;

?>