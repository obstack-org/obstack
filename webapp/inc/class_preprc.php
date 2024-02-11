<?php

class preprc {

  private $db;

  /******************************************************************
   * Initialize
   ******************************************************************/
  public function __construct($db) {
    $this->db = $db;
  }

  public function migrate($sessman_config) {

    $mlist = [
      'varchar' => [ 'ldap_host', 'ldap_userdn', 'ldap_group-auth', 'ldap_group-sa', 'radius_host', 'radius_secret', 'radius_group-auth', 'radius_group-sa' ],
      'decimal' => [ 'session_timeout', 'ldap_enabled', 'ldap_port', 'radius_enabled', 'radius_port', 'radius_attr' ]
    ];

    foreach(array_keys($mlist) as $table) {

      $xlist = [];
      foreach($this->db->query("SELECT name FROM setting_$table WHERE name LIKE 'session_%' OR name LIKE 'ldap_%' OR name LIKE 'radius_%'", []) as $dbrow) {
        $xlist[] = $dbrow->name;
      }

      $dbc = 0;
      $dbq = (object)[ 'insert'=>[], 'params'=>[] ];

      // Session
      if ($table == 'decimal' && !in_array('session_timeout', $xlist)) {
        $dbq->insert[] = "(:n$dbc, :v$dbc)";
        $dbq->params[":n$dbc"] = 'session_timeout';
        $dbq->params[":v$dbc"] = $sessman_config['timeout'];
        $dbc++;
      }

      // LDAP, Radius
      foreach(['ldap','radius'] as $type) {
        if (isset($sessman_config[$type])) {
          foreach($sessman_config[$type] as $key=>$val) {
            if (in_array($type.'_'.$key, $mlist[$table]) && !in_array($type.'_'.$key, $xlist)) {
              if ($table == 'decimal' && $key == 'enabled') {
                $val = ($val) ? 1 : 0;
              }
              $dbq->insert[] = "(:n$dbc, :v$dbc)";
              $dbq->params[":n$dbc"] = $type.'_'.$key;
              $dbq->params[":v$dbc"] = $val;
              $dbc++;
            }
          }
        }
      }

      // Commit
      if (count($dbq->insert) > 0) {
        $dbq->insert = implode(',', $dbq->insert);
        $this->db->query("INSERT INTO setting_$table (name, value) VALUES $dbq->insert", $dbq->params);
      }

    }

  }
}
