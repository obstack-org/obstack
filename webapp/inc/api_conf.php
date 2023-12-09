<?php

/******************************************************************
 * config
 ******************************************************************/

$conf = new mod_conf($db);

// --> /config
if ($api->route('/config')) {
  if ($api->method('GET'))      { $result = $conf->list(!$sessman->authorized()); }
  if ($api->method('PUT'))      { checkSA();  $result = $conf->save($api->payload()); }
}
