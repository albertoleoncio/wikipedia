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
editAPI($text, $section, $minor, $summary, $page, $user);
getAPI($page);
getsectionsAPI($page);

*/

if (!isset($endPoint)) $endPoint = $api_url;

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
	curl_setopt( $ch1, CURLOPT_COOKIEJAR, $userAPI."_cookie.inc" );
	curl_setopt( $ch1, CURLOPT_COOKIEFILE, $userAPI."_cookie.inc" );

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
	curl_setopt( $ch, CURLOPT_COOKIEJAR, $userAPI."_cookie.inc" );
	curl_setopt( $ch, CURLOPT_COOKIEFILE, $userAPI."_cookie.inc" );

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
	curl_setopt( $ch1, CURLOPT_COOKIEJAR, $userAPI."_cookie.inc" );
	curl_setopt( $ch1, CURLOPT_COOKIEFILE, $userAPI."_cookie.inc" );

	$output1 = curl_exec( $ch1 );
	curl_close( $ch1 );

	$result1 = json_decode( $output1, true );
	$csrftoken = $result1["query"]["tokens"]["csrftoken"];

	$params4 = [
		"action" 		=> "edit",
		"title" 		=> $page,
		"text"			=> $text,
		"summary"		=> $summary,
		"token" 		=> $csrftoken,
		"format" 		=> "json"
	];

	if (!is_null($section))	{
		if ($section === "append") {
			$params4["appendtext"] = $params4["text"];
			unset($params4["text"]);
		} else {
			$params4["section"]	= $section;
		}
	}		
	if ($minor)				$params4["minor"]	= true;
	if ($minor)				$params4["bot"]		= true;

	$ch = curl_init();

	curl_setopt( $ch, CURLOPT_URL, $endPoint );
	curl_setopt( $ch, CURLOPT_POST, true );
	curl_setopt( $ch, CURLOPT_POSTFIELDS, http_build_query( $params4 ) );
	curl_setopt( $ch, CURLOPT_RETURNTRANSFER, true );
	curl_setopt( $ch, CURLOPT_COOKIEJAR, $userAPI."_cookie.inc" );
	curl_setopt( $ch, CURLOPT_COOKIEFILE, $userAPI."_cookie.inc" );

	$output = curl_exec( $ch );
	curl_close( $ch );

	echo ( "<pre style=\"background-color: antiquewhite;\">" );
	$output = json_decode( $output, true );
	if (isset($output['edit'])) $output = $output['edit'];
	print_r ( $output );
	echo ( "</pre>" );
	if (isset($output["newrevid"])) return $output["newrevid"];
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

	if (!isset($result["query"]["pages"]["0"]["revisions"]["0"]["slots"]["main"]["content"])) {
		return FALSE;
	} else {
		return $result["query"]["pages"]["0"]["revisions"]["0"]["slots"]["main"]["content"];
	}
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

		if (!isset($result["query"]["pages"]["0"]["revisions"]["0"]["slots"]["main"])) {
			return FALSE;
		} else {
			$main = $result["query"]["pages"]["0"]["revisions"]["0"]["slots"]["main"];
		}

		if (isset($main["nosuchsection"])) {
			$validsection = false;
		} else {
			$allsections[$section] = $main["content"];
			$section++;
		}
	}

	return $allsections;

}


function uploadAPI ( $text, $location, $summary, $page, $userAPI) {
	global $endPoint;

	//Get token
	$params1 = [
		"action" 	=> "query",
		"meta" 		=> "tokens",
		"format" 	=> "json"
	];

	$url1 = $endPoint . "?" . http_build_query( $params1 );

	$ch1 = curl_init( $url1 );

	curl_setopt( $ch1, CURLOPT_RETURNTRANSFER, true );
	curl_setopt( $ch1, CURLOPT_COOKIEJAR, $userAPI."_cookie.inc" );
	curl_setopt( $ch1, CURLOPT_COOKIEFILE, $userAPI."_cookie.inc" );

	$output1 = curl_exec( $ch1 );
	curl_close( $ch1 );

	$result1 = json_decode( $output1, true );
	$csrftoken = $result1["query"]["tokens"]["csrftoken"];

	//Get siteinfo
	$params2 = [
		"action" 	=> "query",
		"meta" 		=> "siteinfo",
		"siprop" 	=> "general|fileextensions",
		"format" 	=> "json"
	];

	$url2 = $endPoint . "?" . http_build_query( $params2 );

	$ch2 = curl_init( $url2 );

	curl_setopt( $ch2, CURLOPT_RETURNTRANSFER, true );
	curl_setopt( $ch2, CURLOPT_COOKIEJAR, $userAPI."_cookie.inc" );
	curl_setopt( $ch2, CURLOPT_COOKIEFILE, $userAPI."_cookie.inc" );

	$output2 = curl_exec( $ch2 );
	curl_close( $ch2 );

	$result2 = json_decode( $output2, true );
	$max_upload_size = $result2["query"]["general"]["maxuploadsize"];
	foreach ($result2["query"]["fileextensions"] as $ext) $allowed_extensions[] = $ext["ext"];

	//Verifications about the file
	if (!file_exists($location)) return FALSE;
	if (!in_array(pathinfo($location)['extension'], $allowed_extensions)) return FALSE;
	if (filesize($location) > $max_upload_size) return FALSE;


	$params4 = [
		"action" 		=> "upload",
		"filename" 		=> $page,
		"comment" 		=> $summary,
		"text"			=> $text,
		"file"			=> curl_file_create($location, mime_content_type($location), $page),
		"token" 		=> $csrftoken,
		"ignorewarnings"=> "1",
		"format" 		=> "json"
	];

	$ch = curl_init();

	curl_setopt( $ch, CURLOPT_URL, $endPoint );
	curl_setopt( $ch, CURLOPT_POST, true );
	curl_setopt( $ch, CURLOPT_HTTPHEADER, array('Content-type: multipart/form-data'));
	curl_setopt( $ch, CURLOPT_POSTFIELDS, $params4 );
	curl_setopt( $ch, CURLOPT_RETURNTRANSFER, true );
	curl_setopt( $ch, CURLOPT_COOKIEJAR, $userAPI."_cookie.inc" );
	curl_setopt( $ch, CURLOPT_COOKIEFILE, $userAPI."_cookie.inc" );

	$output = curl_exec( $ch );
	curl_close( $ch );

	echo ( "<pre style=\"background-color: antiquewhite;\">" );
	$output = json_decode( $output, true );
	print_r ( $output );
	echo ( "</pre>" );
}