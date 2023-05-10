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

  /******************************************************************
   * Initialize with connection string
   ******************************************************************/
  function __construct() {
    $args = func_get_args();
    $this->debug = false;
    $this->dbconn = null;
    try {
      if (count($args) >= 3) {
        $this->dbconn = new PDO($args[0], $this->dbuser, $this->dbpass);
      }
      else {
        $this->dbconn = new PDO($args[0]);
      }
      if ($this->debug) {
        $this->dbconn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
      }
    }
    catch (PDOException $e) {
      print 'Error!: ' . $e->getMessage() . '<br/>';
      die();
    }
  }

  /******************************************************************
   * Return SQL driver (e.g. mysql, pgsql)
   ******************************************************************/
  function driver() {
    return $this->dbconn->getAttribute(PDO::ATTR_DRIVER_NAME);
  }

  /******************************************************************
   * Perform SQL query
   *  $query    : SQL query
   *  $params   : Array of parameters for PDO
   ******************************************************************/
  function query($query, $params) {
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

}