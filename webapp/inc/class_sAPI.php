<?php
 /******************************************************************
 *
 * api($version = 1)
 *  -> route($route)
 *  -> method($method)
 *  -> param($param)
 *  -> payload()
 *  -> http_error($error)
 *
 ******************************************************************/

class sAPI {

  private $payload = null;
  public $uri;
  private $params = [];
  private $version;

  /******************************************************************
   * Initialize, error 400 on URI syntax error
   ******************************************************************/
  public function __construct($version = 1) {
    // Set version
    $this->version = $version;

    // Syntax check
    $uri = mb_substr(parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH), strpos($_SERVER['REQUEST_URI'],"/v$this->version"));
    if (preg_match('/^\/[0-9a-z_\-\/]*[0-9a-z_\-]$/i', $uri) != 1) {
      $this->http_error(400);
    }
    // Prepare data
    $this->uri = array_values(array_filter(explode('/', $uri)));
    $this->payload = json_decode(file_get_contents('php://input'), true);
  }

  /******************************************************************
   * Route matching (/path/to/{id})
   ******************************************************************/
  public function route($route) {
    // Match route depth
    $steps = explode('/', "v$this->version$route");
    if (count($steps) != count($this->uri)) {
      return false;
    }
    // Process per step
    foreach ($steps as $index => $step) {
      // Parameter
      if (preg_match('/^{.[0-9a-z]*}$/i', $step) > 0) {
        $this->params[mb_substr($step, 1, -1)] = $this->uri[$index];
      }
      // Static
      elseif ($step != $this->uri[$index]) {
        $this->params = [];
        return false;
      }
    }
    return true;
  }

  /******************************************************************
   * HTTP method
   ******************************************************************/
  public function method($method) {
    if (strtolower($method) == strtolower($_SERVER['REQUEST_METHOD'])) {
      return true;
    }
    return false;
  }

  /******************************************************************
   * Parameter value from route (param('id'))
   ******************************************************************/
  public function param($param) {
    if (isset($this->params[$param])) {
      return $this->params[$param];
    }
    return false;
  }

  /******************************************************************
   * Provide HTTP payload
   ******************************************************************/
  public function payload() {
    return $this->payload;
  }

  /******************************************************************
   * HTTP error code
   ******************************************************************/
  public function http_error($error, $msg=null) {
    $errorstring = [
      400=>'Bad request',
      401=>'Unauthorized',
      403=>'Forbidden',
      404=>'Not Found',
      428=>'Precondition Required',
      500=>'Internal server error',
      502=>'Bad Gateway',
      503=>'Service Unavailable'
    ];
    http_response_code($error);
    header($_SERVER['SERVER_PROTOCOL']." $error ".$errorstring[$error]);
    if ($msg != null) {
      print json_encode([ 'error'=>$msg ]);
    }
    die();
  }

}
