<?php
/******************************************************************
 *
 * api_objtype($db)
 *  -> acl($id)
 *  -> list($id=null)
 *  -> save($id)
 *  -> delete($id)
 *  -> property_list($otid, $id)
 *  -> property_save($otid, $id)
 *  -> property_delete($otid, $id)
 *
 ******************************************************************/

class mod_objtype {

  private $db;
  private $format;
  private $display;
  private $acl;
  private $log;
  private $valuetype = [1=>'varchar', 2=>'decimal', 3=>'uuid', 4=>'timestamp', 5=>'text'];

  /******************************************************************
   * Initialize
   ******************************************************************/
  public function __construct($db) {
    $this->db = $db;
    $this->acl = [];
    $this->log = [];
    if (isset($_GET['format'])) { $this->format = $_GET['format']; }
    if (isset($_GET['display'])) { $this->display = $_GET['display']; }
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
   * Retrieve permissions
   ******************************************************************/
  public function acl($otid) {
    if (!in_array($otid, $this->acl)) {
      if ($_SESSION['sessman']['sa']) {
        if (count($this->db->select('id', 'objtype',  [ 'id'=>$otid ]))>0) {
          $this->acl[$otid] = (object)[ 'read'=>true, 'create'=>true, 'update'=>true, 'delete'=>true ];
        }
      }
      else {
        $groups = str_replace('"','\'',substr(json_encode($_SESSION['sessman']['groups']),1,-1));
        if (strlen($groups) > 0) {
          $dbqcols = "CAST(read AS int) AS read, CAST(\"create\" AS int) AS \"create\", CAST(update AS int) AS update, CAST(delete AS int) AS delete";
          if ($this->db->driver2()->mysql) {
            $dbqcols = "COALESCE(`read`, 0) AS `read`, COALESCE(`create`, 0) AS `create`, COALESCE(`update`, 0) AS `update`, COALESCE(`delete`, 0) AS `delete`";
          }
          $dbquery = "
            SELECT
              $dbqcols
            FROM objtype_acl AS ota
            WHERE ota.smgroup IN ($groups)
            AND ota.objtype = :otid
          ";
          $result = $this->db->query($dbquery, [':otid'=>$otid])[0];
          $this->acl[$otid] = (object)[
            'read'=>($result->read == 1),
            'create'=>($result->create == 1),
            'update'=>($result->update == 1),
            'delete'=>($result->delete == 1)
          ];
        }
      }
    }
    return $this->acl[$otid];
  }

  /******************************************************************
   * Retrieve Log state
   ******************************************************************/
  public function log($otid) {
    if (!in_array($otid, $this->log)) {
      $this->log[$otid] = $this->db->select('log', 'objtype', [':id'=>$otid]);
    }
    return $this->log[$otid];
  }

  /******************************************************************
   * List object types
   ******************************************************************/
  public function list($otid=null) {
    // Prepare
    $dbq = (object)[ 'select'=>[], 'join'=>[], 'filter'=>[], 'params'=>[] ];

    // Filter
    if ($otid != null) {
      if (!is_array($otid)) {
        $otid = [ $otid ];
      }
      $dbqin = $this->list2in($otid, 'otid');
      $dbq->filter[] = "ot.id IN ($dbqin->marks)";
      $dbq->params = $dbqin->params;
    }
    $dbq->select[] = 'DISTINCT ot.id AS id, ot.name AS name';
    if (!$_SESSION['sessman']['sa']) {
      $dbqin = $this->list2in($_SESSION['sessman']['groups'], 'smg');
      if (count($dbqin->params) <= 0) {
        $dbqin->marks = 'NULL';
      }
      $dbq->join[] = 'LEFT JOIN objtype_acl oa ON oa.objtype = ot.id';
      $dbq->filter[] = "oa.smgroup IN ($dbqin->marks) AND oa.read";
      $dbq->params = array_merge($dbq->params, $dbqin->params);
    }
    if (in_array($this->display, [ 'map' ]) || in_array($this->format, [ 'aggr' ])) {
      $dbq->select[] = 'ot.map AS map';
    }
    if (in_array($this->format, [ 'expand', 'full', 'aggr' ])) {
      $dbq->select[] = 'ot.short AS short, ot.log AS log';
    }
    if (count($dbq->filter) > 0) {
      $dbq->filter = 'WHERE '.implode(' AND ', $dbq->filter);
    }
    else {
      $dbq->filter = '';
    }
    $dbq->select = implode(', ', $dbq->select);
    $dbq->join = implode(' ', $dbq->join);

    // Retrieve object types
    $dbquery = "
      SELECT
        $dbq->select
      FROM objtype AS ot
      $dbq->join $dbq->filter
      GROUP BY ot.id
    ";
    $dbresult = $this->db->query($dbquery, $dbq->params);
    if ($this->db->driver() == 'mysql' && in_array($this->format, [ 'expand', 'full', 'aggr' ])) {
      foreach($dbresult as $idx=>$obj) {
        $dbresult[$idx]->log = ($dbresult[$idx]->log == 1);
      }
    }
    if ($this->format == 'aggr') {
      foreach ($dbresult as $ot) {
        $meta = null;
        $property_list = $this->property_list($ot->id);
        if ($this->display == 'edit') {
          $meta = (object)[ 'objecttype'=>(object)[], 'valuemap'=>(object)[] ];
          $mod_obj = new mod_obj($this->db, $this);
          $mod_valuemap = new mod_valuemap($this->db);
          foreach ($property_list as $property) {
            if ($property->type == 3) {
              $tobjtype = $property->type_objtype;
              $meta->objecttype->$tobjtype = [];
              foreach ($mod_obj->list_short([$tobjtype], null) as $sid=>$sname) {
                $meta->objecttype->$tobjtype[] = (object)[ 'id'=>$sid, 'name'=>$sname ];
              }
            }
            if ($property->type == 4) {
              $tvaluemap = $property->type_valuemap;
              $meta->valuemap->$tvaluemap = $mod_valuemap->value_list($tvaluemap);
            }
          }
        }
        // Relations
        $relation_list = [];
        $dbrquery = '
          SELECT id FROM objtype o
          LEFT JOIN objtype_objtype oo ON oo.objtype = o.id OR oo.objtype_ref = o.id
          WHERE o.id != :otid
          AND (oo.objtype = :otid OR oo.objtype_ref = :otid)
          OR (oo.objtype = :otid AND oo.objtype_ref = :otid)
        ';
        foreach ($this->db->query($dbrquery, [ ':otid'=>$ot->id ]) as $rel) {
          $relation_list[] = $rel->id;
        }
        // Format result
        $result = (object)[
          'objecttype'=> $ot,
          'property'=>$property_list,
          'meta'=>$meta,
          'relations'=>$relation_list,
          'acl'=> $this->acl($ot->id)
        ];
        if ($result->meta == null) {
          unset($result->meta);
        }
      }
      return $result;
    }
    else {
      $result = $dbresult;
      return ($otid == null) ? $result : $result[0];
    }
  }

  /******************************************************************
   * Save object type
   ******************************************************************/
  public function save($id, $data) {
    // Prepare
    $log = null;
    $dbq = (object)[ 'params'=>[] ];
    foreach ([ 'name', 'log', 'short', 'map' ] as $field) {
      if (isset($data[$field])) {
        $dbq->params[":$field"] = $data[$field];
      }
    }
    if (isset($data['log'])) {
      $log = ($data['log'] || $data['log'] == 'true' || $data['log'] == '1') ? true : false;
      if ($this->db->driver2()->mysql) {
        $dbq->params[':log'] = ($log) ? 1 : 0;
      }
      else {
        $dbq->params[':log'] = ($log) ? 'true' : 'false';
      }
    }

    // ObjType
    if ($id == null) {
      $id = $this->db->insert('objtype', $dbq->params);
      $result = [ 'id'=>$id ];
    }
    else {
      $chlog = ($log !== null && $log != $this->log($id));
      $this->db->update('objtype', $dbq->params, [':id'=>$id]);
      $result = [];
      // Object logs
      if ($chlog) {
        $logmsg = ($log) ? 'enabled' : 'disabled';
        $dbc = 0;
        $dbq = (object)[ 'values'=>[], 'params'=>[ ':username'=>$_SESSION['sessman']['username'], ':detail'=>"Logging $logmsg" ] ];
        foreach ($this->db->select('id','obj', [':id'=>$id]) as $dbrow) {
          $dbq->values[] = "(:obj$dbc, now(), :username, 10, :detail)";
          $dbq->params[":obj$dbc"] = $dbrow->id;
          $dbc++;
        }
        $dbq->values = implode(',', $dbq->values);
        $this->db->query("INSERT INTO obj_log VALUES $dbq->values", $dbq->params);
      }
    }
    // Objtype properties
    if (isset($data['property'])) {
      // Current properties
      $xlist = [];
      $proplist = [];
      foreach ($this->db->select('id','objproperty', [':objtype'=>$id], 'prio') as $dbrec) {
        $xlist[] = $dbrec->id;
      }
      // New properties
      $prio = 1;
      foreach ($data['property'] as $prop) {
        $prop['prio'] = $prio;
        $tmpid = $this->property_save($id, $prop['id'], $prop)[0]->id;
        if ($prop['id'] == null) {
          $prop['id'] = $tmpid;
        }
        $proplist[] = $prop['id'];
        $prio++;
      }
      // Delete properties
      foreach (array_diff($xlist, $proplist) as $prop) {
        $this->property_delete($id, $prop);
      }
    }
    // Objtype relations
    if (isset($data['relations'])) {
      $dbc = 0;
      $dbq = (object)[ 'insert'=>[], 'params'=>[] ];
      foreach ($data['relations'] as $id_ref) {
        $dbq->insert[] = ($id >= $id_ref) ? "(:otx$dbc,:oty$dbc)" : "(:oty$dbc,:otx$dbc)";
        $dbq->params[":otx$dbc"] = $id;
        $dbq->params[":oty$dbc"] = $id_ref;
        $dbc++;
      }

      $this->db->query("DELETE FROM objtype_objtype WHERE objtype=:otid OR objtype_ref=:otid", [ ':otid'=>$id ]);
      if (count($dbq->insert) > 0) {
        $dbq->insert = implode(',', $dbq->insert);
        $this->db->query("INSERT INTO objtype_objtype VALUES $dbq->insert", $dbq->params);
      }
    }
    return $result;
  }

  /******************************************************************
   * Delete object type
   ******************************************************************/
  public function delete($otid) {
    $dbq = (object)[ 'params'=>[':objtype'=>$otid] ];
    foreach ($this->valuetype as $type) {
      $this->db->query("DELETE FROM value_$type WHERE objproperty IN (SELECT id FROM objproperty WHERE objtype=:objtype)", $dbq->params);
    }
    $this->db->delete('obj', $dbq->params);
    $this->db->delete('objproperty', $dbq->params);
    $this->db->delete('objtype_acl', $dbq->params);
    $this->db->query('DELETE FROM objtype_objtype WHERE objtype=:otid or objtype_ref=:otid', $dbq->params);
    $this->db->delete('objtype', [':id'=>$otid]);
    return true;
  }

  /******************************************************************
   * List object type properties
   ******************************************************************/
  public function property_list($otid, $id=null) {
    if (!$this->acl($otid)->read) { return null; }

    // Process list
    $dbq = (object)[ 'select'=>[], 'filter'=>[ 'ot.id=:otid' ], 'params'=>[ ':otid'=>$otid ] ];
    if ($id == null) {
      $dbq->select[] = 'op.id AS id,';
    }
    else {
      $dbq->filter[] = 'op.id = :id';
      $dbq->params[':id'] = $id;
    }
    $dbq->select = implode('', $dbq->select);
    $dbq->filter = implode(' AND ', $dbq->filter);
    $dbquery = "
      SELECT
        $dbq->select
        op.name AS name,
        op.type AS type,
        op.type_objtype AS type_objtype,
        op.type_valuemap AS type_valuemap,
        op.required AS required,
        op.frm_readonly AS frm_readonly,
        op.frm_visible AS frm_visible,
        op.tbl_visible AS tbl_visible,
        op.tbl_orderable AS tbl_orderable
      FROM objproperty AS op
      LEFT JOIN objtype ot ON ot.id = op.objtype
      WHERE $dbq->filter
      ORDER BY op.prio, op.name
    ";
    $result = $this->db->query($dbquery, $dbq->params);
    if ($this->db->driver() == 'mysql') {
      foreach($result as $idx=>$obj) {
        foreach(['required', 'frm_visible', 'frm_readonly', 'tbl_visible', 'tbl_orderable'] as $property) {
          $result[$idx]->$property = ($result[$idx]->$property == 1);
        }
      }
    }
    return ($id == null) ? $result : $result[0];
  }

  /******************************************************************
   * Save object type property
   ******************************************************************/
  public function property_save($otid, $id, $data) {
    if ($id == null && $data['id'] != null) {
      $id = $data['id'];
    }
    $fields = [ 'name', 'type', 'prio', 'type_objtype', 'type_valuemap', 'required', 'frm_readonly', 'frm_visible', 'tbl_visible', 'tbl_orderable' ];
    $dbqparams = [ ':objtype'=>$otid ];
    foreach ($fields as $field) {
      if (isset($data[$field])) {
        if ($this->db->driver2()->mysql) {
          $dbqparams[":$field"] = (is_bool($data[$field])) ? (($data[$field] || $data[$field] == '1') ? 1 : 0) : $data[$field];
        }
        else {
          $dbqparams[":$field"] = (is_bool($data[$field])) ? (($data[$field] || $data[$field] == '1') ? 'true' : 'false') : $data[$field];
        }
      }
    }
    if ($id == null) {
      return $this->db->insert('objproperty', $dbqparams);
    }
    else {
      return $this->db->update('objproperty', $dbqparams, [ ':id'=>$id ]);
    }
  }

  /******************************************************************
   * Delete object type property
   ******************************************************************/
  public function property_delete($otid, $id) {
    $this->db->delete('objproperty', [':objtype'=>$otid, ':id'=>$id]);
    return true;
  }

}
