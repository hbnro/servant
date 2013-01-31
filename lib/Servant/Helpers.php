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

}
