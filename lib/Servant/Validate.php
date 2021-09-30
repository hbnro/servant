<?php

namespace Servant;

class Validation
{

  private static $data = array();
  private static $error = array();
  private static $rules = array();

  private static $con_fix = array(
                    '=' => 'eq_',
                    '|' => '_or_',
                    '!' => 'not_',
                    '<' => 'lt_',
                    '>' => 'gt_',
                  );

  public static function setup(array $test = array())
  {
    static::$error = array();
    static::$rules = array_fill_keys(array_keys($test), array());

    foreach ($test as $field => $rules) {
      foreach ((array) $rules as $key => $one) {
        if (is_string($one)) {
          foreach (array_filter(explode(' ', $one)) as $one) {
            $name = preg_replace('/\W/', '_', strtr($one, static::$con_fix));
            $name = ! is_numeric($key) ? $key : preg_replace('/_{2,}/', '_', trim($name, '_'));

            static::$rules[$field][$name] = $one;
          }
        } else {
          if (is_string($key)) {
            static::$rules[$field][$key] = $one;
          } else {
            static::$rules[$field] []= $one;
          }
        }
      }
    }
  }

  public static function execute(array $set = array())
  {
    static::$data = $set;

    $ok = 0;

    foreach (static::$rules as $key => $set) {
      if ( ! static::wrong($key, (array) $set)) {
        $ok += 1;
      }
    }

    return sizeof(static::$rules) === $ok;
  }

  public static function errors()
  {
    return static::$error;
  }

  public static function error($name, $default = 'required')
  {
    return ! empty(static::$error[$name]) ? static::$error[$name] : $default;
  }

  public static function value($name, $default = FALSE)
  {
    return \Staple\Helpers::fetch(static::$data, $name, $default);
  }

  public static function data()
  {
    return static::$data;
  }

  private static function wrong($name, array $set = array())
  {
    $fail = FALSE;
    $test = \Staple\Helpers::fetch(static::$data, $name);

    if ($key = array_search('required', $set)) {
      unset($set[$key]);

      if ( ! trim($test)) {//FIX
        $error = ! is_numeric($key) ? $key : 'required';
        $fail  = TRUE;
      }
    }

    if ($test) {
      foreach ($set as $error => $rule) {
        if (is_callable($rule)) {
          if ( ! call_user_func($rule, $test)) {
            $fail = TRUE;
            break;
          }
        } elseif (strpos($rule, '|') !== FALSE) {
          $fail = TRUE;

          foreach (array_filter(explode('|', $rule)) as $callback) {
            if (function_exists($callback) && $callback($test)) {
              $fail = FALSE;
              break;
            }
          }

          if ($fail) {
            break;
          }
        } elseif (preg_match('/^((?:[!=]=?|[<>])=?)(.+?)$/', $rule, $match)) {
          $vars = static::vars($match[2]);
          $expr = array_shift($vars);

          $test = is_numeric($test) ? $test : "'" . addslashes($test) . "'";
          $expr = is_numeric($expr) ? $expr : "'" . addslashes($expr) . "'";

          $operator = $match[1];

          if ( ! trim($match[1], '!=')) {
            $operator .= '=';
          }

          if ( ! @eval("return $expr $operator $test ?: FALSE;")) {
            $fail = TRUE;
            break;
          }
        } elseif (($rule[0] === '%') && (substr($rule, -1) === '%')) {
          $expr = '/' . str_replace('/', '\/', substr($rule, 1, -1)) . '/us';

          if ( ! @preg_match($expr, $test)) {
            $fail = TRUE;
            break;
          }
        } elseif (preg_match('/^([^\[\]]+)\[([^\[\]]+)\]$/', $rule, $match)) {
          $negate   = substr($match[1], 0, 1) === '!';
          $callback = $negate ? substr($match[1], 1) : $match[1];

          if (function_exists($callback)) {
            if ( ! isset($match[2])) {
              $match[2] = NULL;
            }

            $args = static::vars($match[2]);
            array_unshift($args, $test);

            $value = @call_user_func_array($callback, $args);

            if (( ! $value && ! $negate) OR ($value && $negate)) {
              $fail = TRUE;
              break;
            }
          }
        } elseif ( ! in_array($test, static::vars($rule))) {
          $fail = TRUE;
          break;
        }
      }
    }

    if ($fail && ! empty($error)) {
      static::$error[$name] = (string) $error;
    }

    return $fail;
  }

  private static function vars($test)
  {
    $test = array_filter(explode(',', $test));

    foreach ($test as $key => $val) {
      if (preg_match('/^([\'"]).*\\1$/', $val)) {
        $test[$key] = substr(trim($val), 1, -1);
      } elseif (is_numeric($val)) {
        $test[$key] = $val;
      } else {
        $test[$key] = \Staple\Helpers::fetch(static::$data, $val);
      }
    }

    return $test;
  }

}
