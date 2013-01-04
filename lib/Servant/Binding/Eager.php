<?php

namespace Servant\Binding;

class Eager
{

  private $params = array();

  private function __construct()
  {
  }


  public function __call($method, $arguments)
  {
    if ( ! in_array($method, array('all', 'find', 'first', 'last', 'each'))) {
      throw new \Exception("Unable to execute '$method' method");
    }


    $out =
    $params = array();

    foreach ($arguments as $i => $tmp) {
      if (is_array($tmp)) {
        unset($arguments[$i]);
        $params += $tmp;
      }
    }


    $include = $this->params;
    $klass   = $this->params['parent'];

    switch ($method) {
      case 'last';
      case 'first';
        $out = call_user_func_array("$klass::$method", $arguments);

        if ($tmp = call_user_func("$include[from]::first_by_$include[on]", $out->{$include['fk']})) {
          $out->attr($include['as'], $tmp, TRUE);
        }
      break;
      default;
        $result   = static::fetch($include, $params);
        $include += compact('method', 'arguments');

        $klass::each($params, function ($row)
          use (&$out, &$result, &$include) {
            extract($include);

            if (isset($result[$row->$fk])) {
              $row->attr($as, $result[$row->$fk], TRUE);
            } else {
              $row->attr($as, $include['from']::build(), TRUE);
            }

            if ($method === 'each') {
              (($fn = end($arguments)) instanceof \Closure) && $fn($row);
              $out = NULL;
            } else {
              $out []= $row;
            }
          });
    }

    return $out;
  }

  public static function load($on, $from)
  {
    $params = array();

    if (is_array($from)) {
      $params = $from;
    } else {
      if ( ! empty($on::$related_to[$from])) {
        $params = $on::$related_to[$from];
      } else {
        $params['from'] = \Staple\Helpers::classify(\Staple\Inflector::singularize($from));
      }
    }

    // TODO: make it multiple?
    $load = new static;

    $load->params = array_merge(array(
      'fk' => \Staple\Helpers::underscore("$params[from]_id"),
      'as' => \Staple\Helpers::underscore($params['from']),
      'on' => $params['from']::pk(),
      'parent' => $on,
    ), $params);

    return $load;
  }


  private static function fetch($props, $params)
  {
    $out =
    $rel = array();

    extract($props, EXTR_SKIP);

    $params['select'] = array($fk);
    $parent::each($params, function ($row)
      use(&$out, $fk) {
        $out []= $row->$fk;
      });


    $out = array_unique($out);
    $set = compact('select');

    $set['where'][$on] = $out;

    $from::each($set, function ($row)
      use(&$rel, $on) {
        $rel[$row->$on] = $row;
      });

    return $rel;
  }




}
