<?php
/******************************************************************
 *
 * mod_acl($db)
 *  -> open($otid)
 *
 ******************************************************************/

class mod_acl {

  private $db;

  /******************************************************************
   * Initialize
   ******************************************************************/
  public function __construct($db) {
    $this->db = $db;
  }

  /******************************************************************
   * List ACL's by group
   ******************************************************************/

  public function group_list($groupid) {
    $dbqcols = ($this->db->driver2()->mysql)
      ? "COALESCE(`read`, 0) AS `read`, COALESCE(`create`, 0) AS `create`, COALESCE(`update`, 0) AS `update`, COALESCE(`delete`, 0) AS `delete`"
      : "CAST(read AS int) AS read, CAST(\"create\" AS int) AS \"create\", CAST(update AS int) AS update, CAST(delete AS int) AS delete";
    $dbquery = "
      SELECT
        ot.id,
        ot.name,
        $dbqcols
      FROM objtype AS ot
      LEFT JOIN (
        SELECT
          *
        FROM objtype_acl
        WHERE smgroup = :id
      ) AS ota ON ota.objtype = ot.id
    ";
    $result = [];
    foreach($this->db->query($dbquery, ['id'=>$groupid]) as $dbrow) {
      $result[] = [
        'id'=>$dbrow->id,
        'name'=>$dbrow->name,
        'read'=>($dbrow->read == 1),
        'create'=>($dbrow->create == 1),
        'update'=>($dbrow->update == 1),
        'delete'=>($dbrow->delete == 1)
      ];
    }
    return $result;
  }

  /******************************************************************
   * Save ACL's by group
   ******************************************************************/

  public function group_save($groupid, $data) {
    $this->db->query('DELETE FROM objtype_acl WHERE smgroup=:group', ['group'=>$groupid]);
    foreach ($data as $rec) {
      if ($rec['read'] || $rec['create'] || $rec['update'] || $rec['delete']) {
        $dbparams = [
          'id'      =>$rec['id'],
          'group'   =>$groupid,
          'read'    =>($rec['read']=="true")?1:0,
          'create'  =>($rec['create']=="true")?1:0,
          'update'  =>($rec['update']=="true")?1:0,
          'delete'  =>($rec['delete']=="true")?1:0
        ];
        $this->db->query('INSERT INTO objtype_acl VALUES (:id, :group, :read, :create, :update, :delete)', $dbparams);
      }
    }
    return [];
  }

  /******************************************************************
   * Delete ACL's by group
   ******************************************************************/

  public function group_delete($groupid) {
    $this->db->query('DELETE FROM objtype_acl WHERE smgroup=:group', ['group'=>$groupid]);
    return [];
  }

  /******************************************************************
   * List ACL's by object type
   ******************************************************************/

  public function objtype_list($otid) {
    $dbquery = null;
    $dbparams = [];
    if (strlen($otid) < 36) {
      $dbqcols = ($this->db->driver2()->mysql)
        ? "'0' AS `read`, '0' AS `create`, '0' as `update`, '0' as `delete`"
        : "'0' AS read, '0' AS \"create\", '0' as update, '0' as delete";
      $dbquery = "SELECT id, groupname, $dbqcols FROM sessman_group ORDER BY groupname";
    }
    else {
      $dbqcols = ($this->db->driver2()->mysql)
        ? "COALESCE(`read`, 0) AS `read`, COALESCE(`create`, 0) AS `create`, COALESCE(`update`, 0) AS `update`, COALESCE(`delete`, 0) AS `delete`"
        : "CAST(read AS int) AS read, CAST(\"create\" AS int) AS \"create\", CAST(update AS int) AS update, CAST(delete AS int) AS delete";
      $dbquery = "
        SELECT
          g.id,
          g.groupname,
          $dbqcols
        FROM sessman_group AS g
        LEFT JOIN (
          SELECT
            *
          FROM objtype_acl
          WHERE objtype = :otid
        ) AS ota ON ota.smgroup = g.id
        ORDER BY g.groupname
      ";
      $dbparams = ['otid'=>$otid];
    }
    $result = [];
    foreach($this->db->query($dbquery, $dbparams) as $dbrow) {
      $result[] = [
        'id'=>$dbrow->id,
        'groupname'=>$dbrow->groupname,
        'read'=>($dbrow->read == 1),
        'create'=>($dbrow->create == 1),
        'update'=>($dbrow->update == 1),
        'delete'=>($dbrow->delete == 1)
      ];
    }
    return $result;
  }

  /******************************************************************
   * Save ACL's by object type
   ******************************************************************/

  public function objtype_save($otid, $data) {
    $this->db->query('DELETE FROM objtype_acl WHERE objtype=:objtype', ['objtype'=>$otid]);
    foreach ($data as $rec) {
      if ($rec['read'] || $rec['create'] || $rec['update'] || $rec['delete']) {
        $dbparams = [
          'id'      =>$otid,
          'group'   =>$rec['id'],
          'read'    =>($rec['read']=="true")?1:0,
          'create'  =>($rec['create']=="true")?1:0,
          'update'  =>($rec['update']=="true")?1:0,
          'delete'  =>($rec['delete']=="true")?1:0
        ];
        $this->db->query('INSERT INTO objtype_acl VALUES (:id, :group, :read, :create, :update, :delete)', $dbparams);
      }
    }
    return [];
  }

   /******************************************************************
   * Delete ACL's by object type
   ******************************************************************/

  public function objtype_delete($otid) {
    $this->db->query('DELETE FROM objtype_acl WHERE objtype=:objtype', ['objtype'=>$otid]);
    return [];
  }

}
