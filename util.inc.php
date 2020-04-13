<?php
$ch = curl_init();

// enable cURL's cookie engine
// we only need to have it last for the duration of this request so don't actually give it a file
curl_setopt($ch, CURLOPT_COOKIEFILE, "");

function generate_csrf_token() {
	if (function_exists("random_bytes")) {
		return bin2hex(random_bytes(32));
	}

	if (function_exists("mcrypt_create_iv")) {
		return bin2hex(mcrypt_create_iv(32, MCRYPT_DEV_URANDOM));
	}

	if (function_exists("openssl_random_pseudo_bytes")) {
		return bin2hex(openssl_random_pseudo_bytes(32));
	}

	die("Unable to generate secure random bytes. You should upgrade PHP to at least version 7. If this is not possible, then at least make sure that either the mcrypt or openssl extensions are enabled.");
}

function privateauth_verify_token($code) {
	$ch = curl_init();

	curl_setopt($ch, CURLOPT_URL, PRIVATEAUTH_ENDPOINT);
	curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);

	$stringData = http_build_query(array(
		"code" => $code,
		"client_id" => PRIVATEAUTH_CLIENT_ID,
		"redirect_uri" => PRIVATEAUTH_REDIRECT_URI
	));

	curl_setopt($ch, CURLOPT_POST, 1);
	curl_setopt($ch, CURLOPT_POSTFIELDS, $stringData);
	// curl_setopt($ch, CURLOPT_HTTPHEADER, array("Content-Type: application/json"));

	$content = curl_exec($ch);

	$responseCode = curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
	if ($responseCode != 200) {
		return NULL;
	}

	return json_decode($content);
}

function do_unifi_request($type, $path, $data) {
	global $ch;
	$fullURL = CONTROLLER_URL . $path;
	curl_setopt($ch, CURLOPT_URL, $fullURL);
	curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);

	if (CONTROLLER_NO_VERIFY) {
		curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);
		curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 0);
	}

	if ($type == "POST") {
		$stringData = json_encode($data);
		curl_setopt($ch, CURLOPT_POST, 1);
		curl_setopt($ch, CURLOPT_POSTFIELDS, $stringData);
		curl_setopt($ch, CURLOPT_HTTPHEADER, array("Content-Type: application/json"));
	}

	$content = curl_exec($ch);
	return json_decode($content);
}