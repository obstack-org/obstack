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
    'title', 'session_timeout'
  ];
  private $settings_admin = [
    'version_check', 'totp_default_enabled'
  ];
  private $settings_admin_edit = [
    'ldap_enabled', 'ldap_host', 'ldap_port', 'ldap_userdn', 'ldap_group-auth', 'ldap_group-sa',
    'radius_enabled', 'radius_host', 'radius_port', 'radius_secret', 'radius_attr', 'radius_group-auth', 'radius_group-sa'
  ];
  private $objson = 'https://www.obstack.org/resources/obstack.json';

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
    if ($this->options == null || !isset($this->options['sc_encryptionkey'])) {
      return true;
    }
    else {
      if (strlen($this->options['sc_encryptionkey']) < 12) {
        return false;
      }
      $hky = [ null, null, null ];
      $sky = $this->options['sc_encryptionkey'];
      foreach ($this->db->query_buffered('sthky', 'SELECT name, value FROM setting_varchar WHERE name LIKE \'hky_ckey%\' ORDER BY name', []) as $dbrow) {
        $hky[intval(mb_substr($dbrow->name,-1))] = $dbrow->value;
      }
      if ($hky[1] == null) {
        $hky[1] = basebq::encode(basebq::rstr(9,14));
        $this->db->query('INSERT INTO setting_varchar (name, value) values (\'hky_ckey1\', :key)', [ ':key'=>$hky[1] ]);
      }
      if ($hky[2] == null) {
        $hky[2] = hash('sha384', $sky.$hky[1]);
        $this->db->query('INSERT INTO setting_varchar (name, value) values (\'hky_ckey2\', :key)', [ ':key'=>$hky[2] ]);
      }
      $this->basepass = basebq::pstr(basebq::decode($hky[1])).basebq::pstr($sky);
      return $hky[2] == hash('sha384', $sky.$hky[1]);
    }
  }

  /******************************************************************
   * Load Configuration (if not already present in session)
   ******************************************************************/

  private function load() {
    if (!isset($_SESSION['settings'])) {
      $_SESSION['settings'] = [];
      foreach ($this->db->query('SELECT id, name, value FROM setting_varchar', []) as $rec) {
        $_SESSION['settings'][$rec->name] = $rec;
      };
      foreach ($this->db->query('SELECT id, name, value FROM setting_decimal', []) as $rec) {
        $rec->value = (int)$rec->value;
        $_SESSION['settings'][$rec->name] = $rec;
      };
    }
  }

  /******************************************************************
   * List Configuration
   * ==================
   *  $public     Public only (eg for non authorized users)
   *  $sa         SuperAdmin
   ******************************************************************/

  public function list($public=true, $sa=false) {
    $this->load();
    $result = [ 'settings'=>[] ];
    $cfgset = $this->settings_public;
    if (!$public) {
      $cfgset = array_merge($cfgset, $this->settings_private);
      $result['navigation'] = $this->db->select('id, parent, name','ntree');
      if ($this->options != null && isset($this->options['sc_encryptionkey'])) {
        if (!isset($_SESSION['obstack'])) { $_SESSION['obstack'] = []; }
        if (!isset($_SESSION['obstack']['basebq'])) {
          $_SESSION['obstack']['basebq'] = $this->basepass;
        }
        setcookie('obstack_basebq',basebq::encode($_SESSION['obstack']['basebq']), [ 'expires'=>time()+2, 'samesite'=>'strict', 'path'=>'/' ]);
      }
    }
    // Admin fields (general)
    if ($sa) {
      $cfgset = array_merge($cfgset, $this->settings_admin);
    }
    // Admin fields (config module)
    if ($sa && $this->display == 'edit') {
      $cfgset = array_merge($cfgset, $this->settings_admin_edit);
    }
    // Gather settings
    $this->load();
    foreach ($cfgset as $cfgname) {
      if (isset($_SESSION['settings'][$cfgname])) {
        $result['settings'][] = $_SESSION['settings'][$cfgname];
      }
    }

    // Version check
    if ($sa) {
      if (!isset($_SESSION['settings']['version_check'])) {
        $_SESSION['settings']['version_check'] = (object)[ 'value'=>1 ];
        $this->db->query("INSERT INTO setting_decimal (name, value) VALUES ('version_check', 1)", []);
      }
      $result['version'] = [
        'notify'=>$_SESSION['settings']['version_check']->value
      ];
      if (!isset($_SESSION['obstack']['version']) && $_SESSION['settings']['version_check']->value == 1) {
        $_SESSION['obstack']['version'] = 0;
        try {
          $scc = stream_context_create(array('http'=>array('timeout'=>2)));
          $json = @file_get_contents($this->objson, false, $scc);
          $obst = json_decode($json);
          if (isset($obst->obstack->version)) {
            $_SESSION['obstack']['version'] = intval(str_replace('.', '', $obst->obstack->version));
          }
        } catch (Exception $e) { unset($e); }
      }
      if (isset($_SESSION['obstack']['version'])) {
        $result['version']['available'] = $_SESSION['obstack']['version'];
      }
    }

    return $result;
  }

  /******************************************************************
   * Get setting
   * ===========
   *  $public     Public only (eg for non authorized users)
   *  $sa         SuperAdmin
   ******************************************************************/

  public function get($key) {
    if (empty($_SESSION['settings'])) {
      $this->load();
    }
    return $_SESSION['settings'][$key]->value;
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
      foreach ($this->db->select('id','ntree') as $dbrow) {
        $xlist[] = $dbrow->id;
      }
      // New map
      $tmpid = [];
      foreach ($data['navigation'] as $rec) {
        if (!isset($rec['id']) || $rec['id'] == null || strlen($rec['id'])!=36 || (strlen($rec['id'])==36 && preg_match('/^[a-f\d]{8}(-[a-f\d]{4}){4}[a-f\d]{8}$/i', $rec['id']) !== 1)) {
          $tmpid[$rec['id']] = $this->db->insert('ntree', [':name'=>$rec['name'] ]);
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
        $this->db->update('ntree', [ ':name'=>$rec['name'], ':parent'=>$rec['parent']], [ ':id'=>$rec['id'] ]);
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

      // Validate allowed setting names and values
      $mlist = [ 'varchar'=>[], 'decimal'=>[] ];
      $settings = [];
      foreach ($data['settings'] as $rec) {
        $settings[$rec['name']] = $rec['value'];
        if (in_array($rec['name'], array_merge($this->settings_public, $this->settings_private, $this->settings_admin, $this->settings_admin_edit))) {
          if (in_array(end(explode('_', $rec['name'])), [ 'enabled', 'timeout', 'check', 'port', 'attr', 'sidebar-width' ])) {
            if (mb_substr($rec['name'],-8) == '_timeout' && intval($rec['value']) < 30) {
              $settings[$rec['name']] = 30;
            }
            $mlist['decimal'][] = $rec['name'];
          }
          else {
            if ((mb_substr($rec['name'],0,4) == 'css_' && (
              ((mb_substr($rec['name'],-6) == '-color' || mb_substr($rec['name'],-11) == '-background') && preg_match('/^#[0-9a-f]{6}$/', $rec['value'])) ||
              (mb_substr($rec['name'],-6) == '-width' && preg_match('/^[1-9][0-9]{2}$/', $rec['value']))
            )) ||
              mb_substr($rec['name'],0,4) != 'css_'
            ) {
              $mlist['varchar'][] = $rec['name'];
            }
          }
        }
      }

      // Process data
      foreach(array_keys($mlist) as $table) {
        if (count($mlist[$table]) > 0) {
          $xlist = [];
          $dbqin = $this->list2in($mlist[$table]);
          $dbqvalue = ($table == 'decimal') ? 'round(value)::text as value' : 'value';
          if ($this->db->driver()->mysql) {
            $dbqvalue = str_replace('::text', '', $dbqvalue);
          }
          foreach($this->db->query("SELECT id, name, $dbqvalue FROM setting_$table WHERE name IN ($dbqin->marks)", $dbqin->params) as $dbrow) {
            $xlist[$dbrow->name] = $dbrow->value;
          }
          // Prepare insert, process update
          $dbc = 0;
          $dbq = (object)[ 'insert'=>[], 'params'=>[] ];
          foreach ($mlist[$table] as $rec) {
            if (!isset($xlist[$rec])) {
              $dbq->insert[] = "(:n$dbc, :v$dbc)";
              $dbq->params[":n$dbc"] = $rec;
              $dbq->params[":v$dbc"] = $settings[$rec];
              $dbc++;
            }
            elseif ($xlist[$rec] != $settings[$rec]) {
              $this->db->update("setting_$table", [ ':value'=>$settings[$rec] ], [ ':name'=>$rec ]);
            }
          }
          // Process insert
          if (count($dbq->insert) > 0) {
            $dbq->insert = implode(',', $dbq->insert);
            $this->db->query("INSERT INTO setting_$table (name, value) VALUES $dbq->insert", $dbq->params);
          }
        }
      }

      unset($_SESSION['settings']);

    }

    return [];
  }

}
