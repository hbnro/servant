<?php

namespace Servant\Binding;

class Failure extends \Servant\Binding\Base
{

  public function __construct()
  {
  }

  public function __get($key)
  {
    return isset($this->data[$key]) ? $this->data[$key] : NULL;
  }

  public function __set($key, $value)
  {
    $this->data[$key] = $value;
  }

  public static function from(array $set, array $data = array())
  {
    $list = new static;

    foreach ($set as $field => $message) {
      $list->data[$field] = sprintf($message, isset($data[$field]) ? $data[$field] : NULL);
    }

    return $list;
  }

  public function getIterator()
  {
    return new \ArrayIterator($this->data);
  }

  public function count()
  {
    return sizeof($this->data);
  }

  public function all()
  {
    return $this->data;
  }

}
