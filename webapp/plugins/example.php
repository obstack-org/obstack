<?php

class ExamplePlugin extends Obstack_Plugin {

  public $objecttype = 'e07413b8-4db0-4281-b5eb-ad376b61a3af';

  public function read($object) {
    $object->{'6062b625-997b-474a-b124-62c5b5e2e7ab'} = $object->{'2201a7a4-48e2-476d-b83a-c3d85f58874b'} * $object->{'71bc2110-d2aa-4d03-ae3e-33c7edd359eb'};
    return $object;
  }

  public function save($object) {
    if ($object->id == null) {
      $object->{'532b4e35-ed73-42b7-9e62-f50e2b60fe1b'} = $this->db_now();
    }
    return $object;
  }

  public function delete($object) {
  }

  private function db_now() {
    global $db;
    return $db->query("SELECT TO_CHAR(now(), 'YYYY-MM-DD HH:MI:SS') AS now", [])[0]->now;
  }

}
