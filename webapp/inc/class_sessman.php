<?php
/******************************************************************
 *
 * sessman($db, $sessionname)
 *  Config
 *  -> $config_ldap = false
 *  -> $config_radius = false
 *  -> function settimeout($timeout)
 *  Session / Authentication:
 *  -> function login($username, $secret)
 *  -> function login_token($token)
 *  -> function authorized()
 *  -> function SA()
 *  -> function tokens()
 *  -> function ismember($groupid)
 *  -> function logout()
 *  User management:
 *  -> function user_list($id = null)
 *  -> function user_save($id, $data)
 *  -> function user_delete($id)
 *  -> function usergroup_list($userid)
 *  -> function usergroup_save($userid, $data)
 *  -> function usergroup_delete($userid, $groupid)
 *  Token management:
 *  -> function usertoken_list($userid, $tokenid = null)
 *  -> function usertoken_save($userid, $tokenid, $name, $expiry)
 *  -> function usertoken_delete($userid, $tokenid)
 *  Group management:
 *  -> function group_list($id = null)
 *  -> function group_save($id, $data)
 *  -> function group_delete($id)
 *
 ******************************************************************/

require_once 'class_totp.php';

class sessman {

  /******************************************************************
   * $config_ldap = [
   *   'enabled'       => false,
   *   'host'          => '127.0.0.1',
   *   'port'          => 389,
   *   'userdn'        => 'cn=users,cn=accounts,dc=example,dc=local',
   *   'group-auth'    => 'cn=mygroup-auth,cn=groups,cn=accounts,dc=example,dc=local',
   *   'group-sa'      => 'cn=mygroup-sa,cn=groups,cn=accounts,dc=example,dc=local'
   * ];
   ******************************************************************/
  public $config_ldap = null;

  /******************************************************************
   * $config_radius = [
   *   'enabled'       => false,
   *   'host'          => '127.0.0.1',
   *   'port'          => 1812,
   *   'secret'        => 'testing123',
   *   'attr'          => 230,
   *   'group-auth'    => 'mygroup-auth',
   *   'group-sa'      => 'mygroup-sa'
   * ];
   * (groups match if name is in comma separated list of 'attr' value)
   ******************************************************************/
  public $config_radius = null;

  /******************************************************************
   * $db = new db($db_connectionstring);
   ******************************************************************/
  private $db;
  private $sessionname;

  /******************************************************************
   * Initialize, start session
   ******************************************************************/
  public function __construct($db, $sessionname) {
    $this->db = $db;
    $this->sessionname = $sessionname;
    $this->settimeout(600);
    if (session_status()==PHP_SESSION_NONE) {
      session_name($sessionname);
      session_start();
    }
  }

  /******************************************************************
   * Session user data
   ******************************************************************/
  private function session_create($user) {
    $_SESSION['sessman'] = [];
    $_SESSION['sessman']['active'] = time();
    $_SESSION['sessman']['userid'] = $user->id;
    $_SESSION['sessman']['tfc'] = $user->tfc;
    $_SESSION['sessman']['username'] = $user->username;
    $_SESSION['sessman']['groups'] = $user->groups;
    $_SESSION['sessman']['tokens'] = $user->tokens;
    $_SESSION['sessman']['sa'] = $user->sa;
    $_SESSION['sessman']['ext'] = $user->ext;
  }

  /******************************************************************
   * Close session user data
   ******************************************************************/
  private function session_delete() {
    unset($_SESSION['sessman']);
  }

  /******************************************************************
   * Set session timeout
   ******************************************************************/
  public function settimeout($timeout) {
    $this->timeout = $timeout;
  }

  /******************************************************************
   * General login using username/password
   *   (tries LDAP and/or Radius login when enabled)
   ******************************************************************/
  public function login($username, $secret, $otp=null) {
    // auth_ldap
    if ($this->config_ldap != null && $this->config_ldap['enabled'] && $this->auth_ldap($username, $secret)) {
      return true;
    }
    // auth_radius
    if ($this->config_radius != null && ($this->config_radius['enabled'] && $this->auth_radius($username, $secret))) {
      return true;
    }
    // auth_db
    return $this->auth_db($username, $secret, $otp);
  }

  /******************************************************************
   * Login using a token
   ******************************************************************/
  public function login_token($token) {
    // MySQL/pSQL
    $dbqtoken = ($this->db->driver()->mysql) ? 'PASSWORD(:token)' : 'crypt(:token, ut.token)';
    // Determine session state
    $user = $this->db->query("
      SELECT
        u.id AS id,
        u.username AS username,
        u.firstname AS firstname,
        u.lastname AS lastname,
        u.tokens AS tokens,
        u.sa AS sa
      FROM sessman_user u
      LEFT JOIN sessman_usertokens ut ON ut.smuser = u.id
      WHERE u.active = true
      AND u.tokens = true
      AND ut.token = $dbqtoken
      AND ut.expiry > now()",
      [':token'=>$token]
    );
    // Set user
    if (!empty($user)) {
      $user[0]->groups = [];
      foreach ($this->db->select('smgroup','sessman_usergroups', [':smuser'=>$user[0]->id]) as $dbrec) {
        $user[0]->groups[] = $dbrec->smgroup;
      }
      $user[0]->ext = false;
      $user[0]->tfc = false;
      $this->session_create($user[0]);
      return true;
    }
    // Logout
    $this->session_delete();
    return false;
  }

  /******************************************************************
   * Authorization state
   ******************************************************************/
  public function authorized() {
    // Session state and timeout
    if (isset($_SESSION['sessman']) && !$_SESSION['sessman']['tfc'] && (time() < ($_SESSION['sessman']['active'] + $this->timeout))) {
      $_SESSION['sessman']['active'] = time();
      return true;
    }
    // Unauthorized, reset session id
    session_destroy();
    session_id(bin2hex(random_bytes(random_int(56,64))));
    session_start();
    return false;
  }

  /******************************************************************
   * SuperAdmin state
   ******************************************************************/
  public function SA() {
    if (!$this->authorized()) { return false; }
    return $_SESSION['sessman']['sa'];
  }

  /******************************************************************
   * Tokens state
   ******************************************************************/
  public function tokens() {
    if (!$this->authorized()) { return false; }
    return $_SESSION['sessman']['tokens'];
  }

  /******************************************************************
   * Group membership
   ******************************************************************/
  public function ismember($groupid) {
    if (!$this->authorized()) { return false; }
    return isset($_SESSION['sessman']['groups'][$groupid]);
  }

  /******************************************************************
   * General logout
   ******************************************************************/
  public function logout() {
    $this->session_delete();
  }

  /******************************************************************
   * Authentication by Database
   ******************************************************************/
  private function auth_db($username, $secret, $otp=null) {
    $dbqsecret = ($this->db->driver()->mysql) ? 'secret=PASSWORD(:secret)' : 'secret=crypt(:secret, secret)';
    $dbqactive = ($this->db->driver()->mysql) ? '1' : 'true';
    // Determine session state
    $user = $this->db->query(
      "SELECT id, username, firstname, lastname, totp, totp_secret, tokens, sa FROM sessman_user WHERE username=:username AND $dbqsecret AND active=$dbqactive",
      [':username'=>strtolower($username), ':secret'=>$secret]
    );
    if (!empty($user)) {
      $user[0]->groups = [];
      foreach ($this->db->select('smgroup', 'sessman_usergroups', [':smuser'=>$user[0]->id]) as $dbrec) {
        $user[0]->groups[] = $dbrec->smgroup;
      }
      $user[0]->ext = false;
      // TOTP
      $user[0]->tfc = false;
      $totp_default = false;
      foreach ($this->db->select('value', 'setting_decimal', [':name'=>'session_totp-default']) as $dbrow) {
        if ($dbrow->value == '1') {
          $totp_default = true;
        }
      }
      if ($user[0]->totp || $totp_default) {
        $user[0]->tfc = true;
        if ($user[0]->totp_secret == null) {
          $totp_secret = TOTP::genSecret(56);
          $user[0]->totp_secret = $totp_secret['secret'];
          $this->db->update('sessman_user', [':totp_secret'=>$user[0]->totp_secret], [':id'=>$user[0]->id]);
          header('Content-Encoding: gzip');
          print gzencode(json_encode(TOTP::genURI( $user[0]->username, $user[0]->totp_secret, 6, 30, 'ObStack', 'sha256' )), 9);
        }
        else {
          if ($otp == null) {
            header('Content-Encoding: gzip');
            print gzencode(json_encode(['otp'=>'mooh']), 9);
          }
          else {
            if ($otp == TOTP::getOTP($user[0]->totp_secret, 6, 30, 0, 'sha256')['otp']) {
              $user[0]->tfc = false;
            }
          }
        }
      }

      // Finish
      $this->session_create($user[0]);
      return true;
    }
    $this->session_delete();
    return false;
  }

  /******************************************************************
   * Authentication by LDAP
   ******************************************************************/
  private function auth_ldap($username, $secret) {
    // Extension state
    if (!extension_loaded('ldap')) {
      die('Error! [ldap]: PHP extension not loaded');
    }
    // Defaults
    $auth = false;
    $user = new stdClass();
    $user->id = null;
    $user->username = $username;
    $user->tokens = false;
    $user->sa = false;
    $user->ext = true;
    $user->groups = [];
    // LDAP connection
    $ldapconn = ldap_connect($this->config_ldap['host'], $this->config_ldap['port']);
    ldap_set_option($ldapconn, LDAP_OPT_PROTOCOL_VERSION, 3);
    ldap_set_option($ldapconn, LDAP_OPT_REFERRALS, 0);
    ldap_set_option($ldapconn, LDAP_OPT_NETWORK_TIMEOUT, 10);
    // LDAP connection
    if (@ldap_bind($ldapconn, "uid=$username,".$this->config_ldap['userdn'], $secret)) {
      // Database groups
      $dbgroups = [];
      foreach ($this->db->select('id, ldapcn','sessman_group') as $dbrec) {
        if ($dbrec->ldapcn != null) {
          $dbgroups[trim($dbrec->ldapcn)] = $dbrec->id;
        }
      }
      // Group matching
      foreach(ldap_get_entries($ldapconn, ldap_search($ldapconn, "uid=$username,".$this->config_ldap['userdn'], 'cn=*'))[0]['memberof'] as $ldapgroup) {
        if (isset($dbgroups[$ldapgroup])) {
          $user->groups[] = $dbgroups[trim($ldapgroup)];
        }
        if ($ldapgroup == $this->config_ldap['group-auth'])   { $auth = true; }
        if ($ldapgroup == $this->config_ldap['group-sa'])     { $user->sa = true; }
      }
    }
    // Session state
    if ($auth) {
      $this->session_create($user);
      return true;
    }
    $this->session_delete();
    return false;
  }

  /******************************************************************
   * Authentication by Radius
   ******************************************************************/
  private function auth_radius($username, $secret) {
    // Extension state
    if (!extension_loaded('radius')) {
      die('Error! [radius]: PHP extension not loaded');
    }
    // Defaults
    $auth = false;
    $user = new stdClass();
    $user->id = null;
    $user->username = $username;
    $user->tokens = false;
    $user->sa = false;
    $user->ext = true;
    $user->groups = [];
    // Radius connection
    $radconn = radius_auth_open();
    radius_add_server($radconn, $this->config_radius['host'], $this->config_radius['port'], $this->config_radius['secret'], 5, 3);
    radius_create_request($radconn, RADIUS_ACCESS_REQUEST);
    radius_put_attr($radconn, RADIUS_USER_NAME, $username);
    radius_put_attr($radconn, RADIUS_USER_PASSWORD, $secret);
    if (radius_send_request($radconn) == RADIUS_ACCESS_ACCEPT) {
      // Database groups
      $dbgroups = [];
      foreach ($this->db->select('id, radiusattr','sessman_group') as $dbrec) {
        if ($dbrec->radiusattr != null) {
          foreach (explode(';', str_replace([',', '|'], ';', $dbrec->radiusattr)) as $group) {
            $dbgroups[trim($group)] = $dbrec->id;
          }
        }
      }
      // Group matching
      while ($attr = radius_get_attr($radconn)) {
        if ($attr['attr'] == intval($this->config_radius['attr'])) {
          foreach (explode(';', str_replace([',', '|'], ';', $attr['data'])) as $group) {
            if (isset($dbgroups[trim($group)])) {
              $user->groups[] = $dbgroups[trim($group)];
            }
            if (trim($group) == $this->config_radius['group-auth'])   { $auth = true; }
            if (trim($group) == $this->config_radius['group-sa'])     { $user->sa = true; }
          }
        }
      }
    }
    // Session state
    if ($auth) {
      $this->session_create($user);
      return true;
    }
    $this->session_delete();
    return false;
  }

  /******************************************************************
   * List user(s)
   *    $id = null      List all users (SA only)
   *    $id = 'self'    Active user's profile
   *    $id = ......    User by UUID (SA only)
   ******************************************************************/
  public function user_list($id = null) {
    $self = false;
    if ($id == null) {
      // List all users (SA only)
      if (!$this->SA())  { return false; }
      $result = $this->db->select('id, username, firstname, lastname, active, tokens, sa','sessman_user', [], 'username');
      foreach(range(0,count($result)-1) as $idx) {
        if ($this->db->driver()->mysql) {
          foreach(['active', 'tokens', 'sa'] as $key) {
            $result[$idx]->$key = ($result[$idx]->$key == '1') ? true : false;
          }
        }
      }
      return $result;
    }
    else {
      // List 'self'
      if ($id == 'self') {
        if (!$this->authorized()) { return false; }
        // External user
        if ($_SESSION['sessman']['userid'] == null) {
          $user = new stdClass();
          $user->username = $_SESSION['sessman']['username'];
          $user->firstname = '';
          $user->lastname = '';
          $user->tokens = false;
          $user->sa = $_SESSION['sessman']['sa'];
          $user->ext = $_SESSION['sessman']['ext'];
          return [$user];
        }
        // DB user
        $id = $_SESSION['sessman']['userid'];
        $self = true;
      }
      else {
        // Lists user by id, allow SA only
        if (!$this->SA())  { return false; }
      }
    }
    // Prepare response
    $result = $this->db->select('id, username, totp, firstname, lastname, active, tokens, sa','sessman_user', [':id'=>$id]);
    if ($this->db->driver()->mysql) {
      foreach(['active', 'tokens', 'sa', 'totp'] as $key) {
        $result[0]->$key = ($result[0]->$key == '1') ? true : false;
      }
    }
    if ($self) {
      unset($result[0]->id);
      unset($result[0]->active);
    }
    return $result;
  }

  /******************************************************************
   * Save user
   *    $id = null      Create new user (SA only)
   *    $id = 'self'    Active user's profile
   *    $id = ......    User by UUID (SA only)
   *    $data = [
   *      "username":   "string",
   *      "password":   "string",
   *      "firstname":  "string",
   *      "lastname":   "string",
   *      "tokens":     boolean,
   *      "sa":         boolean
   *    ]
   ******************************************************************/
  public function user_save($id, $data) {
    if ($id == 'self') {
      // Always allow self
      if (!$this->authorized()) { return false; }
      $id = $_SESSION['sessman']['userid'];
      $fieldlist = ['password', 'totp_reset'];
    }
    else {
      // If not self only allow SA
      if (!$this->SA()) { return false; }
      $fieldlist = ['username', 'password', 'firstname', 'lastname', 'active', 'tokens', 'sa', 'totp', 'totp_reset'];
    }
    // Prepare SQL statement (MySQL/pSQL)
    $dbq = (object)[ 'columns'=>[], 'values'=>[], 'update'=>[], 'params'=>[] ];
    foreach ($fieldlist as $key) {
      if (isset($data[$key])) {
        if ($key == 'password') {
          $dbq->columns[] = 'secret';
          $dbq->values[] = ($this->db->driver()->mysql) ? 'PASSWORD(:secret)' : 'crypt(:secret, gen_salt(\'bf\'))';
          $dbq->update[] = ($this->db->driver()->mysql) ? 'secret=PASSWORD(:secret)' : 'secret=crypt(:secret, gen_salt(\'bf\'))';
          $dbq->params[':secret'] = $data[$key];
        }
        elseif ($key=='totp_reset' && ($data['totp_reset'] || $data['totp_reset'] == 1)) {
          $dbq->columns[] = 'totp_secret';
          $dbq->values[]  = ':totp_secret';
          $dbq->update[]  = 'totp_secret=:totp_secret';
          $dbq->params[":totp_secret"] = null;
        }
        elseif (in_array($key, ['active', 'tokens', 'sa', 'totp'])) {
          $dbq->columns[] = $key;
          $dbq->values[]  = ":$key";
          $dbq->update[]  = "$key=:$key";
          if ($this->db->driver()->mysql) {
            $dbq->params[":$key"] = ($data[$key] || $data[$key] == '1') ? '1' : '0';
          } else {
            $dbq->params[":$key"] = ($data[$key] || $data[$key] == '1') ? 'true' : 'false';
          }
        }
        else {
          $dbq->columns[] = $key;
          $dbq->values[]  = ":$key";
          $dbq->update[]  = "$key=:$key";
          $dbq->params[":$key"] = $data[$key];
        }
      }
    }

    // Create new user
    if ($id == null) {
      $dbq->columns = implode(',', $dbq->columns);
      $dbq->values  = implode(',', $dbq->values);
      if ($this->db->driver()->mysql_legacy) {
        $id = $this->db->query('SELECT uuid_generate_v4() AS id')[0]->id;
        $dbq->columns .= ',id';
        $dbq->values .= ',:id';
        $dbq->params[":id"] = $id;
        $this->db->query("INSERT INTO sessman_user ($dbq->columns) VALUES ($dbq->values)", $dbq->params);
      }
      else {
        $id = $this->db->query("INSERT INTO sessman_user ($dbq->columns) VALUES ($dbq->values) RETURNING id", $dbq->params)[0]->id;
      }
      if (isset($data['groups'])) {
        foreach($data['groups'] as $groupid) { $this->usergroup_save($id, ['id'=>$groupid]); }
      }
      return [ ['id'=>$id] ];
    }

    // Update user
    else {
      $dbq->params[":id"] = $id;
      if (count($dbq->update)>0) {
        $this->db->query("UPDATE sessman_user SET ".implode(',', $dbq->update)." WHERE id=:id", $dbq->params);
      }
      if (isset($data['groups'])) {
        $this->db->delete('sessman_usergroups', [':smuser'=>$id]);
        foreach($data['groups'] as $groupid) { $this->usergroup_save($id, ['id'=>$groupid]); }
      }
      return [[]];
    }
  }

  /******************************************************************
   * Delete user
   ******************************************************************/
  public function user_delete($id) {
    $this->db->delete('sessman_usertokens', [':smuser'=>$id]);
    $this->db->delete('sessman_usergroups', [':smuser'=>$id]);
    $this->db->delete('sessman_user', [':id'=>$id]);
    return true;
  }

  /******************************************************************
   * List user groups (SA only)
   ******************************************************************/
  public function usergroup_list($userid) {
    if (!$this->SA())  { return false; }
    if ($userid == 'self') { return false; }
    $dbquery = '
      SELECT
        g.id AS id,
        g.groupname AS groupname
      FROM sessman_usergroups AS ug
      LEFT JOIN sessman_group AS g on g.id = ug.smgroup
      where ug.smuser=:smuser
    ';
    return $this->db->query($dbquery, [':smuser'=>$userid]);
  }

  /******************************************************************
   * Save user group (SA only)
   ******************************************************************/
  public function usergroup_save($userid, $data) {
    if (!$this->SA())  { return false; }
    if ($userid == 'self') { return false; }
    $this->db->query('INSERT INTO sessman_usergroups VALUES (:smuser, :smgroup)', [':smuser'=>$userid, ':smgroup'=>$data['id']]);
    return true;
  }

  /******************************************************************
   * Delete user group (SA only)
   ******************************************************************/
  public function usergroup_delete($userid, $groupid) {
    if (!$this->SA())  { return false; }
    if ($userid == 'self') { return false; }
    $this->db->query('sessman_usergroups', [':smuser'=>$userid, ':smgroup'=>$groupid]);
    return true;
  }

  /******************************************************************
   * List user token
   *    $userid = ......      User by UUID
   *    $tokenid = null       All tokens
   *    $tokenid = ......     Token by UUID
   ******************************************************************/
  public function usertoken_list($userid, $tokenid = null) {
    if ($userid == 'self') {
      // Always allow self
      if (!$this->authorized()) { return false; }
      if (!$this->tokens()) { return []; }
      $userid = $_SESSION['sessman']['userid'];
    }
    else {
      // If not self only allow SA
      if (!$this->SA()) { return false; }
    }
    // User tokens allowed
    if (count($this->db->select('tokens','sessman_user', [':id'=>$userid, ':tokens'=>($this->db->driver()->mysql) ? '1' : true ])) == 0) {
      return [];
    }
    // Token(s)
    $expiry = ($this->db->driver()->mysql) ? 'DATE_FORMAT(expiry, \'%Y-%m-%d %H:%i\')' : 'TO_CHAR(expiry,\'YYYY-MM-DD HH24:MI\')';
    if ($tokenid == null) {
      return $this->db->select("id, name, $expiry as expiry", 'sessman_usertokens', [':smuser'=>$userid]);
    }
    else {
      return $this->db->select("name, $expiry as expiry", 'sessman_usertokens', [':smuser'=>$userid, ':id'=>$tokenid]);
    }
  }

  /******************************************************************
   * Create user token
   *    $userid = 'self'        Active user's token
   *    $userid = ......        Any user's token (SA only)
   *    $tokenid = null         Create new token
   *    $tokenid = ......       Update token by UUID
   *    $name = ""              Display name
   *    $expiry = ......        Expiry datetime (YYYY-MM-DD MM:HH)
   * (tokens are only returned on creation)
   ******************************************************************/
  public function usertoken_save($userid, $tokenid, $name, $expiry) {
    if ($userid == 'self') {
      // Always allow self
      if (!$this->authorized()) { return false; }
      if (!$this->tokens()) { return false; }
      $userid = $_SESSION['sessman']['userid'];
    }
    else {
      // If not self only allow SA
      if (!$this->SA()) { return false; }
    }
    // User tokens allowed
    if (count($this->db->select('tokens','sessman_user', [':id'=>$userid, ':tokens'=>($this->db->driver()->mysql) ? '1' : true ])) == 0) {
      return false;
    }
    // MySQL/pSQL
    $dbqtoken = ($this->db->driver()->mysql) ? 'PASSWORD(:token)' : 'crypt(:token, gen_salt(\'bf\'))';
    if ($tokenid == null) {
      // Register token
      $token = bin2hex(random_bytes(random_int(16, 20)));
      $dbq = (object)[
        'columns'=>'smuser, name, token, expiry',
        'values'=>":smuser, :name, $dbqtoken, :expiry",
        'params'=>[':smuser'=>$userid, ':token'=>$token, ':name'=>$name, ':expiry'=>$expiry],
      ];
      if ($this->db->driver()->mysql_legacy) {
        $tokenid = $this->db->query('SELECT uuid_generate_v4() AS id')[0]->id;
        $dbq->columns .= ',id';
        $dbq->values .= ',:id';
        $dbq->params[":id"] = $tokenid;
        $this->db->query("INSERT INTO sessman_usertokens ($dbq->columns) VALUES ($dbq->values)", $dbq->params);
      }
      else {
        $tokenid = $this->db->query("INSERT INTO sessman_usertokens ($dbq->columns) VALUES ($dbq->values) RETURNING id", $dbq->params)[0]->id;
      }
      if (count($this->db->select('id', 'sessman_usertokens', [':id'=>$tokenid])) > 0) {
        return [ 'id'=>$tokenid, 'token'=>$token ];
      }
    }
    else {
      // Update token
      $this->db->update('sessman_usertokens', [':name'=>$name, ':expiry'=>$expiry], [':id'=>$tokenid, ':smuser'=>$userid]);
      return [];
    }
    return false;
  }

  /******************************************************************
   * Delete user token
   *    $userid = 'self'        Active user's token
   *    $userid = ......        Any user's token (SA only)
   *    $tokenid = ......       Delete token by UUID
   ******************************************************************/
  public function usertoken_delete($userid, $tokenid) {
    if ($userid == 'self') {
      // Always allow self
      if (!$this->authorized()) { return false; }
      if (!$this->tokens()) { return false; }
      $userid = $_SESSION['sessman']['userid'];
    }
    else {
      // If not self only allow SA
      if (!$this->SA()) { return false; }
    }
    // User tokens allowed
    if (count($this->db->select('tokens','sessman_user', [':id'=>$userid, ':tokens'=>($this->db->driver()->mysql) ? '1' : true ])) == 0) {
      return false;
    }
    // Delete token
    $this->db->delete('sessman_usertokens', [':id'=>$tokenid, ':smuser'=>$userid]);
    return true;
  }

  /******************************************************************
   * List groups (SA only)
   *    $id = null      List of groups
   *    $id = ......    Group by UUID
   ******************************************************************/
  public function group_list($id = null) {
    if (!$this->SA())  { return false; }
    if ($id == null) {
      return $this->db->select('id, groupname, ldapcn, radiusattr', 'sessman_group');
    }
    else {
      return $this->db->select('groupname, ldapcn, radiusattr', 'sessman_group', [':id'=>$id]);
    }
  }

  /******************************************************************
   * Create or update group   (SA only)
   *    $id = null      Create new group
   *    $id = ......    Update group by UUID
   *    $data = [
   *      "groupname":    "string",
   *      "ldapcn":       "string",
   *      "radiusattr"    "string"
   *    ]
   *  Automatically assign group
   *    ldapcn        when matching LDAP group
   *    radiusattr    when in group list (radius attribute, comma seperated)
   ******************************************************************/
  public function group_save($id, $data) {
    if (!$this->SA())  { return false; }

    // Prepare SQL statement (MySQL/pSQL)
    $dbparams = [];
    $fieldlist = ['groupname', 'ldapcn', 'radiusattr'];
    foreach ($fieldlist as $key) {
      if (isset($data[$key])) {
        $dbparams[":$key"] = $data[$key];
      }
    }

    // Create new group
    if ($id == null) {
      $id = $this->db->insert("sessman_group", $dbparams);
      if (isset($data['users'])) {
        foreach($data['users'] as $userid) { $this->groupmember_save($id, ['id'=>$userid]); }
      }
      return [ ['id'=>$id] ];
    }
    // Update group
    else {
      $this->db->update("sessman_group", $dbparams, [':id'=>$id]);
      if (isset($data['users'])) {
        $this->db->delete('sessman_usergroups', [':smgroup'=>$id]);
        foreach($data['users'] as $userid) { $this->groupmember_save($id, ['id'=>$userid]); }
      }
      return [[]];
    }
  }

  /******************************************************************
   * Delete group (SA only)
   ******************************************************************/
  public function group_delete($id) {
    if (!$this->SA())  { return false; }
    // delete group
    $this->db->delete('sessman_usergroups', [':id'=>$id]);
    $this->db->delete('sessman_group', [':id'=>$id]);
    return true;
  }

  /******************************************************************
   * List group members (SA only)
   *    $id = ......    Members by group UUID
   ******************************************************************/
  public function groupmember_list($id = null) {
    if (!$this->SA())  { return false; }
    $dbquery = '
      SELECT
        su.id,
        su.username,
        su.firstname,
        su.lastname,
        su.active
      FROM sessman_usergroups sg
      LEFT JOIN sessman_user su ON su.id = sg.smuser
      WHERE sg.smgroup = :id
    ';
    $result = [];
    foreach ($this->db->query($dbquery, [':id'=>$id]) as $dbrow) {
      $result[] = [
        'id'=>$dbrow->id,
        'username'=>$dbrow->username,
        'firstname'=>$dbrow->firstname,
        'lastname'=>$dbrow->lastname,
        'active'=>($dbrow->active || $dbrow->active == '1') ? true : false
      ];
    }
    return $result;
  }

  /******************************************************************
   * Save user group (SA only)
   ******************************************************************/
  public function groupmember_save($groupid, $data) {
    if (!$this->SA())  { return false; }
    return $this->db->query('INSERT INTO sessman_usergroups VALUES (:smuser, :smgroup)', [':smuser'=>$data['id'], ':smgroup'=>$groupid]);
  }

}
