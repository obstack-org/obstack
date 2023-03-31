<?php
/******************************************************************
 * 
 * api_objtype($db)
 *  -> list()
 *  -> list_short($id, $obj)
 *  -> open($id)
 *  -> save($id)
 *  -> delete($id)
 *  -> property_list($otid, $id)
 *  -> property_save($otid, $id)
 *  -> property_delete($otid, $id)
 * 
 ******************************************************************/

class mod_objtype { 

  private $db;
  private $format = null;
  private $valuetype = [1=>'varchar', 2=>'decimal', 3=>'uuid', 4=>'timestamp', 5=>'text'];

  /******************************************************************
   * Initialize
   ******************************************************************/
  function __construct($db) {
    $this->db = $db;
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
   * Boolean as string
   ******************************************************************/
  private function bool2str($var) {
    if ($var) return 'true';
    else return 'false';
  }

  /******************************************************************
   * List object types
   ******************************************************************/  
  function list($id = null) {
    if ($id == null) {
      return $this->db->query('SELECT id, name FROM objtype ORDER BY name', []);
    }
    else {
      return $this->db->query('SELECT name, log, short FROM objtype WHERE id=:id', [':id'=>$id])[0];
    } 
  }

  /******************************************************************
   * Generate list of object with short names with a maximun depth
   *   For both $id and $obj set to null to ignore
   *     list_short(null,null);     All objects in the system
   *     list_short([id],null);     All objects from objtype [id]
   *     list_short([id],[obj]);    Only the selected object
   ******************************************************************/ 
  function list_short($id, $obj) {
    // Call limited function to prevent infinite loop
    $short = $this->list_short_limit($id, $obj, 0);
    // Return correct format on empty result
    if (count($short) == 1) {
      if ($short[0]['id'] == null) {
        return [];
      }
    }
    // Return result
    return $short;
  }

  /******************************************************************
   * Functionality of list_short() with a maximum depth
   ******************************************************************/   
  private function list_short_limit($id, $obj, $depth) {
    // Prepare values based on $obj
    $dbqobj = '';
    $dbqobjtype = '';
    $dbparams = [];
    if ($obj != null) {
      $dbqobj = 'AND o.id=:obj';
      $dbparams['obj'] = $obj;
    }
    if ($id != null) {
      $dbqobjtype = 'AND ot.id=:id';
      $dbparams['id'] = $id;
    }
    // Query
    $dbquery = "
      SELECT 
        o.id AS id, 
        op.name AS label,      
        op.type AS type,
        CASE op.type
          WHEN 1 THEN vv.value
          WHEN 2 THEN rtrim(TO_CHAR(vd.value, 'FM99999999.99999999'),'.')
          WHEN 3 THEN vu.value::varchar
          WHEN 4 THEN vo.name
          WHEN 5 THEN 
            CASE
              WHEN (TO_CHAR(vd.value,'FM9') = '1') THEN 'V'
              ELSE 'X'
            END
          WHEN 6 THEN vx.value
          WHEN 7 THEN '•••••'
          WHEN 8 THEN TO_CHAR(vt.value,'YYYY-MM-DD')
          WHEN 9 THEN TO_CHAR(vt.value,'YYYY-MM-DD HH24:MI')
          END AS value,
        ot.short as short
      FROM obj o 
      LEFT JOIN objtype ot ON ot.id = o.objtype 
      LEFT JOIN objproperty op ON op.objtype = ot.id 
      LEFT JOIN value_decimal   vd ON vd.objproperty = op.id AND vd.obj = o.id
      LEFT JOIN value_text 		  vx ON vx.objproperty = op.id AND vx.obj = o.id
      LEFT JOIN value_timestamp vt ON vt.objproperty = op.id AND vt.obj = o.id
      LEFT JOIN value_uuid 		  vu ON vu.objproperty = op.id AND vu.obj = o.id
      LEFT JOIN value_varchar 	vv ON vv.objproperty = op.id AND vv.obj = o.id
      LEFT JOIN valuemap_value vo ON vo.id = vu.value    
      WHERE 1=1 $dbqobj $dbqobjtype
      ORDER BY o.id, op.prio, op.name
    ";
    // Format result
    $tmpid = null;
    $cache = '';
    $length = 0;
    $result = [];
    foreach ($this->db->query($dbquery, $dbparams) as $rec) {
      // When ID changes
      if ($tmpid != $rec->id) {  
        // Add "previous" data array to result, except on the firt run (then cache is empty)
        if (strlen($cache) > 0) {  
          array_push($result, ['id'=>$tmpid, 'name'=>substr($cache,2)]);
        }
        // Reset id + cache
        $tmpid = $rec->id;
        $cache = '';
        $length = 0;
      }
      // Add value
      if ($length < $rec->short) {
        if ($rec->type == 3) {
          // Objtype, with loop protection when selecting self
          if ($depth < 3) {
            if ($rec->value != null) {    
              $cache .= ' / '.$this->list_short_limit(null, $rec->value, $depth+1)[0]['name'];
            }
          }
        }
        // Any other value
        else {
          $cache .= ', '.$rec->value;
        }
      }
      $length++;
    }
    // Add the last data array
    array_push($result, ['id'=>$tmpid, 'name'=>substr($cache,2)]);
    // Return result
    return $result;
  }

  /******************************************************************
   * Open object type
   ******************************************************************/  
  function open($otid) {
    // Handle formats
    if ($this->format('short'))   { return $this->list_short($otid, null); }
    //if ($this->format('config'))  { return $this->config_open($otid); }
    $result = [];
    $dbqvaluemap = ['vu.value::varchar', ''];
    $dbqcheckbox = "TO_CHAR(vd.value,'FM9')";
    $dbqpassword = '*****';
    if ($this->format('hr')) {
      $dbqvaluemap = ['vo.name', 'LEFT JOIN valuemap_value vo ON vo.id = vu.value'];
      $dbqcheckbox = "
        CASE
          WHEN (TO_CHAR(vd.value,'FM9') = '1') THEN 'V'
          ELSE 'X'
        END
      ";
    }
    if ($this->format('gui')) {
      $dbqvaluemap = ['vo.name', 'LEFT JOIN valuemap_value vo ON vo.id = vu.value'];
      $dbqcheckbox = "
        CASE
          WHEN (TO_CHAR(vd.value,'FM9') = '1') THEN '&nbsp;✓'
          ELSE '&nbsp;✗'
        END
      ";
      $dbqpassword = '•••••';
    }
    // Gather data
    $dbquery = "
      SELECT 
        o.id AS id, 
        op.name AS label,
        op.type AS type,
        CASE op.type
          WHEN 1 THEN vv.value
          WHEN 2 THEN rtrim(TO_CHAR(vd.value, 'FM99999999.99999999'),'.')
          WHEN 3 THEN vu.value::varchar
          WHEN 4 THEN $dbqvaluemap[0]
          WHEN 5 THEN $dbqcheckbox
          WHEN 6 THEN vx.value
          WHEN 7 THEN '$dbqpassword'
          WHEN 8 THEN TO_CHAR(vt.value,'YYYY-MM-DD')
          WHEN 9 THEN TO_CHAR(vt.value,'YYYY-MM-DD HH24:MI')
        END AS value
      FROM obj o 
      LEFT JOIN objtype ot ON ot.id = o.objtype 
      LEFT JOIN objproperty op ON op.objtype = ot.id 
      LEFT JOIN value_decimal		vd ON vd.objproperty = op.id AND vd.obj = o.id
      LEFT JOIN value_text 		  vx ON vx.objproperty = op.id AND vx.obj = o.id
      LEFT JOIN value_timestamp vt ON vt.objproperty = op.id AND vt.obj = o.id
      LEFT JOIN value_uuid 		  vu ON vu.objproperty = op.id AND vu.obj = o.id
      LEFT JOIN value_varchar 	vv ON vv.objproperty = op.id AND vv.obj = o.id
      $dbqvaluemap[1] 
      WHERE ot.id = :id
      ORDER BY o.id, op.prio, op.name
    ";
    // Format data
    $tmpid = NULL;
    $cache = [];
    foreach ($this->db->query($dbquery, [':id'=>$otid]) as $rec) {
      if ($tmpid != $rec->id) {
        if (count($cache) > 0) {
          array_push($result, $cache);
        }
        $tmpid = $rec->id;
        $cache = ['id'=>$rec->id];
      }
      if ($rec->type == 3) {
        if ($rec->value != null) {
          $cache[$rec->label] = $this->list_short(null, $rec->value)[0]['name'];
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
    if ($result[0] == []) {
      $result = [];
    }
    return $result;
  }

  /******************************************************************
   * Save object type
   ******************************************************************/  
  function save($id, $data) {
    $dbparams = [];
    // Objtype configuration
    if (isset($data['name']))   { $dbparams['name'] = $data['name']; }
    if (isset($data['log']))    { $dbparams['log'] = $this->bool2str($data['log']); }    
    if (isset($data['short']))  { $dbparams['short'] = $data['short']; }    
    // Objtype create / update
    if ($id == null) {
      $id = $this->db->query('INSERT INTO objtype (name, log, short) VALUES (:name, :log, :short) RETURNING id', $dbparams)[0]->id;
      $result = $id;
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
  function delete($otid) {
    $dbparams = [':otid'=>$otid];
    foreach($this->valuetype as $type) {
      $this->db->query("DELETE FROM value_$type WHERE objproperty IN (SELECT id FROM objproperty WHERE objtype=:otid)", $dbparams);
    }    
    $this->db->query('DELETE FROM obj WHERE objtype=:otid', $dbparams);
    $this->db->query('DELETE FROM objproperty WHERE objtype=:otid', $dbparams);
    $count = count($this->db->query('DELETE FROM objtype WHERE id=:otid RETURNING *', $dbparams));
    return ($count > 0);
  }

  /******************************************************************
   * List object type properties
   ******************************************************************/  
  function property_list($otid, $id = null) {    
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
        op.type as type,
        op.type_objtype as type_objtype,
        op.type_valuemap as type_valuemap,
        op.required as required, 
        op.frm_readonly as frm_readonly, 
        op.frm_visible as frm_visible, 
        op.tbl_visible as tbl_visible, 
        op.tbl_orderable as tbl_orderable
      FROM objproperty op 
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
  function property_save($otid, $id, $data) {
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
          $dbparams[":$key"] = $this->bool2str($value);
        }
        else {
          $dbparams[":$key"] = $value;
        }                    
      }
    }
    $update = substr($update, 2);
    if ($data['id'] == null) {
      if ($id != null) {
        $data['id'] = $id;
      }
    }  
    if ($data['id'] == null) {
      $dbquery = "INSERT INTO objproperty ($fields) VALUES($insert) RETURNING id";
      //$log_create .= ", ".$rec['name'];
    }
    else {
      $dbquery = "UPDATE objproperty SET $update WHERE id=:id AND objtype=:objtype";
      $dbparams['id'] = $data['id'];
      //$log_update .= ", ".$rec['name'];
    }
    return $this->db->query($dbquery, $dbparams);
  }

  /******************************************************************
   * Delete object type property
   ******************************************************************/  
  function property_delete($otid, $id) {
    $count = count($this->db->query('DELETE FROM objproperty WHERE objtype=:otid AND id=:id', [':otid'=>$otid, ':id'=>$id]));
    return ($count > 0);
  }
  
}