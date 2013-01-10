<?php

namespace Servant\Juggling;

class Timestamp
{

  protected $format = 'Y-m-d H:i:s';
  protected $timestamp = NULL;
  protected $datetime = NULL;

  private static $available = array(
                    'date' => 'Y-m-d',
                    'time' => 'H:i:s',
                    'datetime' => 'Y-m-d H:i:s',
                    'timestamp' => 'Y-m-d H:i:s',
                  );


  public function __construct($scalar, $format = 'timestamp')
  {
    if (static::$available[$format]) {
      $this->format = static::$available[$format];
      $this->datetime = new \DateTime($scalar);
    }
    $this->set($scalar);
  }

  public function __call($method, array $arguments)
  {
    $out = call_user_func_array(array($this->datetime, \Staple\Helpers::camelcase($method)), $arguments);
    $this->timestamp = $this->datetime->getTimestamp();

    return $out;
  }

  public function __toString()
  {
    return $this->get();
  }


  public function get()
  {
    return $this->datetime->format($this->format);
  }

  public function set($value)
  {
    $this->timestamp = is_numeric($value) ? $value : strtotime($value);
  }

}
