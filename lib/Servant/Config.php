<?php

namespace Servant;

class Config
{

  private static $bag = array();



  public static function set($key, $value = NULL, $locked = FALSE)
  {
    static::$bag[$key] = compact('value', 'locked');
  }

  public static function get($key, $default = FALSE)
  {
    return isset(static::$bag[$key]['value']) ? static::$bag[$key]['value'] : $default;
  }

  public static function lock($key)
  {
    return ! empty(static::$bag[$key]['locked']);
  }

}
