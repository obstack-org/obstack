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
    session_name($sessionname);
    session_start();
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
    $dbqtoken = ($this->db->driver() == 'mysql') ? 'PASSWORD(:token)' : 'crypt(:token, ut.token)';
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
      foreach ($this->db->query('SELECT smgroup FROM sessman_usergroups WHERE smuser=:smuser', [':smuser'=>$user[0]->id]) as $dbrec) {
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
    // MySQL/pSQL
    $dbqsecret = ($this->db->driver() == 'mysql') ? 'secret=PASSWORD(:secret)' : 'secret=crypt(:secret, secret)';
    // Determine session state
    $user = $this->db->query(
      "SELECT id, username, firstname, lastname, totp, totp_secret, tokens, sa FROM sessman_user WHERE username=:username AND $dbqsecret AND active=true",
      [':username'=>strtolower($username), ':secret'=>$secret]
    );
    if (!empty($user)) {
      $user[0]->groups = [];
      foreach ($this->db->query('SELECT smgroup FROM sessman_usergroups WHERE smuser=:smuser', [':smuser'=>$user[0]->id]) as $dbrec) {
        $user[0]->groups[] = $dbrec->smgroup;
      }
      $user[0]->ext = false;
      // TOTP
      $user[0]->tfc = false;
      $totp_default = false;
      foreach ($this->db->query('SELECT value FROM setting_decimal WHERE name=\'session_totp-default\'', []) as $dbrow) {
        if ($dbrow->value == '1') {
          $totp_default = true;
        }
      }
      if ($user[0]->totp || $totp_default) {
        $user[0]->tfc = true;
        if ($user[0]->totp_secret == null) {
          $totp_secret = TOTP::genSecret(56);
          $user[0]->totp_secret = $totp_secret['secret'];
          $this->db->query('UPDATE sessman_user SET totp_secret=:totp_secret WHERE id=:id', [':id'=>$user[0]->id, ':totp_secret'=>$user[0]->totp_secret]);
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
      foreach ($this->db->query('SELECT id, ldapcn FROM sessman_group', []) as $dbrec) {
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
      foreach ($this->db->query('SELECT id, radiusattr FROM sessman_group', []) as $dbrec) {
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
      return $this->db->query('SELECT id, username, firstname, lastname, active, tokens, sa FROM sessman_user ORDER BY username', []);
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
    $list = $this->db->query('SELECT id, username, totp, firstname, lastname, active, tokens, sa FROM sessman_user WHERE id=:id', ['id'=>$id]);
    if ($self) {
      unset($list[0]->id);
      unset($list[0]->active);
    }
    return $list;
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
    // // TOTP Reset
    // if (isset($data['totp_reset'])) {
    //   $data['totp_secret'] = '';
    // }
    // Prepare SQL statement (MySQL/pSQL)
    $dbqcol = '';
    $dbqval = '';
    $dbqupd = '';
    $dbparams = [];
    foreach ($fieldlist as $key) {
      if (isset($data[$key])) {
        if ($key == 'password') {
          $dbqcol .= ', secret';
          $dbqval .= ', crypt(:password, gen_salt(\'bf\'))';
          $dbqupd .= ', secret=crypt(:password, gen_salt(\'bf\'))';
          if ($this->db->driver() == 'mysql') {
            $dbqupd = ', secret=PASSWORD(:secret)';
          }
        }
        elseif ($key=='totp_reset') {
          $dbqupd .= ($data[$key]==1)?", totp_secret=NULL":'';
        }
        else {
          $dbqcol .= ", $key";
          $dbqval .= ", :$key";
          $dbqupd .= ", $key=:$key";
        }
        if (in_array($key, ['active', 'tokens', 'sa'])) {
          $dbparams[$key] = $data[$key] ? 'true' : 'false';
        }
        elseif (!in_array($key, ['totp_reset'])) {
          $dbparams[$key] = $data[$key];
        }
      }
    }
    // Create new user
    if ($id == null) {
      $dbqcol = mb_substr($dbqcol,2);
      $dbqval = mb_substr($dbqval,2);
      $id = $this->db->query("INSERT INTO sessman_user ($dbqcol) VALUES ($dbqval) RETURNING id", $dbparams)[0]->id;
      if (isset($data['groups'])) {
        foreach($data['groups'] as $groupid) { $this->usergroup_save($id, ['id'=>$groupid]); }
      }
      return [ ['id'=>$id] ];
    }
    // Update user
    else {
      $dbqupd = mb_substr($dbqupd,2);
      $dbparams['id'] = $id;
      $result = [[]];
      if (strlen($dbqupd)>0) {
        $result = $this->db->query("UPDATE sessman_user SET $dbqupd WHERE id=:id", $dbparams);
      }
      if (isset($data['groups'])) {
        $this->db->query('DELETE FROM sessman_usergroups WHERE smuser=:smuser', [':smuser'=>$id]);
        foreach($data['groups'] as $groupid) { $this->usergroup_save($id, ['id'=>$groupid]); }
      }
      return $result;
    }
  }

  /******************************************************************
   * Delete user
   ******************************************************************/
  public function user_delete($id) {
    if ((!$this->SA()) || ($id == $_SESSION['sessman']['userid'])) { return false; }
    // delete user
    $this->db->query('DELETE FROM sessman_usertokens WHERE smuser=:id', [':id'=>$id]);
    $this->db->query('DELETE FROM sessman_usergroups WHERE smuser=:id', [':id'=>$id]);
    $count = count($this->db->query('DELETE FROM sessman_user WHERE id=:id RETURNING *', [':id'=>$id]));
    return $count > 0;
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
    return $this->db->query('INSERT INTO sessman_usergroups VALUES (:smuser, :smgroup)', [':smuser'=>$userid, ':smgroup'=>$data['id']]);
  }

  /******************************************************************
   * Delete user group (SA only)
   ******************************************************************/
  public function usergroup_delete($userid, $groupid) {
    if (!$this->SA())  { return false; }
    if ($userid == 'self') { return false; }
    if (count($this->db->query('SELECT tokens FROM sessman_user WHERE id=:id AND tokens = true', [':id'=>$userid])) == 0) {
      return false;
    }
    $count = count($this->db->query('DELETE FROM sessman_usergroups WHERE smuser=:smuser AND smgroup=:smgroup RETURNING *', [':smuser'=>$userid, ':smgroup'=>$groupid]));
    return $count > 0;
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
    if (count($this->db->query('SELECT tokens FROM sessman_user WHERE id=:id AND tokens = true', [':id'=>$userid])) == 0) {
      return [];
    }
    // Token(s)
    $dbquery = 'SELECT id, name, TO_CHAR(expiry,\'YYYY-MM-DD HH24:MI\') as expiry FROM sessman_usertokens WHERE smuser=:smuser';
    $dbparams = [':smuser'=>$userid];
    if ($tokenid != null) {
      $dbquery = str_replace('SELECT id, ', 'SELECT ', $dbquery);
      $dbquery .= ' AND id=:id';
      $dbparams['id'] = $tokenid;
    }
    return $this->db->query($dbquery, $dbparams);
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
    if (count($this->db->query('SELECT tokens FROM sessman_user WHERE id=:id AND tokens = true', [':id'=>$userid])) == 0) {
      return false;
    }
    // MySQL/pSQL
    $dbqtoken = 'crypt(:token, gen_salt(\'bf\'))';
    if ($this->db->driver() == 'mysql') {
      $dbqtoken = 'PASSWORD(:token)';
    }
    if ($tokenid == null) {
      // Register token
      $token = bin2hex(random_bytes(random_int(16, 20)));
      $result = $this->db->query(
        "INSERT INTO sessman_usertokens (smuser, token, name, expiry) VALUES (:smuser, $dbqtoken, :name, :expiry) RETURNING id",
        [':smuser'=>$userid, ':token'=>$token, ':name'=>$name, ':expiry'=>$expiry]
      );
      if (!empty($result)) {
        return [ 'id'=>$result[0]->id, 'token'=>$token ];
      }
    }
    else {
      // Update token
      $this->db->query(
        'UPDATE sessman_usertokens SET name=:name, expiry=:expiry WHERE id=:id AND smuser=:smuser',
        [':id'=>$tokenid, ':smuser'=>$userid, ':name'=>$name, ':expiry'=>$expiry]
      );
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
    if (count($this->db->query('SELECT tokens FROM sessman_user WHERE id=:id AND tokens = true', [':id'=>$userid])) == 0) {
      return false;
    }
    // Delete token
    $count = count($this->db->query('DELETE FROM sessman_usertokens WHERE id=:id AND smuser=:smuser RETURNING *', [':id'=>$tokenid, ':smuser'=>$userid]));
    return $count > 0;
  }

  /******************************************************************
   * List groups (SA only)
   *    $id = null      List of groups
   *    $id = ......    Group by UUID
   ******************************************************************/
  public function group_list($id = null) {
    if (!$this->SA())  { return false; }
    $dbquery = 'SELECT id, groupname, ldapcn, radiusattr FROM sessman_group';
    $dbparams = [];
    if ($id != null) {
      $dbquery = str_replace('SELECT id, ', 'SELECT ', $dbquery);
      $dbquery .= ' WHERE id=:id';
      $dbparams['id'] = $id;
    }
    return $this->db->query($dbquery, $dbparams);
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
    $dbqcol = '';
    $dbqval = '';
    $dbqupd = '';
    $dbparams = [];
    $fieldlist = ['groupname', 'ldapcn', 'radiusattr'];
    foreach ($fieldlist as $key) {
      if (isset($data[$key])) {
        $dbqcol .= ", $key";
        $dbqval .= ", :$key";
        $dbqupd .= ", $key=:$key";
        $dbparams[$key] = $data[$key];
      }
    }
    // Create new group
    if ($id == null) {
      $dbqcol = mb_substr($dbqcol,2);
      $dbqval = mb_substr($dbqval,2);
      $id = $this->db->query("INSERT INTO sessman_group ($dbqcol) VALUES ($dbqval) RETURNING id", $dbparams)[0]->id;
      if (isset($data['users'])) {
        foreach($data['users'] as $userid) { $this->groupmember_save($id, ['id'=>$userid]); }
      }
      return [ ['id'=>$id] ];
    }
    // Update group
    else {
      $dbqupd = mb_substr($dbqupd,2);
      $dbparams['id'] = $id;
      if (isset($data['users'])) {
        $this->db->query('DELETE FROM sessman_usergroups WHERE smgroup=:smgroup', [':smgroup'=>$id]);
        foreach($data['users'] as $userid) { $this->groupmember_save($id, ['id'=>$userid]); }
      }
      return $this->db->query("UPDATE sessman_group SET $dbqupd WHERE id=:id", $dbparams);
    }
  }

  /******************************************************************
   * Delete group (SA only)
   ******************************************************************/
  public function group_delete($id) {
    if (!$this->SA())  { return false; }
    // delete group
    $this->db->query('DELETE FROM sessman_usergroups WHERE smgroup=:id', [':id'=>$id]);
    $count = count($this->db->query('DELETE FROM sessman_group WHERE id=:id RETURNING *', [':id'=>$id]));
    return $count > 0;
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
    return $this->db->query($dbquery, [':id'=>$id]);
  }

  /******************************************************************
   * Save user group (SA only)
   ******************************************************************/
  public function groupmember_save($groupid, $data) {
    if (!$this->SA())  { return false; }
    return $this->db->query('INSERT INTO sessman_usergroups VALUES (:smuser, :smgroup)', [':smuser'=>$data['id'], ':smgroup'=>$groupid]);
  }

}
