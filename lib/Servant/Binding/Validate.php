<?php

namespace Servant\Binding;

class Validate
{

  private $model = NULL;

  private $data = array();
  private $params = array();

  private static $messages = array(
                    'acceptance_of' => 'must be accepted',
                    'confirmation_of' => "doesn't match confirmation",
                    'exclusion_of' => 'is reserved',
                    'format_of' => 'is invalid',
                    'inclusion_of' => 'is not included in the list',
                    'length_of' => "length doesn't fit",
                    'numericality_of' => 'is not a number',
                    'presence_of' => "can't be empty",
                    'uniqueness_of' => 'has already been taken',
                  );


  private function __construct()
  {
  }



  public static function setup($on, array $rules)
  {
    $check = new static;

    $check->model  = $on;
    $check->params = $rules;
    $check->data   = $on->fields();

    return $check;
  }

  public function errors()
  {
    return $this->failure;
  }

  public function run()
  {
    $rules = array();

    foreach ($this->params as $key => $val)
    {
      if ((substr($key, -3) === '_of') && method_exists($this, substr($key, 0, -3))) {
        @list($field, $set) = call_user_func_array(array($this, substr($key, 0, -3)), (array) $val);

        isset($rules[$field]) OR $rules[$field] = array();

        $debug = static::$messages[$key];

        if (is_array($val) && ($tmp = end($val))) {
          isset($tmp['message']) && $debug = $tmp['message'];
        }

        foreach ($set as $one) {
          $rules[$field][$debug] = $one;
        }
      } else {
        if (isset($rules[$key])) {
          $rules[$key] = array_merge($rules[$key], $val);
        } else  {
          $rules[$key] = (array) $val;
        }
      }
    }


    \Staple\Validation::setup($rules);

    if ( ! \Staple\Validation::execute($this->data)) {
      $set = \Servant\Binding\Failure::from(\Staple\Validation::errors(), $this->data);
      $this->model->attr('errors', $set, TRUE);
    } else {
      return TRUE;
    }
  }



  private function presence($field, array $params = array())
  {
    return array($field, array('required'));
  }

  private function acceptance($field, array $params = array())
  {
    return array($field, array(function ($value)
      use ($params) {
        return $value === ( ! empty($params['accept']) ? addslashes($params['accept']) : 'on');
      }));
  }

  private function confirmation($field, array $params = array())
  {
    return array($field, array('required', "={$field}_confirmation"));
  }

  private function exclusion($field, array $params = array())
  {
    @list(, $callback) = $this->inclusion($field, $params);
    return array($field, array(function ($value)
      use ($callback) {
        return ! $callback($value);
      }));
  }

  private function format($field, array $params = array())
  {
    return array($field, array(function ($value)
      use ($params) {
        $regex = ! empty($params['with']) ? $params['with'] : '//';

        return @preg_match($regex, $value);
      }));
  }

  private function inclusion($field, array $params = array())
  {
    return array($field, array(function ($value)
      use ($params) {
        return in_array($value, ! empty($params['in']) ? (array) $params['in'] : array());
      }));
  }

  private function length($field, array $params = array())
  {
    return array($field, array(function ($value)
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
      }));
  }

  private function numericality($field, array $params = array())
  {
    return array($field, array(function ($value)
      use ($params) {
        return ! empty($params['integer']) ? preg_match('/^[-+]?\d+$/', $value) : is_numeric($value);
      }));
  }

  private function uniqueness($field, array $params = array())
  {
    $model = ! empty($params['model']) ? $params['model'] : get_class($this->model);
    $model = \Staple\Helpers::classify(\Staple\Inflector::singularize($model));

    $klass = ! empty($params['class']) ? $params['class'] : $model;
    $field = ! empty($params['field']) ? $params['field'] : $field;

    $callback = "$model::count_by_$field";

    return array($field, array(function ($value)
      use ($callback) {
        return ! call_user_func($callback, $value);
      }));
  }

}
