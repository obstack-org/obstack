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
   ******************************************************************/
  public function list($id = null) {
    if ($id == null) {
      return $this->db->select('id, name', 'valuemap', [], 'name');
    }
    else {
      $result = $this->db->select('name, prio', 'valuemap', [':id'=>$id])[0];
      if ($this->db->driver()->mysql) {
        $result->prio = ($result->prio == 1) ? true : false;
      }
      return $result;
    }
  }

  /******************************************************************
   * Save valuemap (create/update)
   ******************************************************************/
  public function save($id, $data) {

    // Prepare
    $dbq = (object)[ 'set'=>[] ];
    if (isset($data['name'])) {
      $dbq->set[':name'] = $data['name'];
    }
    if (isset($data['prio'])) {
      $dbq->set[':prio'] = ($this->db->driver()->mysql)
        ? (($data['prio'] || $data['prio'] == '1') ? '1' : '0')
        : (($data['prio'] || $data['prio'] == '1') ? 'true' : 'false');
    }

    // Create
    if ($id == null) {
      $id = $this->db->insert('valuemap', $dbq->set);
      $result = $id;
    }
    // Update
    else {
      $result = $this->db->update('valuemap', $dbq->set, [':id'=>$id]);
    }

    // Values
    if (isset($data['value'])) {
      // Current values
      $xlist = [];
      foreach ($this->db->select('id', 'valuemap_value', [':valuemap'=>$id]) as $dbrow) {
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
    $this->db->delete('valuemap_value', [':valuemap'=>$id]);
    $this->db->delete('valuemap', [':id'=>$id]);
    return true;
  }

  /******************************************************************
   * value open
   ******************************************************************/
  public function value_list($vmid, $id=null) {
    if ($id == null) {
      $vmconfig = $this->db->select('prio','valuemap', [':id'=>$vmid])[0];
      $priosort = 'name';
      if ($vmconfig->prio) {
        $priosort = 'prio';
      }
      return $this->db->select('id, name', 'valuemap_value', [':valuemap'=>$vmid], $priosort);
    }
    else {
      return $this->db->select('name', 'valuemap_value', [':valuemap'=>$vmid, ':id'=>$id]);
    }
  }

  /******************************************************************
   * value save
   ******************************************************************/
  public function value_save($vmid, $id, $data) {

    // Prepare
    $dbq = (object)[ 'set'=>[ ':valuemap'=>$vmid, ':name'=>$data['name'] ]];
    if (isset($data['prio'])) {
      $dbq->set[':prio'] = $data['prio'];
    }

    // Save
    if ($id == null) {
      return [ (object)[ 'id'=>$this->db->insert('valuemap_value', $dbq->set) ] ];
    }
    else {
      $this->db->update('valuemap_value', $dbq->set, [':id'=>$id]);
      return [ (object)[ 'id'=>$id ] ];
    }
  }

  /******************************************************************
   * value delete
   ******************************************************************/
  public function value_delete($vmid, $id) {
    $this->db->delete('valuemap_value', [':id'=>$id, ':valuemap'=>$vmid]);
    return true;
  }

}
