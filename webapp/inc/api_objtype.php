<?php

/******************************************************************
 * objtype
 ******************************************************************/

$objtype = new mod_objtype($db);
$obj = new mod_obj($db, $objtype);

// --> /objecttype
if ($api->route('/objecttype')) {
  if ($api->method('GET'))     { $result = $objtype->list(); }
  if ($api->method('POST'))    { checkSA(); $result = $objtype->save(null, $api->payload()); }
}

// --> /objecttype/[objecttype]
if ($api->route('/objecttype/{objecttype}')) {
  if ($api->method('GET'))     { $result = $objtype->list($api->param('objecttype')); }
  if ($api->method('PUT'))     { checkSA(); $result = $objtype->save($api->param('objecttype'), $api->payload()); }
  if ($api->method('DELETE'))  { checkSA(); if ($objtype->delete($api->param('objecttype'))) { $result = ['delete'=>true]; } }
}

// --> /objecttype/[objecttype]/acl
if ($api->route('/objecttype/{objecttype}/acl')) {
  require_once 'inc/mod_acl.php';
  require_once 'inc/api_acl.php';
}

// --> /objecttype/[objecttype]/property
if ($api->route('/objecttype/{objecttype}/property')) {
  if ($api->method('GET'))     { $result = $objtype->property_list($api->param('objecttype')); }
  if ($api->method('POST'))    { checkSA(); $result = $objtype->property_save($api->param('objecttype'), null, $api->payload()); }
}

// --> /objecttype/[objecttype]/property/[property]
if ($api->route('/objecttype/{objecttype}/property/{property}')) {
  if ($api->method('GET'))     { $result = $objtype->property_list($api->param('objecttype'), $api->param('property')); }
  if ($api->method('PUT'))     { checkSA(); $result = $objtype->property_save($api->param('objecttype'), $api->param('property'), $api->payload()); }
  if ($api->method('DELETE'))  { checkSA(); if ($objtype->property_delete($api->param('objecttype'), $api->param('property'))) { $result = ['delete'=>true]; } }
}

// --> /objecttype/[objecttype]/object
if ($api->route('/objecttype/{objecttype}/object')) {
  if ($api->method('GET'))     { $result = $objtype->open($api->param('objecttype')); }
  if ($api->method('POST'))    { $result = $obj->save($api->param('objecttype'), null, $api->payload()); }
}

// --> /objecttype/[objecttype]/object/[object]
if ($api->route('/objecttype/{objecttype}/object/{object}')) {
  if ($api->method('GET'))     { $result = $obj->open($api->param('objecttype'), $api->param('object')); }
  if ($api->method('PUT'))     { $result = $obj->save($api->param('objecttype'), $api->param('object'), $api->payload()); }
  if ($api->method('DELETE'))  { if ($obj->delete($api->param('objecttype'), $api->param('object'))) { $result = ['delete'=>true]; } }
}

// --> /objecttype/[objecttype]/object/[object]/relation
if ($api->route('/objecttype/{objecttype}/object/{object}/relation')) {
  if ($api->method('GET'))     { $result = $obj->relation_list($api->param('object')); }
  if ($api->method('POST'))    { if ($obj->relation_save($api->param('object'), $api->payload()['id'])) { $result = ['assign'=>true]; } }
}

// --> /objecttype/[objecttype]/object/[object]/relation/available
if ($api->route('/objecttype/{objecttype}/object/{object}/relation/available')) {
  if ($api->method('GET'))     { $result = $obj->relation_list_available($api->param('objecttype'), $api->param('object')); }
}

// --> /objecttype/[objecttype]/object/[object]/relation/[relation]
if ($api->route('/objecttype/{objecttype}/object/{object}/relation/{relation}')) {
  if ($api->method('DELETE'))  { if ($obj->relation_delete($api->param('object'), $api->param('relation'))) { $result = ['delete'=>true]; } }
}

// --> /objecttype/[objecttype]/object/[object]/log
if ($api->route('/objecttype/{objecttype}/object/{object}/log')) {
  if ($api->method('GET'))     { checkSA(); $result = $obj->log_list($api->param('objecttype'), $api->param('object')); }
}
