<?php
/******************************************************************
 *
 * api_obj($dbconn [,$username ,$password])
 *  -> query($dbquery, $dbparams)
 *
 * Examples:
 *    $dbconn = ['mysql:host=localhost;dbname=src', 'root', 'n0t\$0S#cr#T'];
 *    $dbconn = 'pgsql:host=dev-psql;dbname=test;user=postgres;password=n0t\$0S#cr#T';
 *    $dbconn = 'sqlite:../db/src.db';
 *
 *    $db     = new db($dbconn);
 *    $result = $db->query('select * from example where id=:id limit 1', ['id'=>$id]);
 *
 ******************************************************************/

class db {

  public $debug;
  private $dbconn;
  private $dbuser;
  private $dbpass;
  private $persistent;
  private $driver;

  /******************************************************************
   * Initialize with connection string
   ******************************************************************/
  public function __construct() {
    $args = func_get_args();
    $this->debug = false;
    $this->dbconn = null;
    $this->persistent = false;

    $options = [ PDO::ATTR_EMULATE_PREPARES=>false ];

    try {
      if (count($args) == 1) {
        $this->dbconn = new PDO($args[0]);
      }
      elseif (count($args) == 2) {
        $this->persistent = (is_bool($args[1]) && $args[1]);
        $options[PDO::ATTR_PERSISTENT] = $this->persistent;
        $this->dbconn = new PDO($args[0], null, null, $options);
      }
      elseif (count($args) == 3) {
        $this->dbconn = new PDO($args[0], $this->dbuser, $this->dbpass);
      }
      else {
        $this->persistent = (is_bool($args[3]) && $args[3]);
        $options[PDO::ATTR_PERSISTENT] = $this->persistent;
        $this->dbconn = new PDO($args[0], $this->dbuser, $this->dbpass, $options);
      }
    }
    catch (PDOException $e) {
      print 'Error!: ' . $e->getMessage() . '<br/>';
      die();
    }

    $this->driver = (object)[
      'psql'  => true,
      'mysql' => false,
      'mysql_legacy' => false
    ];
    $version = null;
    if (isset($_SESSION['db']['version'])) {
      $version = $_SESSION['db']['version'];
    }
    else {
      $version = $this->query('SELECT version() AS version')[0]->version;
      if (isset($_SESSION) && !isset($_SESSION['db'])) {
        $_SESSION['db'] = [];
        $_SESSION['db']['version'] = $version;
      }
    }
    if ($this->dbconn->getAttribute(PDO::ATTR_DRIVER_NAME) == 'mysql') {
      $this->driver->psql = false;
      $this->driver->mysql = true;
      $version = explode('.', $version);
      if (intval($version[0]) <= 10 && intval($version[1]) < 5) {
        $this->driver->mysql_legacy = true;
      }
    }
    $this->dbconn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
  }

  /******************************************************************
   * Return SQL driver (e.g. mysql, pgsql)
   ******************************************************************/
  public function driver() {
    return $this->driver;
  }

  /******************************************************************
   * Perform SQL query
   *  $query    : SQL query
   *  $params   : Array of parameters for PDO
   ******************************************************************/
  public function query($query, $params=[]) {
    try {
      $dbquery = $this->dbconn->prepare($query);
      foreach($params as $paramkey => $paramvalue) {
        $dbquery->bindValue($paramkey, $paramvalue);
      }
      $dbquery->execute();
      return $dbquery->fetchall(PDO::FETCH_CLASS);
    }
    catch (PDOException $e) {
      print 'Error!: ' . $e->getMessage() . '<br/>';
      die();
    }
  }

  /******************************************************************
   * Simple SQL select query (psql/mysql)
   *  $columns      : String of comma seperated column names
   *  $table        : Table name
   *  $params_where : Array of parameters for PDO ('AND' only)
   *  $order        : String of comma seperated column names
   ******************************************************************/
  public function select($columns, $table, $params_where=[], $order='') {
    $where = '';
    if (count($params_where) > 0) {
      $where = [];
      foreach(array_keys($params_where) as $key) {
        $where[] = str_replace(':', '', $key)."=$key";
      }
      $where = ' WHERE '.implode(' AND ', $where);
    }
    if (strlen($order) > 0) {
      $order = " ORDER BY $order";
    }
    return $this->query("SELECT $columns FROM $table$where$order", $params_where);
  }

  /******************************************************************
   * Simple SQL insert query returning the ID (psql/mysql)
   *  $table    : Table name
   *  $params   : Array of parameters for PDO
   ******************************************************************/
  public function insert($table, $params=[]) {
    if ($this->driver->mysql_legacy) {
      $id = $this->query('SELECT uuid_generate_v4() AS id')[0]->id;
      $params[':id'] = $id;
      $params_keys = implode(',', array_keys($params));
      $params_cols = str_replace(':', '', $params_keys);
      $this->query("INSERT INTO $table ($params_cols) VALUES ($params_keys)", $params);
      return $id;
    }
    else {
      $params_keys = implode(',', array_keys($params));
      $params_cols = str_replace(':', '', $params_keys);
      return $this->query("INSERT INTO $table ($params_cols) VALUES ($params_keys) RETURNING id", $params)[0]->id;
    }
  }

  /******************************************************************
   * Simple SQL update query (psql/mysql)
   *  $table        : Table name
   *  $params_set   : Array of parameters for PDO
   *  $params_where : Array of parameters for PDO ('AND' only)
   ******************************************************************/
  public function update($table, $params_set=[], $params_where=[]) {
    $set = [];
    foreach(array_keys($params_set) as $key) {
      $set[] = str_replace(':', '', $key)."=$key";
    }
    $where = '';
    if (count($params_where) > 0) {
      $where = [];
      foreach(array_keys($params_where) as $key) {
        $where[] = str_replace(':', '', $key)."=$key";
      }
      $where = ' WHERE '.implode(' AND ', $where);
    }
    return $this->query("UPDATE $table SET ".implode(',', $set).$where, array_merge($params_set, $params_where));
  }

  /******************************************************************
   * Simple SQL delete query (psql/mysql)
   *  $table        : Table name
   *  $params_where : Array of parameters for PDO ('AND' only)
   ******************************************************************/
  public function delete($table, $params_where=[]) {
    $where = '';
    if (count($params_where) > 0) {
      $where = [];
      foreach(array_keys($params_where) as $key) {
        $where[] = str_replace(':', '', $key)."=$key";
      }
      $where = ' WHERE '.implode(' AND ', $where);
    }
    return $this->query("DELETE FROM $table$where", $params_where);
  }

  /*****************************************************************************
   * SQL query using session as buffer.
   * If data exists, return buffered data
   *  $table        : Table name
   *  $params       : Array of parameters for PDO
   *  $name         : Store/retrieve data: $_SESSION['db']['buffered'][$name]
   *****************************************************************************/
  public function query_buffered($name, $query, $params=[]) {
    $result = null;
    if (isset($_SESSION['db']['buffered'][$name])) {
      $result = $_SESSION['db']['buffered'][$name];
    }
    else {
      $result = $this->query($query, $params);
      if (isset($_SESSION)) {
        if (!isset($_SESSION['db'])) {
          $_SESSION['db'] = ['buffered'=>[]];
        }
        $_SESSION['db']['buffered'][$name] = $result;
      }
    }
    return $result;
  }

}
