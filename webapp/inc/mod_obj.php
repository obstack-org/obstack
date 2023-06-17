<?php
/******************************************************************
 *
 * api_obj($db, $otid)
 *  -> open($otid, $id)
 *  -> save($otid, $id, $data)
 *  -> delete($otid, $id)
 *  -> relation_list($id)
 *  -> relation_list_available($otid, $id)
 *  -> relation_save($id, $id_ref, $private=false)
 *  -> relation_delete($id, $id_ref, $private=false)
 *  -> log_list($otid, $id)
 *  -> log_save($otid, $id, $action, $detail)
 *
 ******************************************************************/

class mod_obj {

  private $db;
  private $objtype;
  private $format = null;
  private $logstate = [];
  private $datatype  = [ 1=>'varchar', 2=>'decimal', 3=>'uuid', 4=>'uuid', 5=>'decimal', 6=>'text', 7=>'varchar', 8=>'timestamp', 9=>'timestamp' ];

  /******************************************************************
   * Initialize
   ******************************************************************/
  function __construct($db, $otid) {
    $this->db = $db;
    $this->objtype = $otid;
    if (isset($_GET['format'])) {
      $this->format = $_GET['format'];
    }
  }

  /******************************************************************
   * Match if format is set as request parameter
   ******************************************************************/
  private function format($value) {
    if ($this->format == $value) {
      return true;
    }
    return false;
  }

  /******************************************************************
   * Active permissions
   ******************************************************************/
  private function acl($otid) {
    if ($_SESSION['sessman']['sa']) {
      return (object)[ 'read'=>true, 'create'=>true, 'update'=>true, 'delete'=>true ];
    }
    else {
      $groups = str_replace('"','\'',substr(json_encode($_SESSION['sessman']['groups']),1,-1));
      if (strlen($groups) > 0) {
        $dbquery = "
          SELECT
            CAST(MAX(CAST(read AS int)) AS bool) AS read,
            CAST(MAX(CAST(\"create\" AS int)) AS bool) AS \"create\",
            CAST(MAX(CAST(update AS int)) AS bool) AS update,
            CAST(MAX(CAST(delete AS int)) AS bool) AS delete
          FROM objtype_acl AS ota
          WHERE ota.smgroup IN ($groups)
          AND ota.objtype = :otid
        ";
        return $this->db->query($dbquery, [':otid'=>$otid])[0];
      }
      else {
        return [];
      }
    }
  }

  private function acl_obj($id) {
    if ($_SESSION['sessman']['sa']) {
      return (object)[ 'read'=>true, 'create'=>true, 'update'=>true, 'delete'=>true ];
    }
    else {
      $groups = str_replace('"','\'',substr(json_encode($_SESSION['sessman']['groups']),1,-1));
      if (strlen($groups) > 0) {
        $dbquery = "
          SELECT
            CAST(MAX(CAST(read AS int)) AS bool) AS read,
            CAST(MAX(CAST(\"create\" AS int)) AS bool) AS \"create\",
            CAST(MAX(CAST(update AS int)) AS bool) AS update,
            CAST(MAX(CAST(delete AS int)) AS bool) AS delete
          FROM objtype_acl AS ota
          LEFT JOIN obj AS o ON o.objtype = ota.objtype
          WHERE ota.smgroup IN ($groups)
          AND o.id = :id
        ";
        return $this->db->query($dbquery, [':id'=>$id])[0];
      }
      else {
        return [];
      }
    }
  }

  /******************************************************************
   * Open Object
   ******************************************************************/
  function open($otid, $id) {
    // Process ACL
    $acl = $this->acl($otid);
    if (!$acl->read) {
      if ($this->format('short')) {
        return [ [ 'id'=>null, 'name'=>'🛇' ] ];
      }
      return null;
    }
    // Handle formats
    if ($this->format('short'))   { return $this->objtype->list_short($otid, $id); }
    $result = [];
    $dbqvaluemap = ['vu.value::varchar', ''];
    $dbqpassword = '*****';
    if ($this->format('gui')) {
      $dbqpassword = '•••••';
      $dbqvaluemap = ['vo.name', 'LEFT JOIN valuemap_value vo ON vo.id = vu.value'];
    }
    $dbquery = "
      SELECT
        op.id AS id,
        op.type AS type,
        op.name AS label,
        CASE op.type
          WHEN 1 THEN vv.value
          WHEN 2 THEN rtrim(TO_CHAR(vd.value, 'FM99999999.99999999'),'.')
          WHEN 3 THEN vu.value::varchar
          WHEN 4 THEN $dbqvaluemap[0]
          WHEN 5 THEN TO_CHAR(vd.value,'FM9')
          WHEN 6 THEN vx.value
          WHEN 7 THEN '$dbqpassword'
          WHEN 8 THEN TO_CHAR(vt.value,'YYYY-MM-DD')
          WHEN 9 THEN TO_CHAR(vt.value,'YYYY-MM-DD HH24:MI')
        END AS value
      FROM obj o
      LEFT JOIN objtype ot 			    ON ot.id = o.objtype
      LEFT JOIN objproperty op 		  ON op.objtype = ot.id
      LEFT JOIN value_decimal vd 	  ON vd.objproperty = op.id AND vd.obj = o.id
      LEFT JOIN value_text vx 		  ON vx.objproperty = op.id AND vx.obj = o.id
      LEFT JOIN value_timestamp vt  ON vt.objproperty = op.id AND vt.obj = o.id
      LEFT JOIN value_uuid vu 		  ON vu.objproperty = op.id AND vu.obj = o.id
      LEFT JOIN value_varchar vv 	  ON vv.objproperty = op.id AND vv.obj = o.id
      $dbqvaluemap[1]
      WHERE ot.id = :otid
      AND o.id = :id
      ORDER BY o.id, op.prio, op.name
    ";
    // Gather properties
    if ($this->format('gui')) {
      $cache = [];
      foreach ($this->db->query($dbquery, [':otid'=>$otid, ':id'=>$id]) as $rec) {
        if ($rec->type == 3) {
          array_push($cache, ['id'=>$rec->id, 'type'=>$rec->type, 'label'=>$rec->label, 'value'=>$this->objtype->list_short(null, $rec->value)[0]['name']]);
        }
        else {
          array_push($cache, $rec);
        }
      }
      $result = $cache;
    }
    else {
      $result = $this->db->query($dbquery, [':otid'=>$otid, ':id'=>$id]);
    }
    return $result;
  }

  /******************************************************************
   * Save object
   ******************************************************************/
  function save($otid, $id, $data) {
    // Get ACL
    $acl = $this->acl($otid);
    // Check if new record
    $newrec = false;
    if ($id == null) {
      if (!$acl->create) { return null; }
      $id = $this->db->query('INSERT INTO obj (objtype) VALUES (:otid) RETURNING id', [':otid'=>$otid])[0]->id;
      $newrec = true;
      $this->log_save($otid, $id, 1, 'Object created');
    }
    else {
      if (!$acl->update) { return null; }
    }
    // Prepare objecttypes
    $objproperty = [];
    foreach ($this->db->query('SELECT * FROM objproperty WHERE objtype=:objtype', [':objtype'=>$otid]) as $rec) {
      $objproperty[$rec->id] = ['type'=>$rec->type, 'type_objtype'=>$rec->type_objtype, 'type_valuemap'=>$rec->type_valuemap, 'required'=>$rec->required];
    }
    // Process values
    $tmplog = '';
    foreach ($data as $key => $value) {
      if ($key != 'relations') {
        $table = $this->datatype[$objproperty[$key]['type']];
        $dbquery = "
          select
            op.name AS name,
            op.type AS type,
            v.value AS value
          FROM obj o
          LEFT JOIN objtype ot ON ot.id = o.objtype
          LEFT JOIN objproperty op ON op.objtype = ot.id
          LEFT JOIN value_$table v ON ((v.objproperty = op.id) AND (v.obj = o.id))
          WHERE o.id=:obj AND op.id=:objproperty
        ";
        $dbtmp = $this->db->query($dbquery, [':obj'=>$id, ':objproperty'=>$key]);
        // Format time values
        if (($dbtmp[0]->type == 8) && ($dbtmp[0]->value != null)) { $dbtmp[0]->value = substr($dbtmp[0]->value,0,10); }
        if (($dbtmp[0]->type == 9) && ($dbtmp[0]->value != null)) { $dbtmp[0]->value = substr_replace(substr($dbtmp[0]->value,0,16),'T',10,1); }
        $current = '';
        if (!empty($dbtmp)) {
          $current = $dbtmp[0]->value;
        }
        // Password
        if ($objproperty[$key]['type'] == 7) {
          if ($value != '*****') {
            $value   = password_hash($value, PASSWORD_BCRYPT );
            $current = password_hash($current, PASSWORD_BCRYPT );
          }
          else {
            // Do not save
            $value = $current;
          }
        }
        if (($value != $current) && (strlen($value) > 1)) {
          $this->db->query("INSERT INTO value_$table VALUES (:obj,:objproperty,:value) ON CONFLICT (obj,objproperty) DO UPDATE SET value=:value", [':obj'=>$id, ':objproperty'=>$key, ':value'=>$value]);
          if (!$newrec) {
            $tmplog .= ', '.$dbtmp[0]->name;
          }
        }
      }
    }
    if (strlen($tmplog) > 0) {
      $this->log_save($otid, $id, 2, substr($tmplog, 2));
    }
    // Process relations
    $dbobjects = [];
    $qrobjects = [];
    foreach($this->db->query('SELECT obj, obj_ref FROM obj_obj WHERE obj=:obj OR obj_ref=:obj', [':obj'=>$id]) as $rec) {
      if (!in_array($rec->obj,     $dbobjects)) { if ($rec->obj     != $id) { array_push($dbobjects, $rec->obj); }}
      if (!in_array($rec->obj_ref, $dbobjects)) { if ($rec->obj_ref != $id) { array_push($dbobjects, $rec->obj_ref); }}
    }
    foreach ($data['relations'] as $value) {
      if (!in_array($value,        $qrobjects)) { array_push($qrobjects,  $value); }
    }
    // Delete relation
    foreach (array_diff($dbobjects, $qrobjects) as $rec) {
      $this->relation_delete($id, $rec, true);
      $this->log_save($otid, $id, 6, $this->objtype->list_short(null, $rec)[0]['name']);
      $this->log_save(null, $rec, 6, $this->objtype->list_short(null, $id)[0]['name']);
    }
    // Add relation
    foreach (array_diff($qrobjects, $dbobjects) as $rec) {
      $this->relation_save($id, $rec, true);
      $this->log_save($otid, $id, 5, $this->objtype->list_short(null, $rec)[0]['name']);
      $this->log_save(null, $rec, 5, $this->objtype->list_short(null, $id)[0]['name']);
    }
    return [ 'id'=>$id ];
  }

  /******************************************************************
   * delete object
   ******************************************************************/
  function delete($otid, $id) {
    // Process ACL
    $acl = $this->acl($otid);
    if (!$acl->delete) { return null; }
    // Delete object
    foreach($this->db->query('SELECT * FROM obj_obj WHERE obj=:id OR obj_ref=:id', [':id'=>$id]) as $rec) {
      if ($rec->obj == $id) {
        $this->log_save(null, $rec->obj_ref, 6, $this->objtype->list_short(null, $rec->obj)[0]['name'].' (deleted)');
      }
      else {
        $this->log_save(null, $rec->obj, 6, $this->objtype->list_short(null, $rec->obj_ref)[0]['name'].' (deleted)');
      }
    }
    $this->db->query('DELETE FROM obj WHERE id=:id AND objtype=:objtype', [':objtype'=>$otid, ':id'=>$id]);
    foreach (['decimal', 'text', 'timestamp', 'uuid', 'varchar'] as $table) {
      $this->db->query("DELETE FROM value_$table WHERE obj=:id", [':id'=>$id]);
    }
    $this->db->query('DELETE FROM obj_obj WHERE obj=:id OR obj_ref=:id', [':id'=>$id]);
    return true;
  }

  /******************************************************************
   * List related objects
   ******************************************************************/
  function relation_list($id) {
    // Process ACL
    $acl = $this->acl_obj($id);
    if (!$acl->read) { return null; }
    // Gather relations
    $dbquery = "
      SELECT
        o.id AS objid,
        ot.id AS ObjTypeId,
        ot.\"name\" AS ObjTypeName,
        op.\"name\" AS label,
        op.prio AS prio,
        op.type AS type,
        CASE op.type
          WHEN 1 THEN vv.value
          WHEN 2 THEN rtrim(TO_CHAR(vd.value, 'FM99999999.99999999'),'.')
          WHEN 3 THEN vu.value::varchar
          WHEN 4 THEN vo.name
          WHEN 5 then
            CASE
              WHEN (TO_CHAR(vd.value,'FM9') = '1') THEN '&nbsp;✓'
              ELSE '&nbsp;✗'
            END
          WHEN 6 THEN vx.value
          WHEN 7 THEN '•••••'
          WHEN 8 THEN TO_CHAR(vt.value,'YYYY-MM-DD')
          WHEN 9 THEN TO_CHAR(vt.value,'YYYY-MM-DD HH24:MI')
        END AS value
      FROM obj_obj oo
      LEFT JOIN obj o on (o.id = oo.obj) or (o.id = oo.obj_ref)
      LEFT JOIN objtype ot ON ot.id = o.objtype
      LEFT JOIN objproperty op ON op.objtype = ot.id
      LEFT JOIN value_decimal		vd ON vd.objproperty = op.id AND vd.obj = o.id
      LEFT JOIN value_text 		  vx ON vx.objproperty = op.id AND vx.obj = o.id
      LEFT JOIN value_timestamp vt ON vt.objproperty = op.id AND vt.obj = o.id
      LEFT JOIN value_uuid 		  vu ON vu.objproperty = op.id AND vu.obj = o.id
      LEFT JOIN value_varchar 	vv ON vv.objproperty = op.id AND vv.obj = o.id
      LEFT JOIN valuemap_value vo ON vo.id = vu.value
      WHERE (oo.obj = :id OR oo.obj_ref = :id)
      AND o.id != :id
      ORDER BY o.id, op.prio, op.name
    ";
    $tmpid = NULL;
    $cache = [];
    $result = [];
    foreach ($this->db->query($dbquery, [':id'=>$id]) as $rec) {
      if ($tmpid != $rec->objid) {
        if ($tmpid != null) {
          array_push($result, $cache);
        }
        $tmpid = $rec->objid;
        $cache = ['id'=>$rec->objid, 'objtype'=>$rec->objtypeid, 'objtype_name'=>$rec->objtypename];
      }
      if ($rec->type == 3) {
        if ($rec->value != null) {
          $cache[$rec->label] = $this->objtype->list_short(null, $rec->value)[0]['name'];
        }
        else {
          $cache[$rec->label] = null;
        }
      }
      else {
        $cache[$rec->label] = $rec->value;
      }
    }
    array_push($result, $cache);
    if (count($result) == 1) {
      if ($result[0]['id'] == null) {
        $result = [];
      }
    }
    return $result;
  }

  /******************************************************************
   * List available relations for object
   ******************************************************************/
  function relation_list_available($otid, $id) {
    $result = [];
    foreach ($this->objtype->list() as $type) {
      $property_hide = [];
      $propertylist = $this->objtype->property_list($type->id);
      $objectlist = $this->objtype->open($type->id);
      if ($propertylist != null) {
        foreach($propertylist as $property) {
          if (!$property->tbl_visible) {
            $property_hide[] = $property->name;
          }
        }
      }
      if ($objectlist != null) {
        foreach ($objectlist as $obj) {
          $tmpres = ['id'=>$obj->id, 'objtype'=>$type->id, 'type'=>$type->name];
          foreach ($obj as $key=>$value) {
            if (!in_array($key, $property_hide)) {
              $tmpres[$key] = $value;
            }
          }
          $result[] = $tmpres;
        }
      }
    }
    return $result;
  }

  /******************************************************************
   * Create/Save relation
   ******************************************************************/
  function relation_save($id, $id_ref, $private=false) {
    // Process ACL
    if (!$private) {
      $acl = $this->acl_obj($id);
      if (!$acl->update) { return null; }
    }
    // Save relation
    $dbparams = [':obj'=>$id, ':obj_ref'=>$id_ref];
    if ($id < $id_ref) {
      $dbparams = [':obj'=>$id_ref, ':obj_ref'=>$id];
    }
    $count = count($this->db->query('INSERT INTO obj_obj VALUES (:obj,:obj_ref) RETURNING *', $dbparams));
    return ($count > 0);
  }

  /******************************************************************
   * Delete relation
   ******************************************************************/
  function relation_delete($id, $id_ref, $private=false) {
    // Process ACL
    if (!$private) {
      $acl = $this->acl_obj($id);
      if (!$acl->delete) { return null; }
    }
    $dbparams = [':obj'=>$id, ':obj_ref'=>$id_ref];
    if ($id < $id_ref) {
      $dbparams = [':obj'=>$id_ref, ':obj_ref'=>$id];
    }
    $count = count($this->db->query('DELETE FROM obj_obj WHERE obj=:obj AND obj_ref=:obj_ref RETURNING *', $dbparams));
    return ($count > 0);
  }

  /******************************************************************
   * List log entries
   ******************************************************************/
  function log_list($otid, $id) {
    if ($this->logstate[$otid] == null) {
      $this->logstate[$otid] = $this->db->query('SELECT log FROM objtype WHERE id=:id', [':id'=>$otid])[0]->log;
    }
    if ($this->logstate[$otid]) {
      //return $this->db->query('SELECT TO_CHAR(timestamp,'YYYY-MM-DD HH24:MI') as timestamp,username,action,details FROM obj_log WHERE obj=:id ORDER BY timestamp DESC', array(':id'=>$id));
      $log = $this->db->query('SELECT timestamp,username,action,details FROM obj_log WHERE obj=:id ORDER BY timestamp DESC', [':id'=>$id]);
      for ($i=0; $i < count($log); $i++) {
        $log[$i]->timestamp = substr($log[$i]->timestamp, 0, strrpos($log[$i]->timestamp, ':'));
      }
      return $log;
    }
    return [];
  }

  /******************************************************************
   * Save log entry
   ******************************************************************/
  function log_save($otid, $id, $action, $detail) {
    if ($otid == null) {
      $otid = $this->db->query('SELECT objtype FROM obj WHERE id=:id', [':id'=>$id])[0]->objtype;
    }
    if ($this->logstate[$otid] == null) {
      $this->logstate[$otid] = $this->db->query('SELECT log FROM objtype WHERE id=:id', [':id'=>$otid])[0]->log;
    }
    if ($this->logstate[$otid]) {
      $this->db->query('INSERT INTO obj_log VALUES (:obj,now(),:username,:action,:detail)', [':obj'=>$id, ':username'=>$_SESSION['sessman']['username'], ':action'=>$action, ':detail'=>$detail]);
    }
  }

}