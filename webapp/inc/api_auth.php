<?php

$sessman->settimeout($sessman_config['timeout']);
$sessman->config_ldap = $sessman_config['ldap'];
$sessman->config_radius = $sessman_config['radius'];

// Use token (Header: "X-API-Key":"[token]")
if (isset($_SERVER['HTTP_X_API_KEY'])) {
  $sessman->login_token($_SERVER['HTTP_X_API_KEY']);
}

/******************************************************************
 * auth
 ******************************************************************/

// --> /auth
if ($api->route('/auth')) {
  if ($api->method("GET"))      { $result = ['active'=>$sessman->authorized()]; }
  if ($api->method("POST"))     { $result = ['active'=>$sessman->login($payload['username'], $payload['password'])]; }
  if ($api->method("PUT"))      { $result = ['active'=>$sessman->authorized()]; }
  if ($api->method("DELETE"))   { $sessman->logout(); $result = ['active'=>false]; }
}

// HTTP error 403 when not authorized
// All other restrictions are managed by class_sessman
if (!$sessman->authorized())  { $api->http_error(401); }

// --> /auth/user
if ($api->route('/auth/user')) {
  if ($api->method("GET"))      { $result = $sessman->user_list(); }
  if ($api->method("POST"))     { $result = $sessman->user_save(null, $payload)[0]; }
}

// --> /auth/user/[user]
elseif ($api->route('/auth/user/{user}')) {
  if ($api->method("GET"))      { $result = $sessman->user_list($api->param('user'))[0]; }
  if ($api->method("PUT"))      { $result = $sessman->user_save($api->param('user'), $payload)[0]; }
  if ($api->method("DELETE") && ($sessman->user_delete($api->param('user')))) { $result = ['delete'=>true]; }
}

// --> /auth/user/[user]/group
elseif ($api->route('/auth/user/{user}/group')) {
  if ($api->method("GET"))      { $result = $sessman->usergroup_list($api->param('user')); }
  if ($api->method("POST"))     { $result = $sessman->usergroup_save($api->param('user'), $payload)[0]; }
}

// --> /auth/user/[user]/group/[group]
elseif ($api->route('/auth/user/{user}/group/{group}')) {
  if ($api->method("GET"))      { $result = $sessman->group_list($api->param('group'))[0]; }
  if ($api->method("DELETE") && $sessman->usergroup_delete($api->param('user'), $api->param('group'))) { $result = ['delete'=>true]; }
}

// --> /auth/user/[user]/token
elseif ($api->route('/auth/user/{user}/token')) {
  if ($api->method("GET"))      { $result = $sessman->usertoken_list($api->param('user')); }
  if ($api->method("POST"))     { $result = $sessman->usertoken_save($api->param('user'), null, $payload['name'], $payload['expiry']); }
}

// --> /auth/user/[user]/token/[token]
elseif ($api->route('/auth/user/{user}/token/{token}')) {
  if ($api->method("GET"))      { $result = $sessman->usertoken_list($api->param('user'), $api->param('token'))[0]; }
  if ($api->method("PUT"))      { $result = $sessman->usertoken_save($api->param('user'), $api->param('token'), $payload['name'], $payload['expiry']); }
  if ($api->method("DELETE") && ($sessman->usertoken_delete($api->param('user'), $api->param('token')))) { $result = ['delete'=>true]; }
}

// --> /auth/group
elseif ($api->route('/auth/group')) {
  if ($api->method("GET"))      { $result = $sessman->group_list(); }
  if ($api->method("POST"))     { $result = $sessman->group_save(null, $payload)[0]; }
}

// --> /auth/group/[group]
elseif ($api->route('/auth/group/{group}')) {
  if ($api->method("GET"))      { $result = $sessman->group_list($api->param('group'))[0]; }
  if ($api->method("PUT"))      { $result = $sessman->group_save($api->param('group'), $payload)[0]; }
  if ($api->method("DELETE") && ($sessman->group_delete($api->param('group')))) { $result = ['delete'=>true]; }
}

// --> /auth/group/[group]/member
elseif ($api->route('/auth/group/{group}/member')) {
  if ($api->method("GET"))      { $result = $sessman->groupmember_list($api->param('group')); }
}
