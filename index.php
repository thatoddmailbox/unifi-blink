<?php
require_once("config.inc.php");

$ch = curl_init();

curl_setopt($ch, CURLOPT_COOKIEFILE, "");

function make_request($type, $path, $data) {
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

// log in
$loginResponse = make_request("POST", "api/login", array(
	"username" => CONTROLLER_USERNAME,
	"password" => CONTROLLER_PASSWORD
));
if ($loginResponse->meta->rc == "error") {
	die("Failed to log into UniFi controller. Are the username and password correct?");
}

if (isset($_POST["newState"])) {
	$newStateString = $_POST["newState"];
	$newState = filter_var($newStateString, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
	if ($newState === null) {
		die("Invalid parameter.");
	}

	// actually update it
	$action = $newState ? "set-locate" : "unset-locate";
	$locateResponse = make_request("POST", "api/s/" . CONTROLLER_SITE_ID . "/cmd/devmgr", array(
		"cmd" => $action,
		"mac" => DEVICE_MAC
	));
}

// get the device status
$deviceResponse = make_request("POST", "api/s/" . CONTROLLER_SITE_ID . "/stat/device", array(
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
$logoutResponse = make_request("GET", "api/logout", array());

curl_close($ch);

?>
<!DOCTYPE html>
<html>
	<head>
		<title>unifi-blink</title>
	</head>
	<body>
		<form method="POST">
			<p>Current status: <?php if ($locating) { ?>locating<?php } else { ?>not locating<?php } ?></p>
			<input type="hidden" name="newState" value="<?php echo !$locating ? 'true' : 'false'; ?>" />
			<input type="submit" value="Toggle" />
		</form>
	</body>
</html>
