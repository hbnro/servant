<?php

namespace Servant\Juggling;

class Hasher extends \Servant\Juggling\Base
{

  protected $data = array();


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
    return serialize($this->get());
  }



  public function get()
  {
    return $this->data;
  }

  public function set($value)
  {
    $this->data = \Servant\Helpers::hashify($value);
  }

}
