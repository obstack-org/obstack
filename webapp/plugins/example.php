<?php

class ExamplePlugin extends Obstack_Plugin {

  public $objecttype = 'e07413b8-4db0-4281-b5eb-ad376b61a3af';

  public function list($data) {
    foreach ($data as $id=>$object) {
      $data[$id]->{'532b4e35-ed73-42b7-9e62-f50e2b60fe1b'} = $this->retvar();
    }
    return $data;
  }

  public function open($data) {
    return $data;
  }

  public function save($data) {
    return $data;
  }

  public function delete($data) {
    return $data;
  }

  private function retvar() {
    return 'UpdatedValue1';
  }

}


class ExamplePlugin2 extends Obstack_Plugin {

  public $objecttype = '1e89dc54-cb5e-42f6-82cd-31d4e102b355';

  public function list($data) {
    foreach ($data as $id=>$object) {
      $data[$id]->{'1f8064a4-3437-41b5-99ab-71a967709871'} = 'FromPlugin2';
    }
    return $data;
  }

  public function open($data) {
    return $data;
  }

  public function save($data) {
    return $data;
  }

  public function delete($data) {
    return $data;
  }

}