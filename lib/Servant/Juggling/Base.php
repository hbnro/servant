<?php

namespace Servant\Juggling;

class Base extends \Grocery\Handle\Hasher
{

  protected $data = array();


  public function __construct($scalar)
  {
    $this->set($scalar);
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


  public function serialize()
  {
    return serialize($this->data);
  }

  public function unserialize($data)
  {
    $this->data = unserialize($data);
  }

  public function getIterator()
  {
    return new \ArrayIterator($this->data);
  }

  public function count()
  {
    return sizeof($this->data);
  }

}
