<?php

/*
    edit.php

    MediaWiki API Demos
    Demo of `Edit` module: POST request to edit a page
    MIT license
*/

/*

Comandos:

loginAPI($userAPI, $passAPI);
editAPI($text, $section, $minor, $summary, $page);
getAPI($page);
getsectionsAPI($page);

*/

$endPoint = $api_url;

function loginAPI( $userAPI , $passAPI ) {
	global $endPoint;

	$params1 = [
		"action" => "query",
		"meta" => "tokens",
		"type" => "login",
		"format" => "json"
	];

	$url = $endPoint . "?" . http_build_query( $params1 );

	$ch1 = curl_init( $url );
	curl_setopt( $ch1, CURLOPT_RETURNTRANSFER, true );
	curl_setopt( $ch1, CURLOPT_COOKIEJAR, $userAPI."_cookie.txt" );
	curl_setopt( $ch1, CURLOPT_COOKIEFILE, $userAPI."_cookie.txt" );

	$output1 = curl_exec( $ch1 );
	curl_close( $ch1 );

	$result1 = json_decode( $output1, true );
	$logintoken = $result1["query"]["tokens"]["logintoken"];

	$params2 = [
		"action" 		=> "login",
		"lgname" 		=> $userAPI,
		"lgpassword" 	=> $passAPI,
		"lgtoken" 		=> $logintoken,
		"format" 		=> "json"
	];

	$ch = curl_init();

	curl_setopt( $ch, CURLOPT_URL, $endPoint );
	curl_setopt( $ch, CURLOPT_POST, true );
	curl_setopt( $ch, CURLOPT_POSTFIELDS, http_build_query( $params2 ) );
	curl_setopt( $ch, CURLOPT_RETURNTRANSFER, true );
	curl_setopt( $ch, CURLOPT_COOKIEJAR, $userAPI."_cookie.txt" );
	curl_setopt( $ch, CURLOPT_COOKIEFILE, $userAPI."_cookie.txt" );

	$output = curl_exec( $ch );
	curl_close( $ch );

}

function editAPI( $text , $section , $minor , $summary , $page, $userAPI) {
	global $endPoint;

	$params3 = [
		"action" 	=> "query",
		"meta" 		=> "tokens",
		"format" 	=> "json"
	];

	$url = $endPoint . "?" . http_build_query( $params3 );

	$ch1 = curl_init( $url );

	curl_setopt( $ch1, CURLOPT_RETURNTRANSFER, true );
	curl_setopt( $ch1, CURLOPT_COOKIEJAR, $userAPI."_cookie.txt" );
	curl_setopt( $ch1, CURLOPT_COOKIEFILE, $userAPI."_cookie.txt" );

	$output1 = curl_exec( $ch1 );
	curl_close( $ch1 );

	$result1 = json_decode( $output1, true );
	$csrftoken = $result1["query"]["tokens"]["csrftoken"];

	$params4 = [
		"action" 		=> "edit",
		"bot" 			=> true,
		"title" 		=> $page,
		"token" 		=> $csrftoken,
		"text"			=> $text,
		"summary"		=> $summary,
		"nocreate"		=> true,
		"format" 		=> "json"
	];

	if (!is_null($section))	$params4["section"]	= $section;
	if ($minor)				$params4["minor"]	= $minor;

	$ch = curl_init();

	curl_setopt( $ch, CURLOPT_URL, $endPoint );
	curl_setopt( $ch, CURLOPT_POST, true );
	curl_setopt( $ch, CURLOPT_POSTFIELDS, http_build_query( $params4 ) );
	curl_setopt( $ch, CURLOPT_RETURNTRANSFER, true );
	curl_setopt( $ch, CURLOPT_COOKIEJAR, $userAPI."_cookie.txt" );
	curl_setopt( $ch, CURLOPT_COOKIEFILE, $userAPI."_cookie.txt" );

	$output = curl_exec( $ch );
	curl_close( $ch );

	echo ( "<pre style=\"background-color: antiquewhite;\">" );
	print_r ( json_decode( $output, true )['edit'] );
	echo ( "</pre>" );
}

function getAPI( $page ) {
	global $endPoint;

	$params = [
	    "action" 		=> "query",
	    "prop" 			=> "revisions",
	    "titles" 		=> $page,
	    "rvprop" 		=> "content",
	    "rvslots" 		=> "main",
	    "formatversion" => "2",
	    "format" 		=> "json"
	];

	$url = $endPoint . "?" . http_build_query( $params );

	$ch = curl_init( $url );
	curl_setopt( $ch, CURLOPT_RETURNTRANSFER, true );
	$output = curl_exec( $ch );
	curl_close( $ch );

	$result = json_decode( $output, true );

	return $result["query"]["pages"]["0"]["revisions"]["0"]["slots"]["main"]["content"];
}

function getsectionsAPI( $page ) {
	global $endPoint;

	$section = 0;
	$validsection = true;
	$allsections = array();

	while ($validsection) {

		$params = [
		    "action" 		=> "query",
		    "prop" 			=> "revisions",
		    "titles" 		=> $page,
		    "rvprop" 		=> "content",
		    "rvslots" 		=> "main",
		    "formatversion" => "2",
		    "rvsection"		=> $section,
		    "format" 		=> "json"
		];

		$url = $endPoint . "?" . http_build_query( $params );

		$ch = curl_init( $url );
		curl_setopt( $ch, CURLOPT_RETURNTRANSFER, true );
		$output = curl_exec( $ch );
		curl_close( $ch );

		$result = json_decode( $output, true );

		$main = $result["query"]["pages"]["0"]["revisions"]["0"]["slots"]["main"];

		if (isset($main["nosuchsection"])) {
			$validsection = false;
		} else {
			$allsections[$section] = $main["content"];
			$section++;
		}
	}

	return $allsections;

}