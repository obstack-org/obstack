<?php
/******************************************************************
 *
 * conf($file)
 *  -> get($option)
 *
 ******************************************************************/

class conf {

  private $options;

  /******************************************************************
   * Initialize with connection string
   ******************************************************************/
  public function __construct($file) {

    $this->options = [];
    if ((strpos(realpath(strrev(explode('/',strrev($file),2)[1])), $_SERVER['DOCUMENT_ROOT']) !== 0) && file_exists($file)) {
      $fhnd = new SplFileObject($file);
      while (!$fhnd->eof()) {
        $res = preg_match('/^\s*([a-zA-Z0-9_]\w*)\s*=\s*(.*(?=[#])|.*\w)/', $fhnd->fgets(), $out);
        if ($res) {
          $this->options[strtolower(trim($out[1]))] = trim($out[2]);
        }
      }
      $fhnd = null;
    }

  }

  public function get($option=null) {
    if ($option == null) {
      return $this->options;
    }
    elseif (isset($this->options[$option])) {
      return $this->options[$option];
    }
    else {
      return null;
    }
  }

}
