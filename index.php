<?php
require_once("config.inc.php");

if (CSRF_PROTECTION) {
	session_start();
}

if (defined("AUTH_TOKEN") && AUTH_TOKEN != "") {
	if (!isset($_GET["token"])) {
		die("Auth token is required.");
	}
	if ($_GET["token"] != AUTH_TOKEN) {
		die("Invalid auth token.");
	}
}

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

function do_request($type, $path, $data) {
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

if (CSRF_PROTECTION) {
	if (!isset($_SESSION["csrfToken"])) {
		$_SESSION["csrfToken"] = generate_csrf_token();
	}
}

// log in
$loginResponse = do_request("POST", "api/login", array(
	"username" => CONTROLLER_USERNAME,
	"password" => CONTROLLER_PASSWORD
));
if ($loginResponse->meta->rc == "error") {
	die("Failed to log into UniFi controller. Are the username and password correct?");
}

// check if we actually have a new state to set
if (isset($_POST["newState"])) {
	if (CSRF_PROTECTION) {
		if (!isset($_POST["csrfToken"])) {
			die("CSRF token required.");
		}

		if (!hash_equals($_SESSION["csrfToken"], $_POST["csrfToken"])) {
			die("CSRF token invalid.");
		}
	}

	$newStateString = $_POST["newState"];
	$newState = filter_var($newStateString, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
	if ($newState === null) {
		die("Invalid parameter.");
	}

	// actually update it
	$action = $newState ? "set-locate" : "unset-locate";
	$locateResponse = do_request("POST", "api/s/" . DEVICE_SITE_ID . "/cmd/devmgr", array(
		"cmd" => $action,
		"mac" => DEVICE_MAC
	));
}

// get the device status
$deviceResponse = do_request("POST", "api/s/" . DEVICE_SITE_ID . "/stat/device", array(
	"macs" => array(
		DEVICE_MAC
	)
));
if (count($deviceResponse->data) == 0) {
	die("Could not find UniFi device with given MAC address. Are you sure you have the correct site ID and MAC address?");
}
$device = $deviceResponse->data[0];
$locating = $device->locating;

// log out
$logoutResponse = do_request("GET", "api/logout", array());

curl_close($ch);

?>
<!DOCTYPE html>
<html>
	<head>
		<title>unifi-blink</title>

		<meta name="viewport" content="width=device-width, initial-scale=1">

		<link href="https://fonts.googleapis.com/css2?family=Lato&display=swap" rel="stylesheet" />
		<link href="style.css" rel="stylesheet" />
	</head>
	<body>
		<form method="POST">
			<?php if (CSRF_PROTECTION) { ?>
				<input type="hidden" name="csrfToken" value="<?php echo htmlentities($_SESSION["csrfToken"]); ?>" />
			<?php } ?>
			<input type="hidden" name="newState" value="<?php echo !$locating ? 'true' : 'false'; ?>" />

			<div class="header">Current status</div>
			<div class="status"><?php if ($locating) { ?>locating<?php } else { ?>not locating<?php } ?></div>
			<input type="submit" value="Toggle" />
		</form>
	</body>
</html>
