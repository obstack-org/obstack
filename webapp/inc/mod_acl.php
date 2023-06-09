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
  function __construct($db) {
    $this->db = $db;
  }

  /******************************************************************
   * List ACL's by group
   ******************************************************************/

  function group_list($groupid) {
    $dbquery = "
      SELECT
        ot.id,
        ot.name,
        ota.read,
        ota.create,
        ota.update,
        ota.delete
      FROM objtype AS ot
      LEFT JOIN (
        SELECT
          *
        FROM objtype_acl
        WHERE smgroup = :id
      ) AS ota ON ota.objtype = ot.id
    ";
    return $this->db->query($dbquery, ['id'=>$groupid]);
  }

  /******************************************************************
   * Save ACL's by group
   ******************************************************************/

  function group_save($groupid, $data) {
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

  function group_delete($groupid) {
    $this->db->query('DELETE FROM objtype_acl WHERE smgroup=:group', ['group'=>$groupid]);
    return [];
  }

  /******************************************************************
   * List ACL's by object type
   ******************************************************************/

  function objtype_list($otid) {
    if (strlen($otid) < 36) {
      $dbquery = "
        SELECT
          g.id,
          g.groupname,
          false AS read,
          false AS create,
          false AS update,
          false AS delete
        FROM sessman_group AS g
        ORDER BY groupname
      ";
      return $this->db->query($dbquery, []);
    }
    else {
      $dbquery = "
        SELECT
          g.id,
          g.groupname,
          ota.read,
          ota.create,
          ota.update,
          ota.delete
        FROM sessman_group AS g
        LEFT JOIN (
          SELECT
            *
          FROM objtype_acl
          WHERE objtype = :otid
        ) AS ota ON ota.smgroup = g.id
        ORDER BY g.groupname
      ";
      return $this->db->query($dbquery, ['otid'=>$otid]);
    }
  }

  /******************************************************************
   * Save ACL's by object type
   ******************************************************************/

  function objtype_save($otid, $data) {
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

  function objtype_delete($otid) {
    $this->db->query('DELETE FROM objtype_acl WHERE objtype=:objtype', ['objtype'=>$otid]);
    return [];
  }

}
