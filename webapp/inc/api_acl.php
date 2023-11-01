<?php

/******************************************************************
 * acl
 ******************************************************************/

$acl = new mod_acl($db);

// --> /auth/group/[group]/acl
if ($api->route('/auth/group/{group}/acl')) {
  checkSA();
  if ($api->method('GET'))      { $result = $acl->group_list($api->param('group')); }
  if ($api->method('POST'))     { $result = $acl->group_save($api->param('group'), $api->payload()); }
  if ($api->method('PUT'))      { $result = $acl->group_save($api->param('group'), $api->payload()); }
  if ($api->method('DELETE'))   { $result = $acl->group_delete($api->param('group')); }
}

// --> /objecttype/[objecttype]/acl
elseif ($api->route('/objecttype/{objecttype}')) {
  checkSA();
  if ($api->method('GET'))      { $result[0]->acl = $acl->objtype_list($api->param('objecttype')); }
}

// --> /objecttype/[objecttype]/acl
elseif ($api->route('/objecttype/{objecttype}/acl')) {
  checkSA();
  if ($api->method('GET'))      { $result = $acl->objtype_list($api->param('objecttype')); }
  if ($api->method('POST'))     { $result = $acl->objtype_save($api->param('objecttype'), $api->payload()); }
  if ($api->method('PUT'))      { $result = $acl->objtype_save($api->param('objecttype'), $api->payload()); }
  if ($api->method('DELETE'))   { $result = $acl->objtype_delete($api->param('objecttype')); }
}
