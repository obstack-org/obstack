<?php

include('config.php');
include('inc/class_db.php');

// PHP configuration
date_default_timezone_set('UTC');
error_reporting(E_ALL ^ E_NOTICE);

// Ensure $debug state
if (!$debug) { $debug = false; }

// Database connection
$db = new db($db_connectionstring);
$db->debug = $debug;

// Prepare API response
include('inc/class_sAPI.php');
$api = new sAPI(2);
$payload = $api->payload();
$result = null;

/******************************************************************
 * API Routes
 ******************************************************************/

// --> /auth
include('inc/class_sessman.php');
include('inc/api_auth.php');
// SA shorthand
function checkSA() {
  global $api, $sessman;
  if (!$sessman->SA())  { $api->http_error(403); }
}

// --> /auth/group  (extension to sessman)
if ($api->uri[1] == 'auth' && $api->uri[2] == 'group') {
  include('inc/mod_acl.php');
  include('inc/api_acl.php');
}

// --> /valuemap
if ($api->uri[1] == 'valuemap') {
  include('inc/mod_valuemap.php');
  include('inc/api_valuemap.php');
}

// --> /objtype
if ($api->uri[1] == 'objecttype') {
  include('inc/mod_objtype.php');
  include('inc/mod_obj.php');
  include('inc/api_objtype.php');
}

/******************************************************************
 * API response / error
 ******************************************************************/

if (in_array(gettype($result), ['NULL', 'boolean'])) {
  $api->http_error(404);
}
header('Content-Type: application/json; charset=utf-8');
if ($debug) {
  print(json_encode($result));
}
else {
  header('Content-Encoding: gzip');
  print(gzencode(json_encode($result), 9));
}