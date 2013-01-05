<?php

namespace Servant\Juggling;

class JSON extends \Servant\Juggling\Base
{

  public function __get($key)
  {
    return isset($this->data[$key]) ? $this->data[$key] : NULL;
  }

  public function __set($key, $value)
  {
    $this->data[$key] = $value;
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
