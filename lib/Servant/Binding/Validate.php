<?php

namespace Servant\Binding;

class Validate
{

  private $model = NULL;

  private $data = array();
  private $params = array();

  private static $messages = array(
                    'acceptance' => 'must be accepted',
                    'confirmation' => "doesn't match confirmation",
                    'exclusion' => 'is reserved',
                    'format' => 'is invalid',
                    'inclusion' => 'is not included in the list',
                    'length' => "length doesn't fit",
                    'numericality' => 'is not a number',
                    'presence' => "can't be empty",
                    'uniqueness' => 'has already been taken',
                  );

  private function __construct()
  {
  }

  public static function setup($on, array $rules)
  {
    $check = new static;

    $check->model  = $on;
    $check->params = $rules;
    $check->data   = $on->fields(TRUE);

    return $check;
  }

  public function errors()
  {
    return $this->failure;
  }

  public function run()
  {
    $rules = array();

    foreach ($this->params as $key => $val) {
      $key = is_string($key) ? $key : (string) $val;
      $val = is_array($val) ? $val : array();

      if (strpos($key, '_of_')) {
        @list($fn, $field) = explode('_of_', $key, 2);

        $val += compact('field');
        $set  = call_user_func(array($this, $fn), $val);

        isset($rules[$field]) OR $rules[$field] = array();

        $debug = static::$messages[$fn];
        isset($val['message']) && $debug = $val['message'];

        foreach ($set as $one) {
          $rules[$field][$debug] = $one;
        }
      } else {
        if (isset($rules[$key])) {
          $rules[$key] = array_merge($rules[$key], $val);
        } else {
          $rules[$key] = (array) $val;
        }
      }
    }

    \Staple\Validation::setup($rules);

    if ( ! \Staple\Validation::execute($this->data)) {
      $set = \Servant\Binding\Failure::from(\Staple\Validation::errors(), $this->data);
      $this->model->set_errors($set);
    } else {
      return TRUE;
    }
  }

  private function presence(array $params)
  {
    return array('required');
  }

  private function acceptance(array $params)
  {
    return array(function ($value)
      use ($params) {
        return $value === ( ! empty($params['accept']) ? $params['accept'] : 'on');
      });
  }

  private function confirmation(array $params)
  {
    return array('required', "=$params[field]_confirmation");
  }

  private function exclusion(array $params)
  {
    @list(, $callback) = $this->inclusion($params['field'], $params);

    return array(function ($value)
      use ($callback) {
        return ! $callback($value);
      });
  }

  private function format(array $params)
  {
    return array(function ($value)
      use ($params) {
        $regex = ! empty($params['with']) ? $params['with'] : '//';

        return @preg_match($regex, $value);
      });
  }

  private function inclusion(array $params)
  {
    return array(function ($value)
      use ($params) {
        return in_array($value, ! empty($params['in']) ? (array) $params['in'] : array());
      });
  }

  private function length(array $params)
  {
    return array(function ($value)
      use ($params) {
        $value = is_array($value) ? sizeof($value) : strlen($value);
        $equal = ! empty($params['is']) ? $params['is'] : FALSE;

        if ($equal !== FALSE) {
          return $value === $equal;
        } else {
          $set = ! empty($params['in']) ? (array) $params['in'] : array();

          if ( ! empty($set)) {
            @list($min, $max) = $set;
          } else {
            $min = ! empty($params['min']) ? $params['min'] : -PHP_INT_MAX;
            $max = ! empty($params['max']) ? $params['max'] : PHP_INT_MAX;
          }

          return ($value >= $min) && ($value <= $max);
        }
      });
  }

  private function numericality(array $params)
  {
    return array(function ($value)
      use ($params) {
        return ! empty($params['integer']) ? preg_match('/^[-+]?\d+$/', $value) : is_numeric($value);
      });
  }

  private function uniqueness(array $params)
  {
    $model = ! empty($params['model']) ? $params['model'] : get_class($this->model);
    $model = \Staple\Helpers::classify(\Doctrine\Common\Inflector\Inflector::singularize($model));

    $field = ! empty($params['field']) ? $params['field'] : $params['field'];
    $klass = ! empty($params['class']) ? $params['class'] : $model;

    $callback = "$model::count_by_$params[field]";

    return array(function ($value)
      use ($callback) {
        return ! call_user_func($callback, $value);
      });
  }

}
