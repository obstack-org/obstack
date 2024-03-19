<?php
/******************************************************************
 *
 * basebq()
 *  -> hextags()
 *  -> rndsum($total, $steps)
 *  -> rstr($min, $max)
 *  -> pstr($str)
 *  -> encode($data)
 *  -> decode($basestring)
 *
 ******************************************************************/

class basebq {

  private static function hextags() {
    return '>+%=/';
  }

  private static function rndsum($total=1024, $steps=8) {
    $result = [];
    for($i=$steps; $i>1; $i--) {
      $loose = random_int(0, $total/$i);
      $result[] = $loose;
      $total = $total - $loose;
    }
    $result[] = $total;
    shuffle($result);
    return $result;
  }

  public static function rstr($min, $max) {
    $str = implode("", array_merge(range(0,9), range('a','z'), range('A', 'Z')));
    return mb_substr(str_shuffle($str), 0, random_int($min, $max));
  }

  public static function pstr($str) {
    return mb_substr($str, strlen($str)/6, -(strlen($str)/8));
  }

  public static function encode($data) {
    $result = '';
    $obfsct = [];
    foreach (str_split($data) as $char) {
      $step = [];
      foreach (basebq::rndsum(ord($char), random_int(7,8)) as $tmp) {
        $step[] = $tmp;
      }
      $step[] = random_int(0,6)+132-ord($char);
      $obfsct = array_merge($obfsct,$step);
    }
    foreach($obfsct as $int) {
      if ($int <= 9) {
        $result .= chr($int+48);
      }
      elseif (($int > 9) && ($int <= 35)) {
        $result .= chr($int+87);
      }
      elseif (($int > 35) && ($int <= 61)) {
        $result .= chr($int+29);
      }
      else {
        $result .= str_split(basebq::hextags())[random_int(0,2)].mb_substr('0'.dechex($int),-2);
      }
    }
    return $result;
  }

  public static function decode($basestring) {
    $i = 0;
    $total = 0;
    $result = '';
    $basestring = str_split($basestring);
    while ($i<count($basestring)) {
      if (strpos(basebq::hextags(), $basestring[$i])!==false) {
        $int = hexdec($basestring[$i+1].$basestring[$i+2]);
        $i = $i + 3;
      }
      else {
        $int = ord($basestring[$i]);
        if (($int >=48) && ($int <=57)) {
          $int =  $int - 48;
        }
        elseif (($int >=97) && ($int <=122)) {
          $int = $int - 87;
        }
        if (($int >=65) && ($int <=90)) {
          $int = $int - 29;
        }
        $i++;
      }
      if (($total+$int) < 128) {
        $total += $int;
      }
      else {
        $result .= chr($total);
        $total = 0;
      }
    }
    return $result;
  }

  // https://stackoverflow.com/questions/74782736/cryptojs-aes-cbc-decryption-in-php-without-iv

  private static function EVP_BytesToKey($salt, $password) {
    $derived = '';
    $tmp = '';
    while(strlen($derived) < 48) {
        $tmp = md5($tmp . $password . $salt, true);
        $derived .= $tmp;
    }
    return $derived;
  }

  public static function encrypt($value, $password){
    $salt = openssl_random_pseudo_bytes(8);
    $keyIv = basebq::EVP_BytesToKey($salt, $password);
    $key = mb_substr($keyIv, 0, 32);
    $iv = mb_substr($keyIv, 32);
    $encrypted_data = openssl_encrypt($value, 'aes-256-cbc', $key, OPENSSL_RAW_DATA, $iv);
    return base64_encode('Salted__'.$salt.$encrypted_data);
  }

  public static function decrypt($data, $password) {
    $saltCiphertext = base64_decode($data);
    $salt = mb_substr($saltCiphertext, 8, 8);
    $ciphertext = mb_substr($saltCiphertext, 16, null,);
    $keyIv = basebq::EVP_BytesToKey($salt, $password);
    $key = mb_substr($keyIv, 0, 32);
    $iv = mb_substr($keyIv, 32);
    return openssl_decrypt($ciphertext, 'aes-256-cbc', $key, OPENSSL_RAW_DATA, $iv);
  }

}
