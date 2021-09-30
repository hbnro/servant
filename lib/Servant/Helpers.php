<?php

namespace Servant;

class Helpers
{

  public static function hashify($value)
  {
    if (is_scalar($value)) {
      if ( ! ($value = @unserialize($value))) {
        $value = array();
      }
    }

    return $value;
  }

  public static function listify($value)
  {
    if (is_scalar($value)) {
      $value = trim($value, '{[]}');
      $value = array_map('trim', str_getcsv($value));
    } elseif ($value === NULL) {
      $value = array();
    }

    return $value;
  }

  public static function jsonify($value)
  {
    if (is_scalar($value)) {
      $value = trim($value);

      if ((substr($value, 0, 1) === '[') && (substr($value, -1) === ']')) {
        $value = json_decode($value, TRUE);
      } elseif ((substr($value, 0, 1) === '{') && (substr($value, -1) === '}')) {
        $value = json_decode($value, TRUE);
      } else {
        $value = array(json_decode($value));
      }
    } elseif ($value === NULL) {
      $value = array();
    }

    return $value;
  }

  public static function reindex($table, array $indexes)
  {
    $tmp =
    $out = array();

    foreach ($table->getIndexInfo() as $one) {
      $tmp[key($one['key'])] = ! empty($one['unique']);
    }
    unset($tmp['_id']);

    foreach ($indexes as $key => $val) {
      $on = is_numeric($key) ? FALSE : (bool) $val;
      $key = is_numeric($key) ? $val : $key;

      if (isset($tmp[$key])) {
        if ($on !== $tmp[$key]) {
          $table->deleteIndex($key);
          $table->ensureIndex($key, array('unique' => $on));
        }
      } else {
        $table->ensureIndex($key, array('unique' => $on));
      }
      $out []= $key;
    }

    foreach (array_diff(array_keys($tmp), $out) as $old) {
      $table->deleteIndex($old);
    }
  }

  public static function parameterize($value)
  {
    return strtolower(static::camelcase($value, FALSE, '-'));
  }

  public static function classify($value)
  {
    return static::camelcase($value, TRUE, '\\');
  }

  public static function underscore($value)
  {
    $value = preg_replace('/\W/', '_', preg_replace('/[A-Z](?=\w)/', '_\\0', $value));
    $value = preg_replace_callback('/(^|\W)([A-Z])/', function ($match) {
        "$match[1]_" . strtolower($match[2]);
      }, $value);

    $value = trim(strtr($value, ' ', '_'), '_');
    $value = strtolower($value);

    return $value;
  }

  public static function camelcase($value, $ucfirst = FALSE, $glue = '')
  {
    $value = preg_replace('/[^a-z0-9]|\s+/i', ' ', $value);
    $value = preg_replace_callback('/\s([a-z\d])/i', function ($match)
      use ($glue) {
        return $glue . ucfirst($match[1]);
      }, $value);

    $value = $ucfirst ? ucfirst($value) : $value;
    $value = str_replace(' ', '', trim($value));

    return $value;
  }

  public static function titlecase($value)
  {
    return strtr(static::classify($value), '\\', ' ');
  }

  public static function fetch($from, $that = NULL, $or = FALSE)
  {
    if (is_scalar($from)) {
      return $or;
    } elseif (preg_match_all('/\[([^\[\]]*)\]/U', $that, $matches) OR ($matches[1] = explode('.', $that))) {
      // TODO: there is a previous bug when the first argument has only 1 level?
      $key = ($offset = strpos($that, '[')) > 0 ? substr($that, 0, $offset) : '';

      if ( ! empty($key)) {
        array_unshift($matches[1], $key);
      }

      $key   = array_shift($matches[1]);
      $get   = join('.', $matches[1]);
      $depth = sizeof($matches[1]);

      if (is_object($from) && isset($from->$key)) {
        $tmp = $from->$key;
      } elseif (is_array($from) && isset($from[$key])) {
        $tmp = $from[$key];
      } else {
        $tmp = $or;
      }

      $value = ! $depth ? $tmp : static::fetch($tmp, $get, $or);

      return $value;
    }
  }

}
