<?php
/******************************************************************
 *
 * api_obj($db, $otid)
 *  -> list($otid, $id)
 *  -> open($otid, $id)
 *  -> save($otid, $id, $data)
 *  -> delete($otid, $id)
 *  -> relation_list($otid, $id $available=false)
 *  -> relation_save($id, $id_ref, $private=false)
 *  -> relation_delete($id, $id_ref, $private=false)
 *  -> log_list($otid, $id)
 *  -> log_save($otid, $id, $action, $detail)
 *
 ******************************************************************/

class mod_obj {

  private $db;
  private $format;
  private $display;
  private $objtype;
  private $valuemap;
  private $datatype  = [ 1=>'varchar', 2=>'decimal', 3=>'uuid', 4=>'uuid', 5=>'decimal', 6=>'text', 7=>'varchar', 8=>'timestamp', 9=>'timestamp' ];
  private $propertytype = [ 1=>'Text', 2=>'Number', 3=>'Object Type', 4=>'Value Map', 5=>'Checkbox', 6=>'Textbox', 7=>'Password', 8=>'Date', 9=>'DateTime' ];

  /******************************************************************
   * Initialize
   ******************************************************************/
  public function __construct($db, $objtype, $valuemap) {
    $this->db = $db;
    $this->format = '';
    $this->objtype = $objtype;
    $this->valuemap = $valuemap;
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
    foreach($list as $id=>$value) {
      $result->marks[] = ":$prefix$id";
      $result->params[":$prefix$id"] = $value;
    }
    $result->marks = implode(',', $result->marks);
    return $result;
  }

  /******************************************************************
   * List Objects
   * ============
   * $otid / $id    UUID or array of UUID's
   * ?format=       none (default), text, expand, full, aggr
   ******************************************************************/
  private function list_full($otid=null, $id=null, $format_overwrite=null, $cache=null, $depth=0) {
    // Prepare
    $result = [];
    $format = (object)[ 'none'=>false, 'text'=>false, 'expand'=>false, 'full'=>false, 'aggr'=>false ];
    if ($format_overwrite != null) {
      $format->$format_overwrite = true;
    }
    else {
      if (in_array($this->format, array_keys((array)$format))) {
        $tfmt = $this->format;
        $format->$tfmt = true;
      }
    }
    $xlist = (object)[ 'valuemap'=>[], 'object'=>[] ];
    if ($cache == null) {
      $cache = (object)[ 'valuemap'=>[], 'object'=>[] ];
    }

    // Filter
    $dbq = (object)[ 'filter'=>[], 'params'=>[] ];
    $dbqlabel = ($format->expand || $format->full) ? 'op.name as label,' : '';
    if ($otid != null) {
      if (!is_array($otid)) {
        $otid = [ $otid ];
      }
      $dbqin = $this->list2in($otid, 'otid');
      $dbq->filter[] = "ot.id IN ($dbqin->marks)";
      $dbq->params = array_merge($dbq->params, $dbqin->params);
    }
    if ($id != null) {
      if (!is_array($id)) {
        $id = [ $id ];
      }
      // Exclude cached objects
      $oblist = array_diff($id, array_keys($cache->object));
      if (count($oblist) > 0) {
        $dbqin = $this->list2in($oblist, 'id');
        $dbq->filter[] = "o.id IN ($dbqin->marks)";
        $dbq->params = array_merge($dbq->params, $dbqin->params);
      }
    }
    if ($this->display == 'table') {
      $dbq->filter[] = 'op.tbl_visible';
    }
    if (count($dbq->filter) > 0) {
      $dbq->filter = 'WHERE '.implode(' AND ', $dbq->filter);
    }
    else {
      $dbq->filter = '';
    }

    // Retrieve objects
    if (!($otid == null && $id == null)) {
      // Retrieve data
      $dbquery = "
        SELECT
          o.id AS id,
          ot.short AS short,
          op.id AS property,
          $dbqlabel
          op.type AS type,
          CASE op.type
            WHEN 1 THEN vv.value
            WHEN 2 THEN rtrim(TO_CHAR(vd.value, 'FM99999999.99999999'),'.')
            WHEN 3 THEN vu.value::varchar
            WHEN 4 THEN vu.value::varchar
            WHEN 5 THEN TO_CHAR(vd.value,'FM9')
            WHEN 6 THEN vx.value
            WHEN 7 THEN '*****'
            WHEN 8 THEN TO_CHAR(vt.value,'YYYY-MM-DD')
            WHEN 9 THEN TO_CHAR(vt.value,'YYYY-MM-DD HH24:MI')
          END AS value
        FROM obj o
        LEFT JOIN objtype ot ON ot.id = o.objtype
        LEFT JOIN objproperty op ON op.objtype = ot.id
        LEFT JOIN value_decimal vd ON vd.objproperty = op.id AND vd.obj = o.id
        LEFT JOIN value_text vx ON vx.objproperty = op.id AND vx.obj = o.id
        LEFT JOIN value_timestamp vt ON vt.objproperty = op.id AND vt.obj = o.id
        LEFT JOIN value_uuid vu ON vu.objproperty = op.id AND vu.obj = o.id
        LEFT JOIN value_varchar vv ON vv.objproperty = op.id AND vv.obj = o.id
        $dbq->filter
        ORDER BY o.id, op.prio, op.name
      ";
      $dbresult = $this->db->query($dbquery, $dbq->params);
      if (count($dbresult) > 0) {
        $id = null;
        $meta = [];
        foreach($dbresult as $dbrow) {
          if ($dbrow->id != $id) {
            if ($id != null) {
              $cache->object[$id] = (object)[ 'short'=>$dbrow->short, 'meta'=>$meta ];
              $result[] = (object)[ 'id'=>$id ];
              $meta = [];
            }
            $id = $dbrow->id;
          }
          $meta[$dbrow->property] = (object)[
            'label'=>$dbrow->label,
            'type'=>$dbrow->type,
            'type_text'=>$this->propertytype[$dbrow->type],
            'value'=>$dbrow->value
          ];
          if ($dbrow->type == 2) {
            $meta[$dbrow->property]->value = intval($dbrow->value);
          }
          elseif ($dbrow->type == 3) {
            $xlist->object[$dbrow->value][$dbrow->id] = $dbrow->property;
          }
          elseif ($dbrow->type == 4) {
            $xlist->valuemap[$dbrow->value][$dbrow->id] = $dbrow->property;
          }
          elseif ($dbrow->type == 5) {
            $meta[$dbrow->property]->value = ($dbrow->value == '1') ? true : false;
          }
          $meta[$dbrow->property]->value_text = $meta[$dbrow->property]->value;
        }
        $cache->object[$id] = (object)[ 'short'=>$dbrow->short, 'meta'=>$meta ];
        $result[] = (object)[ 'id'=>$id ];
      }
    }

    // Retrieve valuemap values, exclude cached valuemaps
    $xkeys = array_diff(array_keys($xlist->valuemap), array_keys($cache->valuemap));
    if (count($xkeys) > 0) {
      $dbqin = $this->list2in($xkeys);
      foreach ($this->db->query("SELECT id, name FROM valuemap_value WHERE id IN ($dbqin->marks)", $dbqin->params) as $dbrow) {
        foreach($xlist->valuemap[$dbrow->id] as $oid=>$propid) {
          $cache->object[$oid]->meta[$propid]->value_text = $dbrow->name;
        }
      }
    }

    // Retrieve object shortnames, exclude cached objects
    $shortlist = $this->list_short(null, array_keys($xlist->object), $cache, $depth);
    foreach ($shortlist as $sid=>$svalue) {
      foreach($xlist->object[$sid] as $oid=>$propid) {
        $cache->object[$oid]->meta[$propid]->value_text = $shortlist[$sid];
      }
    }

    // Result
    for ($i=0; $i<count($result); $i++) {
      foreach ($cache->object[$result[$i]->id]->meta as $property=>$value ) {
        if ($format->text) {
          $result[$i]->$property = $value->value_text;
        }
        elseif ($format->expand) {
          $result[$i]->$property = (object) [
            'name'=>$value->label,
            'value'=>$value->value,
            'value_text'=>$value->value_text,
          ];
        }
        elseif ($format->full) {
          $result[$i]->$property = $value;
        }
        else {
          $result[$i]->$property = $value->value;
        }
      }
    }

    return $result;
  }

  /***********************************************************************
   * List short name for ojbects
   * ===========================
   * $otidlist / $idlist    UUID or array of UUID's
   * $cache     Caching results preventing repeated requests
   * $depth     Current depth, preventing infinite loop (link to self)
   ***********************************************************************/
  private function list_short($otidlist=null, $idlist=null, $cache=[], $depth=0) {
    // Prepare
    $result = [];
    if ($cache == null) {
      $cache = (object)[ 'valuemap'=>[], 'object'=>[] ];
    }
    if ($otidlist != null) {
      if ($idlist == null) {
        $idlist = [];
      }
      foreach ($this->list_full($otidlist) as $obj) {
        $idlist[] = $obj->id;
      }
    }
    // Complement cache
    $newlist = array_diff($idlist, array_keys($cache->object));
    if (count($newlist) > 0) {
      $this->list_full(null, $newlist, 'text', $cache);
    }
    // Retrieve shortnames
    foreach($idlist as $id) {
      $max = ($cache->object[$id] < 4) ? $cache->object[$id]->short : 4;
      $short = [];
      // Retrieve values
      foreach($cache->object[$id]->meta as $key=>$value) {
        if ($max >= 0) {
          if ($depth < 8) {
            $short[] = ($value->type == 3) ? $this->list_short(null, [$value->value], $cache, $depth++)[$value->value] : $value->value_text;
          }
          $max--;
        }
      }
      // Format result
      $short = implode(', ', $short);
      $result[$id] =  (strlen($short) > 64) ? substr($short,0,62).'...' : $short;
      $depth = 0;
    }
    return $result;
  }

  /******************************************************************
   * List Objects
   * ============
   * $otid / $id    UUID or array of UUID's
   * ?format=       none (default, text, expand, full
   ******************************************************************/
  public function list($otid) {
    if (!$this->objtype->acl($otid)->read) { return null; }
    return $this->list_full($otid, null);
  }

  /******************************************************************
   * Open Object
   * ===========
   * $otid / $id    UUID or array of UUID's
   * ?format=       none (default), text, expand, full, aggr
   ******************************************************************/
  public function open($otid=null, $id=null) {
    if (!$this->objtype->acl($otid)->read) { return null; }
    if (is_array($otid) || is_array($id)) { return null; }
    $obj = $this->list_full($otid, $id);
    $rel = $this->relation_list($otid, $id);
    if (count($obj) > 0) {
      if ($this->format('aggr')) {
        $objecttype = $this->objtype->list($otid);
        $meta = (object)[ 'objecttype'=>(object)[], 'valuemap'=>(object)[] ];
        foreach ($objecttype[0]->property as $property) {
          if ($property->type == 3) {
            $tobjtype = $property->type_objtype;
            $meta->objecttype->$tobjtype = [];
            foreach($this->list_short([$tobjtype], null) as $sid=>$sname) {
              $meta->objecttype->$tobjtype[] = (object)[ 'id'=>$sid, 'name'=>$sname ];
            }
          }
          if ($property->type == 4) {
            $tvaluemap = $property->type_valuemap;
            $meta->valuemap->$tvaluemap = $this->valuemap->value_list($tvaluemap);
          }
        }
        $obj = (object)[
          'name'=>$this->list_short(null, [$id])[$id],
          'object'=>$obj[0],
          'relation'=>$rel,
          'meta'=>$meta,
          'objecttype'=>$objecttype[0]->objecttype,
          'property'=>$objecttype[0]->property,
          'acl'=>$objecttype[0]->acl
        ];
        if ($objecttype[0]->objecttype->log) {
          $obj->log = $this->log_list_full($id);
        }
      }
    }
    else {
      return null;
    }
    return $obj;
  }

  /******************************************************************
   * Save object
   ******************************************************************/
  public function save($otid, $id, $data) {
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
        if ($value != $current) {
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
      if (!in_array($rec->obj,     $dbobjects) && $rec->obj     != $id) { array_push($dbobjects, $rec->obj); }
      if (!in_array($rec->obj_ref, $dbobjects) && $rec->obj_ref != $id) { array_push($dbobjects, $rec->obj_ref); }
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
  public function delete($otid, $id) {
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
   * List relations
   * ==============
   * $otid / $id    UUID or array of UUID's
   * $available     List available relations
   ******************************************************************/
  public function relation_list($otid, $id, $available=false) {
    if (!$this->objtype->acl($otid)->read) { return null; }
    $xlist = [];
    if ($available) {
      $dbquery = "
        SELECT
          o.id as id,
          o.objtype AS objtype,
          ot.name AS objtype_name,
          ot.short AS short
        FROM obj o
        LEFT JOIN objtype ot ON ot.id = o.objtype
      ";
      foreach ($this->db->query($dbquery, []) as $rel) {
        $xlist[$rel->id] = $rel;
      }
    }
    else {
      $dbquery = "
        SELECT
          o.id AS obj,
          r.id AS obj_ref,
          o.objtype AS obj_objtype,
          r.objtype AS obj_ref_objtype,
          oot.name AS obj_name,
          rot.name AS obj_ref_name,
          oot.short AS obj_short,
          rot.short AS obj_ref_short
        FROM obj_obj oo
        LEFT JOIN obj o ON o.id = oo.obj
        LEFT JOIN obj r ON r.id = oo.obj_ref
        LEFT JOIN objtype oot ON oot.id = o.objtype
        LEFT JOIN objtype rot ON rot.id = r.objtype
        WHERE (o.objtype = :otid AND o.id = :id)
        OR (r.objtype = :otid AND r.id = :id)
      ";
      foreach ($this->db->query($dbquery, [ 'otid'=>$otid, 'id'=>$id ]) as $rel) {
        if ($rel->obj == $obj) {
          $xlist[$rel->obj] = (object)[
            'id'=>$rel->obj,
            'objtype'=>$rel->obj_objtype,
            'objtype_name'=>$rel->obj_name,
            'short'=>$rel->obj_short
          ];
        }
        else {
          $xlist[$rel->obj_ref] = (object)[
            'id'=>$rel->obj_ref,
            'objtype'=>$rel->obj_ref_objtype,
            'objtype_name'=>$rel->obj_ref_name,
            'short'=>$rel->obj_ref_short
          ];
        }
      }
    }
    if ($this->format('aggr')) {
      $result = [];
      foreach($this->list_full(null, array_keys($xlist), 'text') as $rel) {
        $tmprel = (object)[
          'id'=>$rel->id,
          'objtype'=>$xlist[$rel->id]->objtype,
          'objtype_name'=>$xlist[$rel->id]->objtype_name
        ];
        $max = ($xlist[$rel->id]->short < 4) ? $xlist[$rel->id]->short : 4;
        foreach ($rel as $property=>$value) {
          if ($max >= 0) {
            $tmprel->$property = $value;
            $max--;
          }
        }
        $result[] = $tmprel;
      }
      return $result;
    }
    else {
      return $this->list_full(null, array_keys($xlist));
    }
  }

  /******************************************************************
   * Create/Save relation
   ******************************************************************/
  public function relation_save($id, $id_ref, $private=false) {
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
    return $count > 0;
  }

  /******************************************************************
   * Delete relation
   ******************************************************************/
  public function relation_delete($id, $id_ref, $private=false) {
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
    return $count > 0;
  }

  private function log_list_full($id) {
    $log = $this->db->query('SELECT timestamp,username,action,details FROM obj_log WHERE obj=:id ORDER BY timestamp DESC', [':id'=>$id]);
    for ($i=0; $i < count($log); $i++) {
      $log[$i]->timestamp = substr($log[$i]->timestamp, 0, strrpos($log[$i]->timestamp, ':'));
    }
    return $log;
  }

  public function log_list($otid, $id) {
    if (!$_SESSION['sessman']['sa']) { return null; }
    if ($this->db->query('SELECT log FROM objtype WHERE id=:id', [':id'=>$otid])[0]->log) {
      return $this->log_list_full($id);
    }
    return [];
  }

  /******************************************************************
   * Save log entry
   ******************************************************************/
  public function log_save($otid, $id, $action, $detail) {
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
