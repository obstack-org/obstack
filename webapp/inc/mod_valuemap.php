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
  public function __construct($db) {
    $this->db = $db;
  }

  /******************************************************************
   * List valuemaps
   *  [ { id, name, prio }, {} ]
   ******************************************************************/
  public function list($id = null) {
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
  public function save($id, $data) {

    // Prepare
    $dbq = (object)[ 'params'=>[] ];
    if (isset($data['name'])) { $dbq->params['name'] = $data['name']; }
    if (isset($data['prio'])) { $dbq->params['prio'] = ($data['prio'] || $data['prio'] == '1') ? 'true' : 'false'; }

    // Create
    if ($id == null) {
      $id = $this->db->query('INSERT INTO valuemap (name, prio) VALUES (:name, :prio) RETURNING id', $dbq->params)[0]->id;
      $result = $id;
    }
    // Update
    else {
      $dbq->params[':id'] = $id;
      $result = $this->db->query('UPDATE valuemap SET name=:name, prio=:prio WHERE id=:id', $dbq->params);
    }

    // Values
    if (isset($data['value'])) {
      // Current values
      $xlist = [];
      $vlist = [];
      foreach ($this->db->query('SELECT id FROM valuemap_value WHERE valuemap=:id', [':id'=>$id]) as $dbrow) {
        $xlist[] = $dbrow->id;
      }
      // New values
      $prio = 1;
      $vlist = [];
      foreach ($data['value'] as $value) {
        if (!isset($value['id'])) {
          $vlist[] = $this->value_save($id, null, ['name'=>$value['name'], 'prio'=>$prio])[0]->id;
        }
        else {
          $vlist[] = $this->value_save($id, $value['id'], ['name'=>$value['name'], 'prio'=>$prio])[0]->id;
        }
        $prio++;
      }

      // Delete values
      foreach (array_diff($xlist, $vlist) as $value) {
        $this->value_delete($id, $value);
      }
    }
    return $result;
  }

  /******************************************************************
   * Delete valuemap
   ******************************************************************/
  public function delete($id) {
    $this->db->query('DELETE FROM valuemap_value WHERE valuemap=:valuemap', [':valuemap'=>$id]);
    $count = count($this->db->query('DELETE FROM valuemap WHERE id=:id RETURNING *', [':id'=>$id]));
    return $count > 0;
  }

  /******************************************************************
   * value open
   ******************************************************************/
  public function value_list($vmid, $id=null) {
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
  public function value_save($vmid, $id, $data) {
    $dbq = (object)[ 'fields'=>[ 'valuemap', 'name' ], 'insert'=>[ ':vmid', ':name' ], 'update'=>[ 'name=:name' ], 'params'=>[ ':vmid'=>$vmid, ':name'=>$data['name'] ] ];
    if (isset($data['prio'])) {
      $dbq->fields[] = 'prio';
      $dbq->insert[] = ':prio';
      $dbq->update[] = 'prio=:prio';
      $dbq->params[':prio'] = $data['prio'];
    }
    $dbq->fields = implode(',', $dbq->fields);
    $dbq->insert = implode(',', $dbq->insert);
    $dbq->update = implode(',', $dbq->update);
    if ($id == null) {
      return $this->db->query("INSERT INTO valuemap_value ($dbq->fields) VALUES ($dbq->insert) RETURNING id", $dbq->params);
    }
    else {
      $dbq->params['id'] = $id;
      return $this->db->query("UPDATE valuemap_value SET $dbq->update WHERE valuemap=:vmid AND id=:id RETURNING id", $dbq->params);
    }
  }

  /******************************************************************
   * value delete
   ******************************************************************/
  public function value_delete($vmid, $id) {
    $count = count($this->db->query('DELETE FROM valuemap_value WHERE valuemap=:vmid AND id=:id RETURNING *', [':id'=>$id, ':vmid'=>$vmid]));
    return $count > 0;
  }

}
