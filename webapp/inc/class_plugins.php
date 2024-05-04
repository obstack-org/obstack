<?php
/******************************************************************
 *
 * plugins()
 *   -> apply($id, $method, $data)
 *
 ******************************************************************/

class plugins {

  public $plugins = [];
  private $methods = [ '__construct', '__ospVldTpy', 'list', 'open', 'save', 'delete' ];

  /******************************************************************
   * Initialize with validating all plugin classes
   ******************************************************************/
  function __construct() {
    global $api;
    foreach(get_declared_classes() as $class) {
      if (get_parent_class($class) == 'ObStack_Plugin') {
        $tdcl = new $class();
        if ( !isset($tdcl->objecttype)
          || !is_string($tdcl->objecttype)
          || (preg_match('/^[a-f\d]{8}(-[a-f\d]{4}){4}[a-f\d]{8}$/i', $tdcl->objecttype) !== 1)
          || array_diff($this->methods, get_class_methods($class))
          || array_diff(get_class_methods($class), $this->methods)
          || (crc32($tdcl->__ospVldTpy()) != 2347662633)
        ) {
          $api->http_error(428, "Error loading Plugin: $class<br><br>Please check the <a href=\"https://www.obstack.org/docs/?doc=manual-plugins\" target=_blank>Plugins documentation</a>");
        }
        $this->plugins[$tdcl->objecttype] = new $class();
      }
    }
  }

  /******************************************************************
   * Apply plugin, return unchanged data when no plugin available
   ******************************************************************/
  function apply($otid, $method, $data) {
    if (array_key_exists($otid, $this->plugins) && method_exists($this->plugins[$otid], $method) ) {
      return $this->plugins[$otid]->$method($data);
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
