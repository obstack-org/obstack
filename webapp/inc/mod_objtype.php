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
  private $cache;
  private $display;
  private $valuetype = [1=>'varchar', 2=>'decimal', 3=>'uuid', 4=>'timestamp', 5=>'text'];
  private $acl;

  /******************************************************************
   * Initialize
   ******************************************************************/
  public function __construct($db) {
    $this->db = $db;
    $this->acl = [];
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
   * Retrieve permissions
   ******************************************************************/
  public function acl($otid) {
    if (!in_array($otid, $this->acl)) {
      if ($_SESSION['sessman']['sa']) {
        if (count($this->db->query('SELECT id FROM objtype o WHERE id = :otid', [ 'otid'=>$otid ]))>0) {
          $this->acl[$otid] = (object)[ 'read'=>true, 'create'=>true, 'update'=>true, 'delete'=>true ];
        }
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
          $this->acl[$otid] = $this->db->query($dbquery, [':otid'=>$otid])[0];
        }
      }
    }
    return $this->acl[$otid];
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
    if (!$_SESSION['sessman']['sa']) {
      $groups = str_replace('"','\'',substr(json_encode($_SESSION['sessman']['groups']),1,-1));
      $dbq->filter[] = "ota.smgroup IN ($groups) AND ota.read";
      $dbq->join[] = 'LEFT JOIN objtype_acl AS ota ON ota.objtype = ot.id';
    }
    $dbq->select[] = 'DISTINCT ot.id AS id, ot.name AS name';
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
    if ($this->format == 'aggr') {
      $result = [];
      foreach ($this->db->query($dbquery, $dbq->params) as $ot) {

        $meta = null;
        $property_list = $this->property_list($ot->id);
        if ($this->display == 'edit') {
          $meta = (object)[ 'objecttype'=>(object)[], 'valuemap'=>(object)[] ];
          $mod_obj = new mod_obj($this->db, $this);
          $mod_valuemap = new mod_valuemap($this->db);
          foreach($property_list as $property) {
            if ($property->type == 3) {
              $tobjtype = $property->type_objtype;
              $meta->objecttype->$tobjtype = [];
              foreach($mod_obj->list_short([$tobjtype], null) as $sid=>$sname) {
                $meta->objecttype->$tobjtype[] = (object)[ 'id'=>$sid, 'name'=>$sname ];
              }
            }
            if ($property->type == 4) {
              $tvaluemap = $property->type_valuemap;
              $meta->valuemap->$tvaluemap = $mod_valuemap->value_list($tvaluemap);
            }
          }
        }
        $otresult = (object)[
          'objecttype'=> $ot,
          'property'=>$property_list,
          'meta'=>$meta,
          'acl'=> $this->acl($ot->id)
        ];
        if ($otresult->meta == null) {
          unset($otresult->meta);
        }
        $result[] = $otresult;
      }
      return $result;
    }
    else {
      return $this->db->query($dbquery, $dbq->params);
    }
  }

  /******************************************************************
   * Save object type
   ******************************************************************/
  public function save($id, $data) {
    // Objtype configuration
    $dbparams = [];
    if (isset($data['name']))   { $dbparams['name'] = $data['name']; }
    if (isset($data['log']))    { $dbparams['log'] = ($data['log'] || $data['log'] == '1') ? 'true' : 'false'; }
    if (isset($data['short']))  { $dbparams['short'] = $data['short']; }
    // Objtype create / update
    if ($id == null) {
      $id = $this->db->query('INSERT INTO objtype (name, log, short) VALUES (:name, :log, :short) RETURNING id', $dbparams)[0]->id;
      $result = [ 'id'=>$id ];
    }
    else {
      if (isset($data['log'])) {
        $logstate = ($data['log'] == "1") ? true : false;
        if ($this->db->query('SELECT log FROM objtype WHERE id=:id', [':id'=>$id])[0]->log != $logstate) {
          $logmsg = ($logstate) ? 'enabled' : 'disabled';
          foreach ($this->db->query('SELECT id FROM obj WHERE objtype=:id', [':id'=>$id]) as $rec) {
            $this->db->query('INSERT INTO obj_log VALUES (:obj,now(),:username,10,:detail)', [':obj'=>$rec->id, ':username'=>$_SESSION['sessman']['username'], ':detail'=>"Logging $logmsg"]);
          }
        }
      }
      $dbparams[':id'] = $id;
      $result = $this->db->query('UPDATE objtype SET name=:name, log=:log, short=:short WHERE id=:id', $dbparams);
    }
    // Objtype properties
    if (isset($data['property'])) {
      // Properties from HTTP request
      $prio = 1;
      $htlist = [];
      foreach ($data['property'] as $rec) {
        $rec['prio'] = $prio;
        $tmpid = $this->property_save($id, $rec['id'], $rec)[0]->id;
        if ($rec['id'] == null) {
          $rec['id'] = $tmpid;
        }
        array_push($htlist, $rec['id']);
        $prio++;
      }
      // Properties from database
      $dblist = [];
      foreach ($this->db->query('SELECT id FROM objproperty WHERE objtype=:id ORDER BY prio', [':id'=>$id]) as $dbrec) {
        array_push($dblist, $dbrec->id);
      }
      // Delete values
      sort($htlist);
      sort($dblist);
      foreach (array_diff($dblist, $htlist) as $rec) {
        $this->property_delete($id, $rec);
      }
    }
    return $result;
  }

  /******************************************************************
   * Delete object type
   ******************************************************************/
  public function delete($otid) {
    $dbparams = [':otid'=>$otid];
    foreach($this->valuetype as $type) {
      $this->db->query("DELETE FROM value_$type WHERE objproperty IN (SELECT id FROM objproperty WHERE objtype=:otid)", $dbparams);
    }
    $this->db->query('DELETE FROM obj WHERE objtype=:otid', $dbparams);
    $this->db->query('DELETE FROM objproperty WHERE objtype=:otid', $dbparams);
    $this->db->query('DELETE FROM objtype_acl WHERE objtype=:otid', $dbparams);
    $count = count($this->db->query('DELETE FROM objtype WHERE id=:otid RETURNING *', $dbparams));
    return $count > 0;
  }

  /******************************************************************
   * List object type properties
   ******************************************************************/
  public function property_list($otid, $id=null) {
    // Process ACL
    if (!$this->acl($otid)->read) { return null; }
    // Process list
    $dbparams = [':otid'=>$otid];
    $dbqid = 'op.id AS id,';
    $dbqproperty = '';
    if ($id != null) {
      $dbparams[':id'] = $id;
      $dbqid = '';
      $dbqproperty = 'AND op.id = :id';
    }
    $dbquery = "
      SELECT
        $dbqid
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
      WHERE ot.id = :otid
      $dbqproperty
      ORDER BY op.prio, op.name
    ";
    $result = $this->db->query($dbquery, $dbparams);
    if ($id == null) {
      return $result;
    }
    else {
      return $result[0];
    }
  }

  /******************************************************************
   * Save object type property
   ******************************************************************/
  public function property_save($otid, $id, $data) {
    $fields = 'objtype';
    $insert = ':objtype';
    $update = '';
    $dbparams = [':objtype'=>$otid];
    $statickeys = ['id'];
    if ($id != null) {
      $statickeys = ['id', 'type', 'type_objtype', 'type_valuemap'];
    }
    foreach ($data as $key => $value) {
      if (!in_array($key, $statickeys)) {
        $fields .= ", $key";
        $insert .= ", :$key";
        $update .= ", $key=:$key";
        if (is_bool($value)) {
          $dbparams[":$key"] = ($value || $value == '1') ? 'true' : 'false';
        }
        else {
          $dbparams[":$key"] = $value;
        }
      }
    }
    $update = substr($update, 2);
    if ($data['id'] == null && $id != null) {
      $data['id'] = $id;
    }
    if ($data['id'] == null) {
      $dbquery = "INSERT INTO objproperty ($fields) VALUES($insert) RETURNING id";
    }
    else {
      $dbquery = "UPDATE objproperty SET $update WHERE id=:id AND objtype=:objtype";
      $dbparams['id'] = $data['id'];
    }
    return $this->db->query($dbquery, $dbparams);
  }

  /******************************************************************
   * Delete object type property
   ******************************************************************/
  public function property_delete($otid, $id) {
    $count = count($this->db->query('DELETE FROM objproperty WHERE objtype=:otid AND id=:id', [':otid'=>$otid, ':id'=>$id]));
    return $count > 0;
  }

}
