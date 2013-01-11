<?php

namespace Servant\Binding;

class Base implements \Countable, \Serializable, \ArrayAccess, \IteratorAggregate
{

  protected $data = array();


  public function serialize()
  {
    return serialize($this->data);
  }

  public function unserialize($data)
  {
    $this->data = unserialize($data);
  }

  public function offsetSet($offset, $value)
  {
    $this->$offset = $value;
  }

  public function offsetExists($offset)
  {
    return isset($this->$offset);
  }

  public function offsetUnset($offset)
  {
    unset($this->$offset);
  }

  public function offsetGet($offset)
  {
    return $this->$offset;
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
