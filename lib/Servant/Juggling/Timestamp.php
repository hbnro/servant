<?php

namespace Servant\Juggling;

class Timestamp
{

  protected $format = 'Y-m-d H:i:s';
  protected $timestamp = NULL;

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
    }
    $this->set($scalar);
  }

  public function __toString()
  {
    return $this->get();
  }


  public function time()
  {
    return $this->timestamp;
  }

  public function get()
  {
    return date($this->format, $this->timestamp);
  }

  public function set($value)
  {
    $this->timestamp = is_numeric($value) ? $value : strtotime($value);
  }

}
