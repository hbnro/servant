<?php

namespace Servant\Juggling;

class Enum extends \Servant\Binding\Base
{

  public function __construct($scalar)
  {
    $this->from_s($scalar);
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
    $out = array();

    foreach ($this->data as $item) {
      if (preg_match('/["\'\s,]/', trim($item))) {
        $item = '"' . addslashes(trim($item)) . '"';
      }
      $out []= $item;
    }

    return join(', ', $out);
  }

  public function from_s($value)
  {
    $this->data = \Servant\Helpers::listify($value);
  }

}
