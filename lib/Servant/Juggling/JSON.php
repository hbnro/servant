<?php

namespace Servant\Juggling;

class JSON extends \Servant\Binding\Base
{

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
    return $this->get();
  }


  public function get()
  {
    return json_encode($this->data);
  }

  public function set($value)
  {
    $this->data = \Servant\Helpers::jsonify($value);
  }

}
