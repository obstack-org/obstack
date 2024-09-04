<?php
// PHP configuration
date_default_timezone_set('UTC');
error_reporting(E_ALL ^ E_NOTICE);

// Prepare API response
require_once 'inc/class_sAPI.php';
$api = new sAPI(2);
$payload = $api->payload();
$result = null;

// Prepare Plugins
require_once 'inc/class_plugins.php';
foreach (glob("plugins/*.php") as $plgfile) {
  include_once $plgfile;
}
$plugins = new plugins();

// Base configuration
require_once 'config.php';
require_once 'inc/class_conf.php';
$bcnf = new conf($obstack_conf);

// Verify base configuration
if ( count($bcnf->get()) == 0 || $bcnf->get('db_connectionstring') == null ) {
  $api->http_error(428, 'Error in configuration<br><br>Please check:<br><a href="https://www.obstack.org/docs/?doc=general-configuration" target=_blank>https://www.obstack.org/docs/?doc=general-configuration</a><br>For upgrading please check:<br><a href="https://www.obstack.org/docs/?doc=general-configuration#upgrade-nodes" target=_blank>https://www.obstack.org/docs/?doc=general-configuration#upgrade-nodes</a>');
}

// Ensure $debug state
$debug = false;
if (in_array(strtolower($bcnf->get('debug')), ['yes', 'true'])) {
  $debug = true;
}

// Session
$sessionname = "obstack_session";
session_name($sessionname);
session_start();

// Database connection
require_once 'inc/class_db.php';
$db = new db($bcnf->get('db_connectionstring'), $bcnf->get('db_persistent'));
$db->debug = $debug;

// Pre-process configuration and/or data (if any)
require_once 'inc/class_preprc.php';
if (isset($sessman_config)) {
  (new preprc($db))->migrate($sessman_config);
}

// Verify configuration
if (
  isset($db_connectionstring) ||
  isset($sessman_config)
) {
  $api->http_error(428, 'Error in configuration.<br><br>Please check the <a href="https://www.obstack.org/docs/?doc=general-configuration#upgrade-nodes" target=_blank>Upgrade nodes</a>');
}
$dbfunc = ($db->driver()->mysql) ? 'database()' : 'CURRENT_SCHEMA()';
if(count($db->query_buffered('stdec',"SELECT 1 FROM information_schema.tables WHERE table_schema = $dbfunc AND table_name = 'setting_decimal'")) == 0){
  $api->http_error(428, 'Database check failed, contact your system administrator.<br><br>(Reference: <a href="https://www.obstack.org/docs/?doc=general-configuration#database" target=_blank>Database schema</a>)');
}

// App configuration
require_once 'inc/mod_conf.php';
$acnf = new mod_conf($db, $bcnf->get());
if (!$acnf->verify()) {
  $api->http_error(428, 'Encryption check failed, contact your system administrator.<br><br>(Reference: <a href="https://www.obstack.org/docs/?doc=general-configuration" target=_blank>General configuration</a>)');
}

// Global log function
$oblog = [];
function oblog($data) {
  global $oblog;
  $oblog[] = $data;
  header("ObStack-Log: ".json_encode($oblog));
}

/******************************************************************
 * API Routes
 ******************************************************************/

// Session Manager
require_once 'inc/class_sessman.php';
$sessman = new sessman($db, $sessionname);
if ($sessman->ratelimit()) {
  $api->http_error(428, 'Too many invalid login attempts.');
}

// SA shorthand
function checkSA() {
  global $api, $sessman;
  if (!$sessman->SA())  { $api->http_error(403); }
}

// --> /config
if (count($api->uri) >=2 && $api->uri[1] == 'config') {
  require_once 'inc/api_conf.php';
}

else {

  // --> /auth
  require_once 'inc/api_auth.php';

  // --> /auth/group  (extension to sessman)
  if (count($api->uri) >=3 && $api->uri[1] == 'auth' && $api->uri[2] == 'group') {
    require_once 'inc/mod_acl.php';
    require_once 'inc/api_acl.php';
  }

  // --> /valuemap
  if (count($api->uri) >=2 && ($api->uri[1] == 'valuemap' || $api->uri[1] == 'objecttype')) {
    require_once 'inc/mod_valuemap.php';
    require_once 'inc/api_valuemap.php';
  }

  // --> /objtype
  if (count($api->uri) >=2 && $api->uri[1] == 'objecttype') {
    require_once 'inc/mod_objtype.php';
    require_once 'inc/mod_obj.php';
    require_once 'inc/api_objtype.php';
  }

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
