<?php

/******************************************************************
 *
 * dbctl()
 *  -> run($db)
 *
 ******************************************************************/

class dbctl {

  public static function run($db) {

    global $dbcver;

    require_once 'class_dbdef.php';
    $dbdef = dbdef::definitions();
    $dbtrans = dbdef::translations($db);

    $translate = function($value) use ($dbtrans) {
      return (array_key_exists($value, $dbtrans)) ? $dbtrans[$value] : $value;
    };

    // Define database structure queries
    $dbquery = null;
    if ($db->driver()->mysql) {
      $dbtmpn = 'database()';
      $dbname = $db->query('SELECT database()', [])[0]->$dbtmpn;
      $dbquery_schema = "
      SELECT
        table_name,
        column_name,
        data_type,
        character_maximum_length,
        is_nullable,
        column_default
      FROM
        information_schema.columns
      WHERE
        table_schema = '$dbname'
      ORDER BY
        table_name, column_name
      ";
      $dbquery_constraints = "
      SELECT
        tc.CONSTRAINT_NAME as contraint_name,
        tc.CONSTRAINT_TYPE as contraint_type,
        tc.TABLE_NAME as table_name,
        kcu.COLUMN_NAME as column_name,
        kcu.REFERENCED_TABLE_NAME as referenced_table_name,
        kcu.REFERENCED_COLUMN_NAME as referenced_column_name
      FROM
        information_schema.TABLE_CONSTRAINTS AS tc
      JOIN
        information_schema.KEY_COLUMN_USAGE AS kcu
        ON tc.CONSTRAINT_NAME = kcu.CONSTRAINT_NAME
        AND tc.TABLE_SCHEMA = kcu.TABLE_SCHEMA
        AND tc.TABLE_NAME = kcu.TABLE_NAME
      WHERE
        tc.CONSTRAINT_TYPE IN ('PRIMARY KEY', 'FOREIGN KEY', 'UNIQUE')
        AND tc.TABLE_SCHEMA = '$dbname'
      ORDER BY
        tc.TABLE_NAME,
        kcu.COLUMN_NAME,
        tc.CONSTRAINT_TYPE desc
      ";
      $dbquery_checks = "
        SELECT
          cc.CONSTRAINT_NAME as contraint_name,
          cc.TABLE_NAME as table_name,
          cc.CHECK_CLAUSE as check_clause
        FROM
          information_schema.CHECK_CONSTRAINTS AS cc
        JOIN
          information_schema.TABLE_CONSTRAINTS AS tc
          ON cc.CONSTRAINT_NAME = tc.CONSTRAINT_NAME
          AND cc.CONSTRAINT_SCHEMA = tc.CONSTRAINT_SCHEMA
        WHERE
          tc.CONSTRAINT_TYPE = 'CHECK'
          AND cc.CONSTRAINT_SCHEMA = '$dbname'
        ORDER BY
          TABLE_NAME
      ";
    }
    else {
      $db->query('CREATE EXTENSION IF NOT EXISTS "pgcrypto"', []);
      $db->query('CREATE EXTENSION IF NOT EXISTS "uuid-ossp"', []);
      $dbname = $db->query('SELECT current_database()', [])[0]->current_database;
      $dbschm = $db->query('SELECT current_schema()', [])[0]->current_schema;
      $dbquery_schema = "
      SELECT
        table_name,
        column_name,
        data_type,
        character_maximum_length,
        is_nullable,
        column_default
      FROM
        information_schema.columns
      WHERE
        table_catalog = '$dbname'
      AND
        table_schema = '$dbschm'
      ORDER BY
        table_name, column_name
      ";
      $dbquery_constraints = "
      SELECT
        tc.CONSTRAINT_NAME as contraint_name,
        tc.CONSTRAINT_TYPE as contraint_type,
        tc.TABLE_NAME as table_name,
        kcu.COLUMN_NAME as column_name,
        ccu.TABLE_NAME AS referenced_table_name,
        ccu.COLUMN_NAME AS referenced_column_name
      FROM
        information_schema.table_constraints AS tc
      JOIN
        information_schema.key_column_usage AS kcu
        ON tc.constraint_name = kcu.constraint_name
        AND tc.constraint_schema = kcu.constraint_schema
      LEFT JOIN
        information_schema.constraint_column_usage AS ccu
        ON tc.constraint_name = ccu.constraint_name
        AND tc.constraint_schema = ccu.constraint_schema
      WHERE
        tc.constraint_type IN ('PRIMARY KEY', 'FOREIGN KEY', 'UNIQUE')
        AND tc.CONSTRAINT_CATALOG = '$dbname'
        AND tc.TABLE_SCHEMA = '$dbschm'
        AND ccu.TABLE_SCHEMA = '$dbschm'
      ORDER BY
        tc.TABLE_NAME,
        kcu.COLUMN_NAME,
        tc.CONSTRAINT_TYPE desc
      ";
      $dbquery_checks = "
        SELECT
          cc.constraint_name AS constraint_name,
          tc.table_name AS table_name,
          cc.check_clause AS check_clause
        FROM
          information_schema.check_constraints AS cc
        JOIN
          information_schema.table_constraints AS tc
          ON cc.constraint_name = tc.constraint_name
          AND cc.constraint_schema = tc.constraint_schema
        WHERE
          tc.constraint_type = 'CHECK'
          AND cc.constraint_schema = '$dbschm'
          AND tc.constraint_catalog = '$dbname'
        ORDER BY
          TABLE_NAME
      ";
    }

    // Read database structure
    $dbschema = [];
    $ctab = null;
    foreach ($db->query($dbquery_schema, []) as $dbrec) {
      if ($dbrec->table_name != $ctab) {
        $dbschema[$dbrec->table_name] = [];
        $ctab = $dbrec->table_name;
      }
      $dbschema[$dbrec->table_name][$dbrec->column_name] = [
        $dbrec->data_type,
        $dbrec->character_maximum_length,
        ($dbrec->is_nullable == 'YES'),
        ($dbrec->column_default == 'NULL') ? null : $dbrec->column_default
      ];
    }
    // -- Constraints
    $dbconstraints = [];
    $ctab = null;
    foreach ($db->query($dbquery_constraints, []) as $dbrec) {
      if ($dbrec->table_name != $ctab) {
        $dbconstraints[$dbrec->table_name] = [];
        $ctab = $dbrec->table_name;
      }
      $ctype = strtolower($dbrec->contraint_type[0]);
      if (!isset($dbconstraints[$dbrec->table_name][$ctype])) {
        $dbconstraints[$dbrec->table_name][$ctype] = [];
      }
      if ($ctype == "f") {
        $dbconstraints[$dbrec->table_name][$ctype][$dbrec->column_name] = [
          $dbrec->referenced_table_name,
          $dbrec->referenced_column_name
        ];
      }
      elseif ($ctype == "u") {
        $dbconstraints[$dbrec->table_name][$ctype][] = [ $dbrec->column_name ];
      }
      else {
        $dbconstraints[$dbrec->table_name][$ctype][] = [
          $dbrec->column_name,
          $dbrec->referenced_table_name,
          $dbrec->referenced_column_name
        ];
      }
    }
    // -- Checks (as constraints)
    $dbchecks = [];
    $ctab = null;
    foreach ($db->query($dbquery_checks, []) as $dbrec) {
      if ($dbrec->table_name != $ctab) {
        $dbchecks[$dbrec->table_name] = [];
        $ctab = $dbrec->table_name;
      }
      $dbchecks[$dbrec->table_name][] = str_replace(['`','(',')'], '', $dbrec->check_clause);
    }

    $clist = [];
    $ulist = [];

    // Process new tables and columns
    foreach ($dbdef as $table => $config) {
      $columns = $config[0];
      $constraints = $config[1];
      $isnew = !array_key_exists($table, $dbschema);
      $dbcolumns = [];
      // Gather new columns
      foreach ($columns as $column => $properties) {
        if ($isnew || !array_key_exists($column, $dbschema[$table])) {
          $column = ($db->driver()->mysql) ? "`$column`" : "\"$column\"";
          $dbcolumn = [ $column ];
          $coltype = $translate($properties[0]);
          if ($properties[1] != null) {
            $coltype .= "({$properties[1]})";
          }
          $dbcolumn = [ $column, $coltype ];
          if (!$properties[2]) {
            $dbcolumn[] = 'NOT NULL';
          }
          if ($properties[3] != null) {
            $dbcolumn[] = 'DEFAULT '.$translate($properties[3]);
          }
          $dbcolumns[] = implode(' ', $dbcolumn);
        }
      }
      // Create/Update columns
      if (!empty($dbcolumns)) {
        if ($isnew) {
          $clist[$table] = [];
          $clist[$table][] = "CREATE TABLE $table (".implode(', ', $dbcolumns).")";
        }
        else {
          $ulist[$table] = [];
          foreach ($dbcolumns as $dbcolumn) {
            $ulist[$table][] = "ALTER TABLE $table ADD $dbcolumn";
          }
        }
      }
      // Create constraints
      $tlist = [];
      // -- Primary key
      if (isset($constraints['p'])) {
        $addkey = [];
        foreach ($constraints['p'] as $pkey) {
          $haspkey = false;
          if (isset($dbconstraints[$table]['p'])) {
            foreach ($dbconstraints[$table]['p'] as $constraint) {
              if ($pkey == $constraint[0]) {
                $haspkey = true;
              }
            }
          }
          if (!$haspkey) {
            $addkey[] = $pkey;
          }
        }
        if (count($addkey) > 0) {
          $addkey = implode(',', $addkey);
          $tlist[] = "ALTER TABLE $table ADD PRIMARY KEY ($addkey)";
        }
      }
      // -- Foreign key
      if (isset($constraints['f'])) {
        $fkid = 0;
        foreach ($constraints['f'] as $fkey => $fref) {
          if (!isset($dbconstraints[$table]['f'][$fkey]) || $dbconstraints[$table]['f'][$fkey] != $fref ) {
            $cname_fref = (strpos($table, '_') !== false || strpos($fref[0], '_') !== false) ? $table : $table . "_" . $fref[0];
            $cname = $cname_fref . "_fk" . (($fkid > 0) ? "_$fkid" : "");
            $tlist[] = "ALTER TABLE $table ADD CONSTRAINT $cname FOREIGN KEY ($fkey) REFERENCES $fref[0]($fref[1]);";
          }
          $fkid++;
        }
      }
      // -- Unique
      if (isset($constraints['u'])) {
        foreach ($constraints['u'] as $ukey) {
          // $constraints['u'] has format [ [key1,key2], [key3] ], but...
          if (count($ukey) > 1) {
            echo("ERROR: UNIQUE with multiple keys is not supported");
            die();
          }
          else {
            $ukey = $ukey[0];
            $hasukey = false;
            foreach ($dbconstraints[$table]['u'] as $dbkey) {
              if ($dbkey[0] == $ukey) {
                $hasukey = true;
              }
            }
            if (!$hasukey) {
              $cname = $table . "_" . $ukey . "_un";
              $tlist[] = "ALTER TABLE $table ADD CONSTRAINT $cname UNIQUE ($ukey)";
            }
          }
        }
      }
      // -- Check
      if (isset($constraints['c'])) {
        foreach ($constraints['c'] as $check) {
          if (!in_array($check, $dbchecks[$table])) {
            $tlist[] = "ALTER TABLE $table ADD CHECK ($check)";
          }
        }
      }

      // Constraints to create/update lists
      if (count($tlist) > 0)
      {
        if ($isnew) {
          $clist[$table] = array_merge($clist[$table], $tlist);
        }
        else {
          if (!isset($ulist[$table])) {
            $ulist[$table] = [];
          }
          $ulist[$table] = array_merge($ulist[$table], $tlist);
        }
      }
    }

    # Create new tables
    foreach ($clist as $tname=>$queries) {
      echo("Create: {$tname}<br>");
      foreach ($queries as $query) {
        // echo("\n{$query}<br>\n"); // debug
        $db->query($query, []);
      }
      echo(str_repeat(' ', 4096));
      flush();
    }

    # Update existing tables
    foreach ($ulist as $tname=>$queries) {
      echo("Update: {$tname}<br>");
      foreach ($queries as $query) {
        // echo("\n{$query}<br>\n"); // debug
        $db->query($query, []);
      }
      echo(str_repeat(' ', 4096));
      flush();
    }

    echo("<br>");

    # Check base config
    $chkdefs = [ 'db_version'=>$dbcver, 'session_timeout'=>600, 'totp_default_enabled'=>0 ];
    foreach ($db->select('name', 'setting_decimal') as $dbrow) {
      if (array_key_exists($dbrow->name, $chkdefs)) {
        unset($chkdefs[$dbrow->name]);
      }
    }
    if (count($chkdefs) > 0) {
      echo("Create: [config:defaults]<br>");
      echo(str_repeat(' ', 4096));
      flush();
      foreach($chkdefs as $cfname=>$cfvalue) {
        $db->insert('setting_decimal', [':name'=>$cfname, ':value'=>$cfvalue]);
      }
    }

    # Check user admin
    if (empty($db->select('username', 'sessman_user', [':username'=>'admin']))) {
      echo("Create: [user:admin]<br>");
      echo(str_repeat(' ', 4096));
      flush();
      $db->query("INSERT INTO sessman_user (username,secret,active,sa) VALUES ('admin', crypt('admin', gen_salt('bf')), true, true);");
    }

    # Update database version
    if (!array_key_exists('db_version', $chkdefs)) {
      echo("Update: [config:version]<br>");
      echo(str_repeat(' ', 4096));
      flush();
      $db->update('setting_decimal', [':value'=>$dbcver], [':name'=>'db_version']);
    }

  }

}
