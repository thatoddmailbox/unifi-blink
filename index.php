<?php
require_once("config.inc.php");
require_once("util.inc.php");

$privateAuthEnabled = (defined("PRIVATEAUTH_ENDPOINT") && PRIVATEAUTH_ENDPOINT != "");

if (CSRF_PROTECTION || $privateAuthEnabled) {
	session_start();
}

if ($privateAuthEnabled) {
	if (!isset($_SESSION["privateAuthMe"])) {
		if (!isset($_GET["code"])) {
			// need to login
			$_SESSION["privateAuthState"] = generate_csrf_token();
			$authURL = PRIVATEAUTH_ENDPOINT . "?" . http_build_query(array(
				"client_id" => PRIVATEAUTH_CLIENT_ID,
				"redirect_uri" => PRIVATEAUTH_REDIRECT_URI,
				"state" => $_SESSION["privateAuthState"]
			));
			header("Location: $authURL");
			die();
		} else {
			// handling login success
			if (!isset($_GET["state"])) {
				die("Missing state parameter from PrivateAuth endpoint.");
			}

			if (!hash_equals($_SESSION["privateAuthState"], $_GET["state"])) {
				die("PrivateAuth state invalid.");
			}

			// verify token
			$tokenDetails = privateauth_verify_token($_GET["code"]);
			if ($tokenDetails === NULL) {
				die("PrivateAuth token invalid.");
			}

			if (!property_exists($tokenDetails, "me")) {
				die("PrivateAuth server response invalid.");
			}

			$_SESSION["privateAuthMe"] = $tokenDetails->me;
			header("Location: " . PRIVATEAUTH_CLIENT_ID);
			die();
		}
	} else {
		if (isset($_POST["logout"])) {
			if (CSRF_PROTECTION) {
				if (!isset($_POST["csrfToken"])) {
					die("CSRF token required.");
				}

				if (!hash_equals($_SESSION["csrfToken"], $_POST["csrfToken"])) {
					die("CSRF token invalid.");
				}
			}

			unset($_SESSION["privateAuthMe"]);
			die("You have been logged out.");
		}
	}
}

if (defined("AUTH_TOKEN") && AUTH_TOKEN != "") {
	if (!isset($_GET["token"])) {
		die("Auth token is required.");
	}
	if ($_GET["token"] != AUTH_TOKEN) {
		die("Invalid auth token.");
	}
}

if (CSRF_PROTECTION) {
	if (!isset($_SESSION["csrfToken"])) {
		$_SESSION["csrfToken"] = generate_csrf_token();
	}
}

// log in
$loginResponse = do_unifi_request("POST", "api/login", array(
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
	$locateResponse = do_unifi_request("POST", "api/s/" . DEVICE_SITE_ID . "/cmd/devmgr", array(
		"cmd" => $action,
		"mac" => DEVICE_MAC
	));
}

// get the device status
$deviceResponse = do_unifi_request("POST", "api/s/" . DEVICE_SITE_ID . "/stat/device", array(
	"macs" => array(
		DEVICE_MAC
	)
));
if (count($deviceResponse->data) == 0) {
	die("Could not find UniFi device with given MAC address. Are you sure you have the correct site ID and MAC address?");
}

$device = $deviceResponse->data[0];
if ($device->state == 0) {
	die("The UniFi device has been disconnected from the controller. Check the UniFi controller for more details.");
}

$locating = $device->locating;

// log out
$logoutResponse = do_unifi_request("GET", "api/logout", array());

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
		<form class="main" method="POST">
			<?php if (CSRF_PROTECTION) { ?>
				<input type="hidden" name="csrfToken" value="<?php echo htmlentities($_SESSION["csrfToken"]); ?>" />
			<?php } ?>
			<input type="hidden" name="newState" value="<?php echo !$locating ? 'true' : 'false'; ?>" />

			<div class="header">Current status</div>
			<div class="status"><?php if ($locating) { ?>locating<?php } else { ?>not locating<?php } ?></div>
			<input type="submit" value="Toggle" />
		</form>
		<?php if (isset($_SESSION["privateAuthMe"])) { ?>
			<form class="loginInfo" method="POST">
				logged in as <?php echo htmlspecialchars($_SESSION["privateAuthMe"]); ?>
				<?php if (CSRF_PROTECTION) { ?>
					<input type="hidden" name="csrfToken" value="<?php echo htmlentities($_SESSION["csrfToken"]); ?>" />
				<?php } ?>
				<input type="submit" name="logout" value="Log out" />
			</form>
		<?php } ?>
	</body>
</html>
