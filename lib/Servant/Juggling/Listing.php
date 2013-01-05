<?php

namespace Servant\Juggling;

class Listing extends \Servant\Juggling\Base
{

  public function __toString()
  {
    return $this->get();
  }


  public function get()
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

  public function set($value)
  {
    $this->data = \Servant\Helpers::listify($value);
  }

}
