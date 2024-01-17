<?php

/******************************************************************
 * config
 ******************************************************************/

// --> /config
if ($api->route('/config')) {
  if ($api->method('GET'))      { $result = $acnf->list(!$sessman->authorized(), $sessman->SA()); }
  if ($api->method('PUT'))      { checkSA();  $result = $acnf->save($api->payload()); }
}
