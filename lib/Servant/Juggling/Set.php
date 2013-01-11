<?php

namespace Servant\Juggling;

class Set
{

  private $value = NULL;

  private static $enable = array(1, TRUE, 'on', 'yes', 'true', 'enable');
  private static $disable = array(0, FALSE, 'off', 'no', 'false', 'disable');

  public function __construct($scalar)
  {
    $this->from_s($scalar);
  }

  public function __toString()
  {
    return $this->to_s();
  }


  public function to_v()
  {
    return (boolean) $this->value;
  }

  public function to_s()
  {
    return $this->value ? 'true' : '';
  }

  public function from_s($value)
  {
    if (is_bool($value)) {
      $this->value = $value;
    } elseif (in_array(strtolower($value), static::$enable)) {
      $this->value = TRUE;
    } elseif (in_array(strtolower($value), static::$disable)) {
      $this->value = FALSE;
    }
  }

}
