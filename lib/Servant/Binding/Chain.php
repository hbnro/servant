<?php

namespace Servant\Binding;

class Chain
{

  private $model = NULL;
  private $scopes =  array();

  private static $retrieve = array(
                    'all',
                    'pick',
                    'each',
                    'count',
                    'first',
                    'last',
                  );

  private function __construct()
  {
  }

  public function __get($key)
  {
    $model = $this->model;

    if (isset($model::$$key)) {
      if ( ! in_array($key, $this->scopes)) {
        $this->scopes []= $model::$$key;
      }

      return $this;
    }

    throw new \Exception("Undefined '$key' property (chained");
  }

  public function __call($method, $arguments)
  {
    switch ($method) {
      case 'get';
        $this->scopes []= array('select' => $arguments);

        return $this;
      case 'where';
      case 'select';
      case 'order';
      case 'group';
      case 'limit';
      case 'offset';
        $this->scopes []= array($method => end($arguments));

        return $this;
      default;
        $model = $this->model;

        if (in_array($method, static::$retrieve)) {
          array_unshift($arguments, $this->params());

          return call_user_func_array("$model::$method", $arguments);
        } elseif (isset($model::$$method)) {
          return $this->$method->all($this->params());
        }

        return call_user_func_array("$model::$method", $arguments);
    }
  }

  public static function from($model, array $xargs = array())
  {
    $obj = new static;

    $obj->model = $model;
    $obj->scopes += $xargs;

    return $obj;
  }

  private function params()
  {
    $out = array();

    foreach ($this->scopes as $old) {
      $new = array();

      if ( ! empty($old['where'])) {
        $test = $old['where'];
        unset($old['where']);

        foreach ($test as $key => $val) {
          if (is_scalar($val) OR ($val === NULL)) {
            $out['where'][$key] = $val;
          } else {
            $tmp = isset($out['where'][$key]) ? $out['where'][$key] : array();
            $out['where'][$key] = array_merge($val, $tmp);
          }
        }
      }

      $out += $old;
    }

    return $out;
  }

}
