<?php

namespace Servant\Juggling;

class Date
{

  private $format = 'Y-m-d H:i:s';

  private $timestamp = NULL;
  private $datetime = NULL;

  private static $available = array(
                    'date' => 'Y-m-d',
                    'time' => 'H:i:s',
                    'datetime' => 'Y-m-d H:i:s',
                    'timestamp' => 'Y-m-d H:i:s',
                  );


  public function __construct($scalar, $format = 'timestamp')
  {
    $this->format = static::$available[$format];
    $this->datetime = new \DateTime($scalar);
    $this->from_s($scalar);
  }

  public function __call($method, array $arguments)
  {
    $out = call_user_func_array(array($this->datetime, \Staple\Helpers::camelcase($method)), $arguments);
    $this->timestamp = $this->datetime->getTimestamp();

    return $out;
  }

  public function __toString()
  {
    return $this->to_s();
  }


  public function to_v()
  {
    return $this->to_s();
  }

  public function to_s()
  {
    return $this->datetime->format($this->format);
  }

  public function from_s($value)
  {
    $this->timestamp = is_numeric($value) ? $value : strtotime($value);
  }

}
