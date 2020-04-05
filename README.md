# unifi-blink
Script to allow easy blinking of a [UniFi access point](https://unifi-network.ui.com/)'s LED. Useful as a busy indicator or similar. Requires PHP 5.6.0 or greater.

This is really just a wrapper around the [semi-documented](https://ubntwiki.com/products/software/unifi-controller/api) UniFi Controller API. You _will_ need a running UniFi controller, and you should make sure that there are [no STUN errors](https://help.ubnt.com/hc/en-us/articles/115015457668-UniFi-Troubleshooting-STUN-Communication-Errors) (if your controller is on the same network as the access points, you should be fine&mdash;this only really comes up with a remote controller).

## Setup
unifi-blink has to sign into the UniFi controller with a user account. **It's recommended to create a separate account just for unifi-blink**, with a randomly generated password. This account will need the Administrator role on whatever site your device is.

Copy the included PHP and CSS files to a folder on your server. Copy the provided `config.inc.example.php` file into a new `config.inc.php` file. Then edit this file as follows:

### Config parameters
* `CONTROLLER_URL` - the full URL to your UniFi controller. Make sure this ends with a slash! If your controller is running on the same server as unifi-blink, you can leave this as its default.
* `CONTROLLER_USERNAME` - the username of the admin account unifi-blink should use.
* `CONTROLLER_PASSWORD` - the password of the admin account unifi-blink should use.
* `CONTROLLER_NO_VERIFY` - whether the controller's SSL certificate should be ignored. If you just have it running locally, this should be `true`. If your controller is actually set up properly (with its own domain and SSL certificate) then this should be `false`.

* `DEVICE_SITE_ID` - the site ID that contains the target UniFi device. You can find this by going to your devices page in the Controller and looking at the URL: it will look like https://(controller domain)/manage/site/(site name)/devices/. If you're using the default site, then this is just `default`.
* `DEVICE_MAC` - the MAC address of the device to control the LED of. You can find this by opening the device's details page in the UniFi controller&mdash;it should be under the "Overview" category.

* `AUTH_TOKEN` (optional) - if set, this will require an authentication token to be included in the URL in order to use unifi-blink.
* `CSRF_PROTECTION` - whether CSRF protection should be enabled. You should leave this as `true` unless you really know what you're doing.

## Auth token
If, in your configuration, you set `AUTH_TOKEN` to some string, then you'll need to include that string in the URL when using unifi-blink. For example, if your `AUTH_TOKEN` is `secure_t0ken`, then you'll need to go to `https://(server domain)/unifi-blink/index.php?token=secure_t0ken`.