<?php

/******************************************************************
 * valuemap
 ******************************************************************/

$valuemap = new mod_valuemap($db);

// --> /valuemap
if ($api->route('/valuemap')) {
  if ($api->method('GET'))      { $result = $valuemap->list(); }
  if ($api->method('POST'))     { checkSA(); $result = $valuemap->save(null, $api->payload()); }
}

// --> /valuemap/[valuemap]
elseif ($api->route('/valuemap/{valuemap}')) {
  if ($api->method('GET'))      { $result = $valuemap->list($api->param('valuemap')); }
  if ($api->method('PUT'))      { checkSA(); $result = $valuemap->save($api->param('valuemap'), $api->payload()); }
  if ($api->method('DELETE'))   { checkSA(); if ($valuemap->delete($api->param('valuemap'))) { $result = ['delete'=>true]; } }
}

// --> /valuemap/[valuemap]/value
elseif ($api->route('/valuemap/{valuemap}/value')) {
  if ($api->method('GET'))      { $result = $valuemap->value_list($api->param('valuemap')); }
  if ($api->method('POST'))     { checkSA(); $result = $valuemap->value_save($api->param('valuemap'), null, $api->payload())[0]; }
}

// --> /valuemap/[valuemap]/[value]
elseif ($api->route('/valuemap/{valuemap}/value/{value}')) {
  if ($api->method('GET'))      { $result = $valuemap->value_list($api->param('valuemap'), $api->param('value'))[0]; }
  if ($api->method('PUT'))      { checkSA(); $result = $valuemap->value_save($api->param('valuemap'), $api->param('value'), $api->payload()); }
  if ($api->method('DELETE'))   { checkSA(); if ($valuemap->value_delete($api->param('valuemap'), $api->param('value'))) { $result = ['delete'=>true]; } }
}
