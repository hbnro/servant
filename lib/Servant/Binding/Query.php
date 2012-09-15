<?php

namespace Servant\Binding;

class Query
{

  private $defs =  array();
  private $model = NULL;

  private function __construct()
  {
  }

  public function __call($method, $arguments)
  {
    switch ($method) {
      case 'val';
        $out  = array();
        $data = $this->first()->fields();

        foreach ($arguments as $one) {
          ! empty($data[$one]) && $out []= $data[$one];
        }

        return sizeof($out) > 1 ? $out : end($out);
      break;
      case 'count';
      case 'first';
      case 'last';
      case 'all';
        return call_user_func("$this->model::$method", $this->defs);
      break;
      case 'where';
      case 'select';
      case 'order';
      case 'group';
      case 'limit';
      case 'offset';
        $this->defs[$method] = sizeof($arguments) > 1 ? $arguments : array_shift($arguments);
        return $this;
      break;
      default;
        if ($method === 'each') {
          array_unshift($arguments, $this->defs);
        } else {
          $arguments []= $this->defs;
        }

        return call_user_func_array("$this->model::$method", $arguments);
      break;
    }
  }

  public static function fetch($on, $what, array $params)
  {
    $chain = new static;
    $chain->model = $on;

    return $chain->$what($params);
  }

}
