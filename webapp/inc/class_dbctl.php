<?php

/******************************************************************
 *
 * dbctl()
 *  -> run($db)
 *
 ******************************************************************/

class dbctl {

  public static function run($db) {

    // Base configuration
    require_once '../config.php';
    require_once 'class_conf.php';
    $bcnf = new conf($obstack_conf);

    // Verify base configuration
    if ( count($bcnf->get()) == 0 || $bcnf->get('db_connectionstring') == null ) {
      die('Error in configuration<br><br>Please check:<br><a href="https://www.obstack.org/docs/?doc=general-configuration" target=_blank>https://www.obstack.org/docs/?doc=general-configuration</a><br>For upgrading please check:<br><a href="https://www.obstack.org/docs/?doc=general-configuration#upgrade-nodes" target=_blank>https://www.obstack.org/docs/?doc=general-configuration#upgrade-nodes</a>');
    }

    // Database connection
    require_once 'class_db.php';

    $dbconnstr = $bcnf->get('db_connectionstring');
    $dbconnstr = "pgsql:host=dev-psql;dbname=autodb;user=postgres;password=postgres";
    $db = new db($dbconnstr, $bcnf->get('db_persistent'));

    require_once 'class_dbdef.php';
    $dbdef = dbdef::definitions();
    $dbtrans = dbdef::translations($db);

    $translate = function($value) use ($dbtrans) {
      return (array_key_exists($value, $dbtrans)) ? $dbtrans[$value] : $value;
    };

    // Read database structure (query)
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
        tc.CONSTRAINT_NAME,
        tc.CONSTRAINT_TYPE,
        tc.TABLE_NAME,
        kcu.COLUMN_NAME,
        kcu.REFERENCED_TABLE_NAME,
        kcu.REFERENCED_COLUMN_NAME
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
        tc.CONSTRAINT_NAME,
        tc.CONSTRAINT_TYPE,
        tc.TABLE_NAME,
        kcu.COLUMN_NAME,
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
    }

    // Read database structure (execute)
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
          $clist[$table] = "CREATE TABLE $table (".implode(', ', $dbcolumns).")";
        }
        else {
          $ulist[$table] = [];
          foreach ($dbcolumns as $dbcolumn) {
            $ulist[$table][] = "ALTER TABLE $table ADD $dbcolumn";
          }
        }
      }
    }

    ksort($clist);
    ksort($ulist);

    // Phased output (unbuffered)
    ob_clean();
    header('Content-Type: text/html');
    header('Cache-Control: no-cache');
    header('X-Accel-Buffering: no');

    ?><!DOCTYPE html>
      <head>
        <title>ObStack</title>
        <link rel="icon" type="image/ico" href="../img/favicon.ico">
        <link type="text/css" rel="stylesheet" href="../css/index.css">
        <link type="text/css" rel="stylesheet" href="../css/setup.css">
      </head>
      <body>
        <div class="info">
          <span class="title">ObStack - Updating database...</span>
          <div class="info-content">
    <?php

    while (ob_get_level() > 0) { ob_end_flush(); }
    ob_implicit_flush(true);

    $bfill = str_repeat(' ', 2048);
    echo("{$bfill}<br>\n");
    flush();

    # Create new tables
    foreach ($clist as $tname=>$query) {
      echo("Create: {$tname}");
      $db->query($query);
      echo("{$bfill}<br>\n");
      flush();
    }

    # Update existing tables
    foreach ($ulist as $tname=>$queries) {
      echo("Update: {$tname} <br>");
      foreach ($queries as $query) {
        $db->query($query, []);
      }
      flush();
    }

    # Finalize
    echo("<br>\nDone!");
    flush();

    exit();

  }

}

dbctl::run(null);
