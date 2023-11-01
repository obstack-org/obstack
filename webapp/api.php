<?php
require_once 'config.php';
require_once 'inc/class_db.php';

// PHP configuration
date_default_timezone_set('UTC');
error_reporting(E_ALL ^ E_NOTICE);

// Ensure $debug state
if (!$debug) { $debug = false; }

// Database connection
$db = new db($db_connectionstring, $db_persistent);
$db->debug = $debug;

// Prepare API response
require_once 'inc/class_sAPI.php';
$api = new sAPI(2);
$payload = $api->payload();
$result = null;

/******************************************************************
 * API Routes
 ******************************************************************/

// --> /auth
require_once 'inc/class_sessman.php';
require_once 'inc/api_auth.php';
// SA shorthand
function checkSA() {
  global $api, $sessman;
  if (!$sessman->SA())  { $api->http_error(403); }
}

// --> /auth/group  (extension to sessman)
if ($api->uri[1] == 'auth' && $api->uri[2] == 'group') {
  require_once 'inc/mod_acl.php';
  require_once 'inc/api_acl.php';
}

// --> /valuemap
if ($api->uri[1] == 'valuemap' || $api->uri[1] == 'objecttype') {
  require_once 'inc/mod_valuemap.php';
  require_once 'inc/api_valuemap.php';
}

// --> /objtype
if ($api->uri[1] == 'objecttype') {
  require_once 'inc/mod_objtype.php';
  require_once 'inc/mod_obj.php';
  require_once 'inc/api_objtype.php';
}

/******************************************************************
 * API response / error
 ******************************************************************/

if (in_array(gettype($result), ['NULL', 'boolean'])) {
  $api->http_error(404);
}
header('Content-Type: application/json; charset=utf-8');
if ($debug) {
  print json_encode($result);
}
else {
  header('Content-Encoding: gzip');
  print gzencode(json_encode($result), 9);
}
