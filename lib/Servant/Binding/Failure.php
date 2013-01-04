<?php

namespace Servant\Binding;

class Failure extends \Grocery\Handle\Hasher
{

  private $errors = array();


  public function __construct()
  {
  }

  public function __get($key)
  {
    return isset($this->errors[$key]) ? $this->errors[$key] : NULL;
  }

  public function __set($key, $value)
  {
    $this->errors[$key] = $value;
  }



  public static function from(array $set, array $data = array())
  {
    $list = new static;

    foreach ($set as $field => $message) {
      $list->errors[$field] = sprintf($message, $data[$field]);
    }
    return $list;
  }


  public function getIterator()
  {
    return new \ArrayIterator($this->errors);
  }

  public function count()
  {
    return sizeof($this->errors);
  }

}
