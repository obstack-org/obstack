<?php
/******************************************************************
 *
 * api_obj($db, $otid)
 *  -> list($otid, $id)
 *  -> open($otid, $id)
 *  -> save($otid, $id, $data)
 *  -> delete($otid, $id)
 *  -> file_open($otid, $id)
 *  -> file_save($otid, $id)  // $_FILES
 *  -> relation_list($otid, $id $available=false)
 *  -> relation_save($id, $id_ref, $private=false)
 *  -> relation_delete($id, $id_ref, $private=false)
 *  -> log_list($otid, $id)
 *  -> log_save($otid, $id, $action, $detail)
 *
 ******************************************************************/

require_once 'class_basebq.php';

class mod_obj {

  private $db;
  private $format;
  private $display;
  private $objtype;
  private $cache;
  private $plugins;
  private $datatype  = [ 1=>'varchar', 2=>'decimal', 3=>'uuid', 4=>'uuid', 5=>'decimal', 6=>'text', 8=>'timestamp', 9=>'timestamp', 11=>'varchar', 12=>'varchar', 15=>'blob' ];
  private $propertytype = [ 1=>'Text', 2=>'Number', 3=>'Object Type', 4=>'Value Map', 5=>'Checkbox', 6=>'Textbox', 8=>'Date', 9=>'DateTime', 11=>'Password (hash)', 12=>'Password (encrypt)', 15=>'File' ];

  /******************************************************************
   * Initialize
   ******************************************************************/
  public function __construct($db, $objtype) {
    global $plugins;
    $this->db = $db;
    $this->objtype = $objtype;
    $this->cache = (object)[ 'valuemap'=>[], 'object'=>[] ];
    $this->plugins = $plugins;
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
   * List Objects
   * ============
   * $otid / $id    UUID or array of UUID's
   * ?format=       none (default), text, expand, full, aggr
   ******************************************************************/
  private function list_full($otid=null, $id=null, $format_overwrite=null, $depth=0) {
    // Prepare
    $idlist = [];
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

    // Filter
    $dbq = (object)[ 'filter'=>[], 'params'=>[] ];
    $dbqlabel = ($format->expand || $format->full) ? 'op.name as label,' : '';
    $xlist = (object)[ 'object'=>[], 'valuemap'=>[] ];
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
      foreach ($id as $tid) {
        if (!in_array($id, $idlist)) {
          $idlist[] = $tid;
        }
      }
      $oblist = array_filter(array_diff($id, array_keys($this->cache->object)));
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
    if (!($otid == null && $id == null) && strlen($dbq->filter) > 0) {
      // Format per type
      $type_case = ($this->db->driver()->mysql)
        ? " WHEN 1 THEN vv.value
            WHEN 2 THEN rtrim(CAST(vd.value AS char))
            WHEN 3 THEN vu.value
            WHEN 4 THEN vu.value
            WHEN 5 THEN CAST(vd.value AS char)
            WHEN 6 THEN vx.value
            WHEN 8 THEN DATE_FORMAT(vt.value,'%Y-%m-%d')
            WHEN 9 THEN DATE_FORMAT(vt.value,'%Y-%m-%d %H:%i')
            WHEN 15 THEN vb.value"
        : " WHEN 1 THEN vv.value
            WHEN 2 THEN rtrim(TO_CHAR(vd.value, 'FM99999999.99999999'),'.')
            WHEN 3 THEN vu.value::varchar
            WHEN 4 THEN vu.value::varchar
            WHEN 5 THEN TO_CHAR(vd.value,'FM9')
            WHEN 6 THEN vx.value
            WHEN 8 THEN TO_CHAR(vt.value,'YYYY-MM-DD')
            WHEN 9 THEN TO_CHAR(vt.value,'YYYY-MM-DD HH24:MI')
            WHEN 15 THEN vb.value";
      // Retrieve data
      $dbquery = "
        SELECT
          o.id AS id,
          ot.id AS otid,
          ot.short AS short,
          op.id AS property, $dbqlabel
          op.type AS type,
          CASE op.type $type_case
            WHEN 11 THEN '•••••'
            WHEN 12 THEN '•••••'
          END AS value
        FROM obj o
        LEFT JOIN objtype ot ON ot.id = o.objtype
        LEFT JOIN objproperty op ON op.objtype = ot.id
        LEFT JOIN value_decimal vd ON vd.objproperty = op.id AND vd.obj = o.id
        LEFT JOIN value_text vx ON vx.objproperty = op.id AND vx.obj = o.id
        LEFT JOIN value_timestamp vt ON vt.objproperty = op.id AND vt.obj = o.id
        LEFT JOIN value_uuid vu ON vu.objproperty = op.id AND vu.obj = o.id
        LEFT JOIN value_varchar vv ON vv.objproperty = op.id AND vv.obj = o.id
        LEFT JOIN value_blob vb ON vb.objproperty = op.id AND vb.obj = o.id
        $dbq->filter
        ORDER BY o.id, op.prio, op.name
      ";
      $dbresult = $this->db->query($dbquery, $dbq->params);
      if (count($dbresult) > 0) {
        $id = null;
        $meta = [];
        foreach ($dbresult as $dbrow) {
          if ($dbrow->id != $id) {
            if ($id != null) {
              $this->cache->object[$id] = (object)[ 'objecttype'=>$dbrow->otid, 'short'=>$dbrow->short, 'meta'=>$meta ];
              if (!in_array($id, $idlist)) {
                $idlist[] = $id;
              }
              $meta = [];
            }
            $id = $dbrow->id;
          }
          $meta[$dbrow->property] = (object)[
            'label'=>($format->expand || $format->full) ? $dbrow->label : '',
            'type'=>$dbrow->type,
            'type_text'=>(isset($this->propertytype[$dbrow->type])) ? $this->propertytype[$dbrow->type] : '',
            'value'=>$dbrow->value
          ];
          if ($dbrow->type == 2) {
            $meta[$dbrow->property]->value = intval($dbrow->value);
          }
          elseif ($dbrow->type == 3 && $dbrow->value != null) {
            $xlist->object[$dbrow->value][$dbrow->id] = $dbrow->property;
          }
          elseif ($dbrow->type == 4 && $dbrow->value != null) {
            $xlist->valuemap[$dbrow->value][$dbrow->id] = $dbrow->property;
          }
          elseif ($dbrow->type == 5) {
            $meta[$dbrow->property]->value = ($dbrow->value == '1') ? true : false;
          }
          $meta[$dbrow->property]->value_text = $meta[$dbrow->property]->value;
        }
        $this->cache->object[$id] = (object)[ 'objecttype'=>$dbrow->otid, 'short'=>$dbrow->short, 'meta'=>$meta ];
        if (!in_array($id, $idlist)) {
          $idlist[] = $id;
        }
      }
    }

    // Apply plugin(s)
    foreach ($idlist as $id) {
      if ($id != null && isset($this->cache->object[$id]->meta) && array_key_exists($this->cache->object[$id]->objecttype, $this->plugins->plugins)) {
        $object = (object)[ 'id'=>$id ];
        foreach ($this->cache->object[$id]->meta as $property=>$value) {
          $object->$property = $value->value;
        }
        foreach ($this->plugins->apply('read', $this->cache->object[$id]->objecttype, $object) as $property=>$value) {
          if ($property != 'id') {
            $this->cache->object[$id]->meta[$property]->value = $value;
            $this->cache->object[$id]->meta[$property]->value_text = $value;
            if ($this->cache->object[$id]->meta[$property]->type == 3) {
              foreach($xlist->object as $xval=>$xrec) {
                if (isset($xrec[$id]) && $xrec[$id] == $property) {
                  unset($xlist->object[$xval][$id]);
                }
              }
              $xlist->object[$value][$id] = $property;
            }
            if ($this->cache->object[$id]->meta[$property]->type == 4) {
              foreach($xlist->valuemap as $xval=>$xrec) {
                if (isset($xrec[$id]) && $xrec[$id] == $property) {
                  unset($xlist->valuemap[$xval][$id]);
                }
              }
              $xlist->valuemap[$value][$id] = $property;
            }
          }
        }
      }
    }

    // Object shortnames
    if ($depth == 0) {
      $shortlist = $this->list_short(null, array_keys($xlist->object), $depth);
      foreach ($shortlist as $sid=>$svalue) {
        if (in_array($sid, array_keys($xlist->object))) {
          foreach ($xlist->object[$sid] as $oid=>$propid) {
            $this->cache->object[$oid]->meta[$propid]->value_text = $shortlist[$sid];
          }
        }
      }
    }

    // ValueMaps
    if (count($xlist->valuemap) > 0) {
      $dbqin = $this->list2in(array_keys($xlist->valuemap));
      foreach ($this->db->query("SELECT id, name FROM valuemap_value WHERE id IN ($dbqin->marks)", $dbqin->params) as $dbrow) {
        if (in_array($dbrow->id, array_keys($xlist->valuemap))) {
          foreach ($xlist->valuemap[$dbrow->id] as $oid=>$propid) {
            $this->cache->object[$oid]->meta[$propid]->value_text = $dbrow->name;
          }
        }
      }
    }

    // Result
    $result = [];
    foreach ($idlist as $id) {
      if ($id != null) {
        $object = (object)[ 'id'=>$id ];
        if (isset($this->cache->object[$id]->meta)) {
          foreach ($this->cache->object[$id]->meta as $property=>$value) {
            if ($format->text) {
              $object->$property = $value->value_text;
            }
            elseif ($format->expand) {
              $object->$property = (object) [
                'name'=>$value->label,
                'value'=>$value->value,
                'value_text'=>$value->value_text,
              ];
            }
            elseif ($format->full) {
              $object->$property = $value;
            }
            else {
              $object->$property = $value->value;
            }
          }
        }
        $result[] = $object;
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
  public function list_short($otidlist=null, $idlist=null, $depth=0) {
    // Prepare
    $result = [];
    if ($idlist == null)    { $idlist = []; }
    if ($otidlist == null)  { $otidlist = []; }

    // Process
    $depth++;
    if ($depth <= 8 && (count($otidlist) > 0 || count($idlist) > 0)) {

      // Gather data
      $objlist = $this->list_full($otidlist, $idlist, null, $depth);
      $otlist = [];
      foreach ($objlist as $obj) {
        if ($obj->id != null) {
          foreach ($this->cache->object[$obj->id]->meta as $value) {
            if ($value->type == 3) {
              $otlist[] = $value->value;
            }
          }
        }
      }
      $tshort = $this->list_short(null, $otlist, $depth);

      // Generate shortname(s)
      foreach ($objlist as $obj) {
        $max = $this->cache->object[$obj->id]->short;
        $short = [];
        if ($obj->id != null) {
          foreach ($this->cache->object[$obj->id]->meta as $value) {
            if ($max > 0) {
              if ($value->type == 3) {
                if (in_array($value->value, array_keys($tshort))) {
                  $short[] = $tshort[$value->value];
                }
              }
              else {
                if ($value->type != 11) {
                  $short[] = $value->value_text;
                }
              }
              $max--;
            }
          }
        }
        $short = implode(', ', $short);
        $result[$obj->id] = (strlen($short) > 48) ? mb_substr($short,0,46).'...' : $short;
      }

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
    return $this->plugins->apply('list', $otid, $this->list_full($otid, null));
  }

  /******************************************************************
   * Open Object
   * ===========
   * $otid / $id    UUID or array of UUID's
   * ?format=       none (default), text, expand, full, aggr
   ******************************************************************/
  public function open($otid=null, $id=null, $display_overwrite=null) {
    if (!$this->objtype->acl($otid)->read) { return null; }
    if (is_array($otid) || is_array($id)) { return null; }
    $obj = $this->plugins->apply('open', $otid, $this->list_full($otid, $id)[0]);
    $rel = $this->relation_list($otid, $id);
    if ($obj != null) {
      if ($this->format('aggr')) {
        $objecttype = $this->objtype->list($otid, $display_overwrite);
        $obj = (object)[
          'name'=>$this->list_short(null, [$id])[$id],
          'object'=>$obj,
          'relation'=>$rel,
          'meta'=>null,
          'objecttype'=>$objecttype->objecttype,
          'property'=>$objecttype->property,
          'acl'=>$objecttype->acl
        ];
        if (empty($objecttype->relations)) {
          unset($obj->relation);
        }
        if (isset($objecttype->meta)) {
          $obj->meta = $objecttype->meta;
        }
        else {
          unset($obj->meta);
        }
        if ($objecttype->objecttype->log) {
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
    $acl = $this->objtype->acl($otid);

    // Prepare
    $cobj = [];
    if ($id == null) {
      if (!$acl->create) { return null; }
    }
    else {
      if (!$acl->update) { return null; }
      $cobj = $this->list_full($otid, $id, 'none')[0];
    }

    // Apply plugin(s)
    if ($this->plugins->hasPlugin($otid, 'save')) {
      $tmpobj = (object)[ 'id'=>$id ];
      foreach($data as $key=>$value) {
        $tmpobj->$key = $value;
      }
      $data = (array) $this->plugins->apply('save', $otid, $tmpobj);
      $data['id'] = $id;
    }

    // Generate
    $vlist = [];
    foreach ($this->db->select('*', 'objproperty', [':objtype'=>$otid]) as $dbrow) {
      $propid = $dbrow->id;
      $tlist = (object)[ 'property'=>$propid, 'name'=>$dbrow->name, 'type_uuid'=>null, 'value'=>null ];
      if ($dbrow->type == 3) {
        $tlist->type_uuid = $dbrow->type_objtype;
      }
      if ($dbrow->type == 4) {
        $tlist->type_uuid = $dbrow->type_valuemap;
      }
      if (isset($cobj->$propid)) {
        $tlist->value = $cobj->$propid;
      }
      $vlist[$dbrow->type][] = $tlist;
    }

    // Check
    $ttable = [ 3=>'obj', 4=>'valuemap_value' ];
    foreach ([ 3=>'objtype', 4=>'valuemap' ] as $xid=>$xtype) {
      if (isset($vlist[$xid])) {
        $dbc = 0;
        $dbq = (object)[ 'filter'=>[], 'params'=>[] ];
        foreach ($vlist[$xid] as $chk) {
          if ($data[$chk->property] != null) {
            $dbq->filter[] = "(id=:id$dbc AND $xtype=:otid$dbc)";
            $dbq->params[":id$dbc"] = $data[$chk->property];
            $dbq->params[":otid$dbc"] = $chk->type_uuid;
            $dbc++;
          }
        }
        if ((count($dbq->filter) > 0) && ($this->db->query("SELECT count(*) AS dbc FROM ".$ttable[$xid]." WHERE ".implode(' OR ', $dbq->filter), $dbq->params)[0]->dbc != $dbc)) {
          return null;
        }
      }
    }

    // Format
    if (isset($vlist[11])) {
      for ($i=0; $i<count($vlist[11]); $i++) {
        if (in_array($data[$vlist[11][$i]->property], [ null, '•••••' ])) {
          unset($vlist[11][$i]);
        }
        else {
          $data[$vlist[11][$i]->property] = password_hash($data[$vlist[11][$i]->property], PASSWORD_BCRYPT);
        }
      }
    }
    if (isset($vlist[12])) {
      for ($i=0; $i<count($vlist[12]); $i++) {
        if (in_array($data[$vlist[12][$i]->property], [ null, '•••••' ])) {
          unset($vlist[12][$i]);
        }
        else {
          $data[$vlist[12][$i]->property] = basebq::encrypt($data[$vlist[12][$i]->property], $_SESSION['obstack']['basebq']);
        }
      }
    }
    if (isset($vlist[8])) {
      for ($i=0; $i<count($vlist[8]); $i++) {
        if ($data[$vlist[8][$i]->property] == null) {
          unset($vlist[8][$i]);
        }
        else {
          $data[$vlist[8][$i]->property] = mb_substr($data[$vlist[8][$i]->property],0,10);
        }
      }
    }
    if (isset($vlist[9])) {
      for ($i=0; $i<count($vlist[9]); $i++) {
        if ($data[$vlist[9][$i]->property] == null) {
          unset($vlist[9][$i]);
        }
        else {
          $vlist[9][$i]->value = substr_replace(mb_substr($vlist[9][$i]->value,0,16),'T',10,1);
          $data[$vlist[9][$i]->property] = substr_replace(mb_substr($data[$vlist[9][$i]->property],0,16),'T',10,1);
        }
      }
    }

    // Save Object
    $chlog = true;
    if ($id == null) {
      $id = $this->db->insert('obj', [ ':objtype'=>$otid]);
      $this->log_save($otid, $id, 1, 'Object created');
      usleep(100000);
      $chlog = false;
    }
    $vlog = [];
    // Process per value type / table
    foreach ($vlist as $type=>$property) {
      $dbc = 0;
      $dbq = (object)[ 'filter'=>[], 'params'=>[] ];
      // Prepare
      foreach ($property as $value) {
        if ($data[$value->property] !== null && in_array($value->property, array_keys($data)) && $value->value != $data[$value->property]) {
          $dbq->filter[] = "(:obj$dbc,:objproperty$dbc,:value$dbc)";
          $dbq->params[":obj$dbc"] = $id;
          $dbq->params[":objproperty$dbc"] = $value->property;
          $dbq->params[":value$dbc"] = $data[$value->property];
          $vlog[] = $value->name;
          $dbc++;
        }
      }
      // Save values
      if ($dbc > 0 && $type != 15) {
        $datatype = $this->datatype[$type];
        $dbq->filter = implode(',', $dbq->filter);
        $dbqconflict = ($this->db->driver()->mysql)
          ? "ON DUPLICATE KEY UPDATE value = VALUES(value)"
          : "ON CONFLICT (obj, objproperty) DO UPDATE SET value = excluded.value";
        $dbquery = "
          INSERT INTO value_$datatype (obj, objproperty, value)
          VALUES $dbq->filter
          $dbqconflict
        ";
        $this->db->query($dbquery, $dbq->params);
      }
    }

    // Update log
    $vlog = implode(', ', $vlog);
    if ($chlog && strlen($vlog) > 0) {
      $this->log_save($otid, $id, 2, $vlog);
    }

    // Save relations
    if (isset($data['relations'])) {
      $xlist = [];
      $otlist = [];
      // Current relations with log state
      foreach ($this->db->query('SELECT obj, obj_ref as ref FROM obj_obj WHERE obj=:obj OR obj_ref=:objr', [':obj'=>$id, ':objr'=>$id]) as $rec) {
        if (!isset($xlist[$rec->obj]) && $rec->obj != $id) { $xlist[] = $rec->obj; }
        elseif (!isset($xlist[$rec->ref]) && $rec->ref != $id) { $xlist[] = $rec->ref; }
      }
      $dbqin = $this->list2in(array_merge($xlist, $data['relations']));
      if (count($dbqin->params) > 0) {
        foreach ($this->db->query("SELECT id, objtype FROM obj WHERE id IN ($dbqin->marks)", $dbqin->params) as $dbrow) {
          $otlist[$dbrow->id] = $dbrow->objtype;
        }
      }

      // Detemine, check, save and log new relations
      $rlist = array_diff($data['relations'], $xlist);
      $relation_allow = [];
      foreach ($this->relation_list($otid,$id,true) as $rel) {
        $relation_allow[] = $rel->id;
      }
      foreach ($rlist as $rel) {
        if (!in_array($rel, $relation_allow)) { return false; }
      }
      $this->relation_save($otid, $id, $rlist, true);
      foreach ($rlist as $rel) {
        $this->log_save($otid, $id, 5, $this->list_short(null, [$rel])[$rel]);
        $this->log_save($otlist[$rel], $rel, 5, $this->list_short(null, [$id])[$id]);
      }
      // Determine, remove and log deleted relations
      $rlist = array_diff($xlist, $data['relations']);
      $this->relation_delete($otid, $id, $rlist, true);
      foreach ($rlist as $rel) {
        $this->log_save($otid, $id, 6, $this->list_short(null, [$rel])[$rel]);
        $this->log_save($otlist[$rel], $rel, 6, $this->list_short(null, [$id])[$id]);
      }
    }

    return [ 'id'=>$id ];
  }

  /******************************************************************
   * delete object
   ******************************************************************/
  public function delete($otid, $id) {
    $acl = $this->objtype->acl($otid);
    if (!$acl->delete) { return null; }

    // Apply plugin(s)
    if ($this->plugins->hasPlugin($otid, 'delete')) {
      $tmpobj = (object)[ 'id'=>$id ];
      foreach($this->open($otid, $id) as $key=>$value) {
        $tmpobj->$key = $value;
      }
      $this->plugins->apply('delete', $otid, $tmpobj);
    }

    // Get short
    $short = $this->list_short(null, [$id])[$id];

    // Delete values
    $tlist = [];
    foreach ($this->db->select('*', 'objproperty', [':objtype'=>$otid]) as $dbrow) {
      if (!isset($tlist[$dbrow->type])) {
        $tlist[] = $dbrow->type;
      }
    }
    foreach ($tlist as $type) {
      $this->db->delete('value_'.$this->datatype[$type], [':obj'=>$id]);
    }

    // Delete relations
    $xlist = [];
    $otlist = [];
    foreach ($this->db->query('SELECT obj, obj_ref as ref FROM obj_obj WHERE obj=:obj OR obj_ref=:objr', [':obj'=>$id, ':objr'=>$id]) as $rec) {
      if (!isset($xlist[$rec->obj]) && $rec->obj != $id) { $xlist[] = $rec->obj; }
      elseif (!isset($xlist[$rec->ref]) && $rec->ref != $id) { $xlist[] = $rec->ref; }
    }
    $dbqin = $this->list2in($xlist);
    if (count($dbqin->params) > 0) {
      foreach ($this->db->query("SELECT id, objtype FROM obj WHERE id IN ($dbqin->marks)", $dbqin->params) as $dbrow) {
        $otlist[$dbrow->id] = $dbrow->objtype;
      }
    }

    foreach ($xlist as $rel) {
      $this->log_save($otlist[$rel], $rel, 6, "$short (object deleted)");
    }
    $this->db->query('DELETE FROM obj_obj WHERE obj=:obj OR obj_ref=:objr', [':obj'=>$id, ':objr'=>$id]);

    // Delete object
    $this->log_save($otid, $id, 9, 'Object deleted');
    $this->db->delete('obj', [':objtype'=>$otid, ':id'=>$id]);

    return true;
  }

  /******************************************************************
   * Download file
   ******************************************************************/
  public function file_open($otid, $id, $propid) {
    if (!$this->objtype->acl($otid)->read) { return null; }

    // Get blob
    $query = '
      SELECT
        vb.value AS filename,
        vb.data as data
      FROM value_blob vb
      LEFT JOIN obj o ON o.id = vb.obj
      LEFT JOIN objproperty op ON op.id = vb.objproperty
      WHERE vb.objproperty = :propid
      AND vb.obj = :id
      AND o.objtype = :otid
      AND type = 15
    ';
    $content = null;
    $filename = null;
    if ($this->db->driver()->mysql) {
      $result = $this->db->query($query,[':otid'=>$otid, ':id'=>$id, ':propid'=>$propid] );
      if (!empty($result)) {
        $content = $result[0]->data;
        $filename = $result[0]->filename;
      }
    }
    else {
      $dbquery = $this->db->dbconn()->prepare($query);
      foreach ([':otid'=>$otid, ':id'=>$id, ':propid'=>$propid] as $paramkey => $paramvalue) {
        $dbquery->bindValue($paramkey, $paramvalue);
      }
      $dbquery->execute();
      $dbquery->bindColumn(1, $filename, PDO::PARAM_STR);
      $dbquery->bindColumn(2, $data, PDO::PARAM_LOB);
      $dbquery->fetch(PDO::FETCH_BOUND);

      // Read stream
      ob_start();
      fpassthru($data);
      $content = ob_get_contents();
      ob_end_clean();
    }

    // Present file
    ob_flush();
    if (isset($_GET['format']) && strtolower($_GET['format']) == 'base64') {
      echo($content);
    }
    else {
      header("Cache-Control: public");
      header("Content-Description: File Transfer");
      if (isset($_GET['format']) && strtolower($_GET['format']) == 'view') {
        header('Content-Type: image');
      }
      else {
        header("Content-Disposition: attachment; filename=".$filename);
      }
      header("Content-Transfer-Encoding: binary");
      echo(base64_decode($content));
    }
    die();
  }

  /******************************************************************
   * Upload file content
   ******************************************************************/
  public function file_save($otid, $id) {
    // Prepare
    $acl = $this->objtype->acl($otid);
    if ($id == null) {
      if (!$acl->create) { return null; }
    }
    else {
      if (!$acl->update) { return null; }
    }

    // Generate error on multiple max file size behaviours
    $fclear = [];
    if (empty($_FILES)) {
      return null;
    }
    foreach($_FILES as $fid => $file) {
      if ($file['error'] == 4) {    // https://www.php.net/manual/en/features.file-upload.errors.php
        $fclear[] = substr($fid,2);
      }
      elseif ($file['error'] != 0 || empty($file['name'])) {
        return null;
      }
    }
    // Store files
    foreach($_FILES as $fid => $file) {
      $dbqconflict = ($this->db->driver()->mysql)
        ? "ON DUPLICATE KEY UPDATE value = VALUES(value), data = VALUES(data)"
        : "ON CONFLICT (obj, objproperty) DO UPDATE SET value = excluded.value, data = excluded.data";
      $dbquery = "
        INSERT INTO value_blob (obj, objproperty, value, data)
        VALUES (:obj, :objproperty, :value, :data)
        $dbqconflict
      ";
      $this->db->query($dbquery, [':obj'=>$id, ':objproperty'=>substr($fid,2), ':value'=>$file['name'],':data'=>base64_encode(file_get_contents($file['tmp_name']))]);
    }
    // Clear files
    if (count($fclear) > 0) {
      $dbqin = $this->list2in($fclear, 'objp');
      $this->db->query("DELETE FROM value_blob WHERE obj=:id AND objproperty IN ($dbqin->marks)", array_merge([':id'=>$id], $dbqin->params));
    }

    return [];
  }

  /******************************************************************
   * List property values
   * ====================
   * $otid / $id / $propid   UUID or array of UUID's
   ******************************************************************/

  public function property_list($otid, $id, $propid) {
    if (!$this->objtype->acl($otid)->read) { return null; }
    $dbquery = '
      SELECT
        op.name AS name,
        op.type AS type,
        vv.value AS value
      FROM value_varchar vv
      LEFT JOIN obj o ON o.id = vv.obj
      LEFT JOIN objproperty op ON op.id = vv.objproperty
      WHERE vv.objproperty = :propid
      AND vv.obj = :id
      AND o.objtype = :otid
      AND TYPE IN (11,12)
    ';
    $result = $this->db->query($dbquery, [':otid'=>$otid, ':id'=>$id, ':propid'=>$propid]);
    if (empty($result)) {
      return [];
    }
    else {
      return $result[0];
    }
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
      $dbq = (object)[
        'join'=>[
          'LEFT JOIN objtype ot ON ot.id = o.objtype',
          'LEFT JOIN objtype_objtype oo ON oo.objtype = ot.id OR oo.objtype_ref = ot.id'
        ],
        'filter'=>[
          '((oo.objtype = :otid0 OR oo.objtype_ref = :otid1) OR (oo.objtype = :otid2 AND oo.objtype_ref = :otid3))'
        ],
        'params'=>[ ':otid0'=>$otid, ':otid1'=>$otid, ':otid2'=>$otid, ':otid3'=>$otid ]
      ];
      if (count($this->db->select('*', 'objtype_objtype', [ ':objtype'=>$otid, ':objtype_ref'=>$otid ])) < 1) {
        $dbq->filter[] = 'ot.id != :otid4';
        $dbq->params[':otid4'] = $otid;
      }
      if (!$_SESSION['sessman']['sa']) {
        $dbqin = $this->list2in($_SESSION['sessman']['groups'], 'smg');
        if (count($dbqin->params) <= 0) {
          $dbqin->marks = 'NULL';
        }
        $dbq->join[] = 'LEFT JOIN objtype_acl oa ON oa.objtype = ot.id';
        $dbq->filter[] = "oa.smgroup IN ($dbqin->marks) AND oa.read";
        $dbq->params = array_merge($dbq->params, $dbqin->params);
      }
      $dbq->join = implode(' ', $dbq->join);
      $dbq->filter = (count($dbq->filter) > 0) ? $dbq->filter = 'WHERE '.implode(' AND ', $dbq->filter) : '';
      $dbquery = "
        SELECT
          o.id AS id,
          o.objtype AS objtype,
          ot.name AS objtype_name,
          ot.short AS short
        FROM obj o
        $dbq->join
        $dbq->filter
      ";
      foreach ($this->db->query($dbquery, $dbq->params) as $rel) {
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
        WHERE (o.objtype = :ootid AND o.id = :oid)
        OR (r.objtype = :rotid AND r.id = :rid)
      ";
      foreach ($this->db->query($dbquery, [ ':ootid'=>$otid, ':rotid'=>$otid, ':oid'=>$id, ':rid'=>$id ]) as $rel) {
        if ($rel->obj == $id) {
          $xlist[$rel->obj_ref] = (object)[
            'id'=>$rel->obj_ref,
            'objtype'=>$rel->obj_ref_objtype,
            'objtype_name'=>$rel->obj_ref_name,
            'short'=>$rel->obj_ref_short
          ];
        }
        else {
          $xlist[$rel->obj] = (object)[
            'id'=>$rel->obj,
            'objtype'=>$rel->obj_objtype,
            'objtype_name'=>$rel->obj_name,
            'short'=>$rel->obj_short
          ];
        }
      }
    }
    $result = [];
    if ($this->format('aggr')) {
      foreach ($this->list_full(null, array_keys($xlist), 'text') as $rel) {
        if (in_array($rel->id, array_keys($xlist))) {
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
      }
    }
    else {
      $result = $this->list_full(null, array_keys($xlist));
    }
    return $result;
  }

  /******************************************************************
   * Create/Save relation
   ******************************************************************/
  public function relation_save($otid, $id, $ref, $private=false) {
    if (!$private && !$this->objtype->acl($otid)->update) { return null; }
    if ($otid == null || $id == null || $ref == null) { return null; }

    if (!is_array($ref)) { $ref = [ $ref ]; }
    $dbc = 0;
    $dbq = (object)[ 'filter'=>[], 'params'=>[] ];
    foreach ($ref as $id_ref) {
      $dbq->filter[] = ($id > $id_ref) ? "(:objx$dbc,:objy$dbc)" : "(:objy$dbc,:objx$dbc)";
      $dbq->params[":objx$dbc"] = $id;
      $dbq->params[":objy$dbc"] = $id_ref;
      $dbc++;
    }
    $dbq->filter = implode(',', $dbq->filter);
    $this->db->query("INSERT INTO obj_obj VALUES $dbq->filter", $dbq->params);
    return [];
  }

  /******************************************************************
   * Delete relation
   ******************************************************************/
  public function relation_delete($otid, $id, $ref, $private=false) {
    if (!$private && !$this->objtype->acl($otid)->delete) { return null; }
    if ($otid == null || $id == null || $ref == null) { return null; }

    if (!is_array($ref)) { $ref = [ $ref ]; }
    $dbc = 0;
    $dbq = (object)[ 'filter'=>[], 'params'=>[] ];
    foreach ($ref as $id_ref) {
      $dbq->filter[] = ($id > $id_ref) ? "(obj=:objx$dbc AND obj_ref=:objy$dbc)" : "(obj=:objy$dbc AND obj_ref=:objx$dbc)";
      $dbq->params[":objx$dbc"] = $id;
      $dbq->params[":objy$dbc"] = $id_ref;
      $dbc++;
    }
    $dbq->filter = implode(' OR ', $dbq->filter);
    $this->db->query("DELETE FROM obj_obj WHERE $dbq->filter", $dbq->params);
    return [];
  }

  /******************************************************************
   * Retrieve log
   ******************************************************************/
  private function log_list_full($id) {
    $log = $this->db->select('timestamp, username, action, details', 'obj_log',  [':obj'=>$id], 'timestamp DESC');
    for ($i=0; $i < count($log); $i++) {
      $log[$i]->timestamp = mb_substr($log[$i]->timestamp, 0, strrpos($log[$i]->timestamp, ':'));
    }
    return $log;
  }

  /******************************************************************
   * Publish log (public)
   ******************************************************************/
  public function log_list($otid, $id) {
    if (!$_SESSION['sessman']['sa']) { return null; }
    if ($this->db->select('log', 'objtype', [':id'=>$otid])[0]->log) {
      return $this->log_list_full($id);
    }
    return [];
  }

  /******************************************************************
   * Save log entry
   ******************************************************************/
  private function log_save($otid, $id, $action, $detail) {
    if ($this->objtype->log($otid)) {
      $this->db->query('INSERT INTO obj_log VALUES (:obj, now(), :username, :action, :detail)', [':obj'=>$id, ':username'=>$_SESSION['sessman']['username'], ':action'=>$action, ':detail'=>$detail]);
    }
  }

}
