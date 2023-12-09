<?php
/******************************************************************
 *
 * mod_config($db)
 *  -> open($filter)
 *  -> save($data)
 *
 ******************************************************************/

class mod_conf {

  private $db;
  private $settings_base = [
    'title', 'db_version',
    'css_titlebar-color', 'css_titlebar-background',
    'css_sidebar-width', 'css_sidebar-color', 'css_sidebar-background',
    'css_content-color', 'css_content-background'
  ];

  /******************************************************************
   * Initialize
   ******************************************************************/
  public function __construct($db) {
    $this->db = $db;
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
   * List Configuration
   * ==================
   *  $filter     Limit settings (eg for non authorized users)
   ******************************************************************/

  public function list($filter=true) {
    $result = [];
    $dbqin = $this->list2in($this->settings_base);
    $result['settings'] = $this->db->query("SELECT id, name, value FROM settings WHERE name IN ($dbqin->marks)", $dbqin->params);
    if (!$filter) {
      $result['navigation'] = $this->db->query("SELECT id, parent, name FROM ntree", []);
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
        $this->db->query("UPDATE objtype SET ntree=null WHERE ntree IN ($dbqin->marks)", $dbqin->params);
        $this->db->query("UPDATE ntree SET parent=null WHERE parent IN ($dbqin->marks)", $dbqin->params);
        $this->db->query("DELETE FROM ntree WHERE id IN ($dbqin->marks)", $dbqin->params);
      }
    }

    // Settings
    if (isset($data['settings'])) {
      foreach ($data['settings'] as $rec) {
        if (in_array($rec['name'], $this->settings_base)) {
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
