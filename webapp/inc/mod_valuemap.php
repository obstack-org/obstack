<?php
/******************************************************************
 *
 * api_valuemap($db)
 *  -> list($id)
 *  -> save($id, $data)
 *  -> delete($id)
 *  -> value_list($vmid, $id)
 *  -> value_save($vmid, $id, $data)
 *  -> value_delete($vmid, $id)
 *
 ******************************************************************/

class mod_valuemap {

  private $db;

  /******************************************************************
   * Initialize
   ******************************************************************/
  function __construct($db) {
    $this->db = $db;
  }

  /******************************************************************
   * Boolean as string
   ******************************************************************/
  private function bool2str($var) {
    if ($var) return 'true';
    if ($var == '1') return 'true';
    return 'false';
  }

  /******************************************************************
   * List valuemaps
   *  [ { id, name, prio }, {} ]
   ******************************************************************/
  function list($id = null) {
    if ($id == null) {
      return $this->db->query('SELECT id, name FROM valuemap ORDER BY name', []);
    }
    else {
      return $this->db->query('SELECT name, prio FROM valuemap WHERE id=:id', [':id'=>$id])[0];
    }
  }

  /******************************************************************
   * Save valuemap (create/update)
   ******************************************************************/
  function save($id, $data) {
    $dbparams = [];
    // Valuemap configuration
    if (isset($data['name'])) { $dbparams['name'] = $data['name']; }
    if (isset($data['prio'])) { $dbparams['prio'] = $this->bool2str($data['prio']); }
    // Valuemap create / update
    if ($id == null) {
      $id = $this->db->query('INSERT INTO valuemap (name, prio) VALUES (:name, :prio) RETURNING id', $dbparams)[0]->id;
      $result = $id;
    }
    else {
      $dbparams[':id'] = $id;
      $result = $this->db->query('UPDATE valuemap SET name=:name, prio=:prio WHERE id=:id', $dbparams);
    }
    // Valuemap values
    if (isset($data['value'])) {
      // Values from HTTP request
      $prio = 1;
      $htlist = [];
      foreach ($data['value'] as $rec) {
        $tmpid = $this->value_save($id, $rec['id'], ['name'=>$rec['name'], 'prio'=>$prio])[0]->id;
        if ($rec['id'] == null) {
          $rec['id'] = $tmpid;
        }
        array_push($htlist, $rec['id']);
        $prio++;
      }
      // Values from database
      $dblist = [];
      foreach ($this->db->query('SELECT id FROM valuemap_value WHERE valuemap=:id', [':id'=>$id]) as $dbrec) {
        array_push($dblist, $dbrec->id);
      }
      // Delete values
      sort($htlist);
      sort($dblist);
      foreach (array_diff($dblist, $htlist) as $rec) {
        $this->value_delete($id, $rec);
      }
    }
    return $result;
  }

  /******************************************************************
   * Delete valuemap
   ******************************************************************/
  function delete($id) {
    $this->db->query('DELETE FROM valuemap_value WHERE valuemap=:valuemap', [':valuemap'=>$id]);
    $count = count($this->db->query('DELETE FROM valuemap WHERE id=:id RETURNING *', [':id'=>$id]));
    return ($count > 0);
  }

  /******************************************************************
   * value open
   ******************************************************************/
  function value_list($vmid, $id = null) {
    if ($id == null) {
      $vmconfig = $this->db->query('SELECT prio FROM valuemap WHERE id=:vmid', [':vmid'=>$vmid])[0];
      $priosort = 'name';
      if ($vmconfig->prio) {
        $priosort = 'prio';
      }
      return $this->db->query("SELECT id, name FROM valuemap_value AS vmo WHERE valuemap=:vmid ORDER BY $priosort", [':vmid'=>$vmid]);
    }
    else {
      return $this->db->query('SELECT name FROM valuemap_value AS vmo WHERE valuemap=:vmid AND id=:id', [':vmid'=>$vmid, ':id'=>$id]);
    }
  }

  /******************************************************************
   * value save
   ******************************************************************/
  function value_save($vmid, $id, $data) {
    $dbqprio = '';
    $dbparams = [':vmid'=>$vmid, ':name'=>$data['name']];
    if ($id == null) {
      if (array_key_exists('prio', $data)) {
        $dbqprio1 = 'prio,';
        $dbqprio2 = ':prio,';
        $dbparams[':prio'] = $data['prio'];
      }
      return $this->db->query("INSERT INTO valuemap_value (valuemap, $dbqprio1 name) VALUES (:vmid, $dbqprio2 :name) RETURNING id", $dbparams);
    }
    else {
      $dbparams[':id'] = $id;
      if (array_key_exists('prio', $data)) {
        $dbqprio = ',prio=:prio';
        $dbparams[':prio'] = $data['prio'];
      }
      return $this->db->query("UPDATE valuemap_value SET name=:name $dbqprio WHERE valuemap=:vmid AND id=:id", $dbparams);
    }
  }

  /******************************************************************
   * value delete
   ******************************************************************/
  function value_delete($vmid, $id) {
    $count = count($this->db->query('DELETE FROM valuemap_value WHERE valuemap=:vmid AND id=:id RETURNING *', [':id'=>$id, ':vmid'=>$vmid]));
    return ($count > 0);
  }

}