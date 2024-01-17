<?php
/******************************************************************
 *
 * mod_config($db)
 *  -> open($filter)
 *  -> save($data)
 *
 ******************************************************************/

require_once 'class_basebq.php';

class mod_conf {

  private $db;
  private $display;
  private $options;
  private $basepass;
  private $settings_public = [
    'css_titlebar-color', 'css_titlebar-background',
    'css_sidebar-width', 'css_sidebar-color', 'css_sidebar-background',
    'css_content-color', 'css_content-background'
  ];
  private $settings_private = [
    'title','session_timeout'
  ];
  private $settings_admin = [
    'user_totp-default',
    'ldap_enabled', 'ldap_host', 'ldap_port', 'ldap_userdn', 'ldap_group-auth', 'ldap_group-sa',
    'radius_enabled', 'radius_host', 'radius_port', 'radius_secret', 'radius_attr', 'radius_group-auth', 'radius_group-sa'
  ];

  /******************************************************************
   * Initialize
   ******************************************************************/
  public function __construct($db, $obstack_conf=null) {
    $this->db = $db;
    $this->options = $obstack_conf;
    if (isset($_GET['display'])) { $this->display = $_GET['display']; }
  }

  /******************************************************************
   * Convert array to marks and params for SQL query:
   * $db->query(
   *    "... WHERE value IN ($list2in->marks)",
   *    $list2in->params )
   ******************************************************************/
  private function list2in($list, $prefix='i') {
    $result = (object)[ 'marks'=>[], 'params'=>[] ];
    foreach ($list as $id=>$value) {
      $result->marks[] = ":$prefix$id";
      $result->params[":$prefix$id"] = $value;
    }
    $result->marks = implode(',', $result->marks);
    return $result;
  }

  /******************************************************************
   * Get tree depth
   ******************************************************************/

  private function getdepth($dtree, $id=null, $depth=0) {
    foreach($dtree as $rec) {
      if ($rec['id'] == $id && $rec['parent'] != null && $depth < 12) {
        $depth = $this->getdepth($dtree, $rec['parent'], $depth+1);
      }
    }
    return $depth;
  }

  /******************************************************************
   * Verify Configuration
   ******************************************************************/

  public function verify() {
    if ($this->options == null || $this->options['sc_encryptionkey'] == null) {
      return true;
    }
    else {
      if (strlen($this->options['sc_encryptionkey']) < 12) {
        return false;
      }
      $hky = [ null, null, null ];
      $sky = $this->options['sc_encryptionkey'];
      foreach ($this->db->query('SELECT name, value FROM settings WHERE name LIKE \'hky_ckey%\' ORDER BY name', []) as $dbrow) {
        $hky[intval(substr($dbrow->name,-1))] = $dbrow->value;
      }
      if ($hky[1] == null) {
        $hky[1] = basebq::encode(basebq::rstr(9,14));
        $this->db->query('INSERT INTO settings (name, value) values (\'hky_ckey1\', :key)', [ ':key'=>$hky[1] ]);
      }
      if ($hky[2] == null) {
        $hky[2] = hash('sha384', $sky.$hky[1]);
        $this->db->query('INSERT INTO settings (name, value) values (\'hky_ckey2\', :key)', [ ':key'=>$hky[2] ]);
      }
      $this->basepass = basebq::pstr(basebq::decode($hky[1])).basebq::pstr($sky);
      return ($hky[2] == hash('sha384', $sky.$hky[1]));
    }
  }

  /******************************************************************
   * List Configuration
   * ==================
   *  $public     Public only (eg for non authorized users)
   *  $sa         SuperAdmin
   ******************************************************************/

  public function list($public=true, $sa=false) {
    $result = [];
    $dbqin = $this->list2in($this->settings_public);
    $result['settings'] = $this->db->query("SELECT id, name, value FROM settings WHERE name IN ($dbqin->marks) ORDER BY name", $dbqin->params);
    if (!$public) {
      $dbqin = $this->list2in($this->settings_private);
      $result['settings'] = array_merge($result['settings'], $this->db->query("SELECT id, name, value FROM settings WHERE name IN ($dbqin->marks) ORDER BY name", $dbqin->params));
      $result['navigation'] = $this->db->query("SELECT id, parent, name FROM ntree", []);
      if ($this->options != null && $this->options['sc_encryptionkey'] != null) {
        if (!isset($_SESSION['obstack'])) { $_SESSION['obstack'] = []; }
        if (!isset($_SESSION['obstack']['basebq'])) {
          $_SESSION['obstack']['basebq'] = $this->basepass;
        }
        setcookie('obstack_basebq',basebq::encode($_SESSION['obstack']['basebq']), [ 'expires'=>time()+10, 'samesite'=>'strict', 'path'=>'/' ]);
      }
    }
    if ($sa && $this->display == 'edit') {
      $dbqin = $this->list2in($this->settings_admin);
      $result['settings'] = array_merge($result['settings'], $this->db->query("SELECT id, name, value FROM settings WHERE name IN ($dbqin->marks) ORDER BY name", $dbqin->params));
    }
    return $result;
  }

  /******************************************************************
   * Save Configuration
   ******************************************************************/

  public function save($data) {

    // Navigation
    if (isset($data['navigation'])) {
      $xlist = [];
      $mlist = [];

      foreach ($data['navigation'] as $rec) {
        $depth = $this->getdepth($data['navigation'], $rec['id']);
        if ($depth > 3) {
          return false;
        }
      }

      // Current maps
      foreach ($this->db->query("SELECT id FROM ntree", []) as $dbrow) {
        $xlist[] = $dbrow->id;
      }
      // New map
      $tmpid = [];
      foreach ($data['navigation'] as $rec) {
        if (!isset($rec['id']) || $rec['id'] == null || strlen($rec['id'])!=36 || (strlen($rec['id'])==36 && preg_match('/^[a-f\d]{8}(-[a-f\d]{4}){4}[a-f\d]{8}$/i', $rec['id']) !== 1)) {
          $tmpid[$rec['id']] = $this->db->query('INSERT INTO ntree (name) VALUES (:name) RETURNING id', [':name'=>$rec['name'] ])[0]->id;
        }
      }
      // Update temp id
      for ($i = 0; $i < count($data['navigation']); $i++) {
        if (strlen($data['navigation'][$i]['id'])!=36 || preg_match('/^[a-f\d]{8}(-[a-f\d]{4}){4}[a-f\d]{8}$/i', $data['navigation'][$i]['id']) !== 1) {
          $data['navigation'][$i]['id'] = $tmpid[$data['navigation'][$i]['id']];
        }
        if (strlen($data['navigation'][$i]['parent'])!=36 || preg_match('/^[a-f\d]{8}(-[a-f\d]{4}){4}[a-f\d]{8}$/i', $data['navigation'][$i]['parent']) !== 1) {
          $data['navigation'][$i]['parent'] = $tmpid[$data['navigation'][$i]['parent']];
        }
      }
      // Inventory, update parents
      foreach ($data['navigation'] as $rec) {
        $mlist[] = $rec['id'];
        $this->db->query('UPDATE ntree SET parent = :parent WHERE id = :id', [ ':parent'=>$rec['parent'], ':id'=>$rec['id'] ]);
      }
      // Determine and remove deleted maps
      $rlist = array_diff($xlist, $mlist);
      $dbqin = $this->list2in($rlist);
      if (count($dbqin->params) > 0) {
        $this->db->query("UPDATE objtype SET map=null WHERE map IN ($dbqin->marks)", $dbqin->params);
        $this->db->query("UPDATE ntree SET parent=null WHERE parent IN ($dbqin->marks)", $dbqin->params);
        $this->db->query("DELETE FROM ntree WHERE id IN ($dbqin->marks)", $dbqin->params);
      }
    }

    // Settings
    if (isset($data['settings'])) {
      foreach ($data['settings'] as $rec) {
        if (in_array($rec['name'], array_merge($this->settings_public, $this->settings_private, $this->settings_admin))) {
          $allow = false;
          if ($rec['name'] == 'title') {
            $allow = true;
          }
          if (substr($rec['name'],0,4) == 'css_') {
            if ((substr($rec['name'],-6) == '-color' || substr($rec['name'],-11) == '-background') && preg_match('/^#[0-9a-f]{6}$/', $rec['value'])) {
              $allow = true;
            }
            if (substr($rec['name'],-6) == '-width' && preg_match('/^[1-9][0-9]{2}px$/', $rec['value'])) {
              $allow = true;
            }
          }
          if (substr($rec['name'],0,5) == 'totp_') {
            $allow = true;
            $rec['value'] = ($rec['value']=='1') ? '1' : '0';
          }
          if ($allow) {
            $this->db->query(
              "INSERT INTO settings (name, value) VALUES (:name, :value) ON CONFLICT (name) DO UPDATE SET value = :value RETURNING id",
              [ ':name'=>$rec['name'], ':value'=>$rec['value'] ]
            );
          }
        }
      }
    }

    return [];
  }

}
