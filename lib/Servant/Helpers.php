<?php

namespace Servant;

class Helpers
{

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
