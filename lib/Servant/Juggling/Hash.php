<?php

namespace Servant\Juggling;

class Hash extends \Servant\Binding\Base
{

  public function __construct($scalar)
  {
    $this->from_s($scalar);
  }

  public function __get($key)
  {
    return isset($this->data[$key]) ? $this->data[$key] : NULL;
  }

  public function __set($key, $value)
  {
    $this->data[$key] = $value;
  }

  public function __toString()
  {
    return $this->to_s();
  }

  public function to_v()
  {
    return $this->data;
  }

  public function to_s()
  {
    return serialize($this->data);
  }

  public function from_s($value)
  {
    $this->data = \Servant\Helpers::hashify($value);
  }

}
