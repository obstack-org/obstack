<?php
/******************************************************************
 *
 * plugins()
 *   -> hasPlugin($id, $mehtod)
 *   -> apply($id, $method, $data)
 *
 ******************************************************************/

class plugins {

  public $plugins = [];
  private $methods = [ '__construct', '__ospVldTpy', 'read', 'list', 'open', 'save', 'delete' ];

  /***********************************************************************
   * Initialize with validating all plugin classes
   ***********************************************************************/
  function __construct() {
    global $api;
    foreach(get_declared_classes() as $class) {
      if (get_parent_class($class) == 'ObStack_Plugin') {
        $tdcl = new $class();
        if ( !isset($tdcl->objecttype)
          || !is_string($tdcl->objecttype)
          || (preg_match('/^[a-f\d]{8}(-[a-f\d]{4}){4}[a-f\d]{8}$/i', $tdcl->objecttype) !== 1)
          || count(array_diff(get_class_methods($class), $this->methods)) != 0
          || (crc32($tdcl->__ospVldTpy()) != 2347662633)
        ) {
          $api->http_error(428, "Error loading Plugin: $class<br><br>Please check the <a href=\"https://www.obstack.org/docs/?doc=development-plugins\" target=_blank>Plugins documentation</a>");
        }
        $this->plugins[$tdcl->objecttype] = new $class();
      }
    }
  }

  /***********************************************************************
   * Check if objecttype had a plugin configured, optional check method
   ***********************************************************************/
  function hasPlugin($otid, $method=null) {
    if (array_key_exists($otid, $this->plugins)) {
      if ($method != null) {
        return method_exists($this->plugins[$otid], $method);
      }
      return true;
    }
    return false;
  }

  /***********************************************************************
   * Apply plugin, return unchanged data when no plugin available
   ***********************************************************************/
  function apply($method, $otid, $data) {
    if ($this->hasPlugin($otid, $method)) {
      $result = is_object($data) ? $this->plugins[$otid]->$method(clone $data) : $this->plugins[$otid]->$method($data);
      if ($method != 'delete') {
        foreach($result as $rkey=>$rval) {
          if (!property_exists($data, $rkey)) {
            unset($result->$rkey);
          }
        }
      }
      return $result;
    }
    return $data;
  }

}

/******************************************************************
 *
 * ObStack_Plugin()
 *
 * Plugin extension for recognising and processing the plugin
 * See plugins/example.php for usage, or review documentation
 *
 ******************************************************************/

class ObStack_Plugin {

  function __construct() {}
  function __ospVldTpy() { return 'Fi[a:1T%Y80s'; }

}
