<?php

namespace Servant;

class Config
{

  private static $bag = array(
                    //
                  );



  public static function set($key, $value = NULL) {
    static::$bag[$key] = $value;
  }

  public static function get($key, $default = FALSE) {
    return isset(static::$bag[$key]) ? static::$bag[$key] : $default;
  }

}
