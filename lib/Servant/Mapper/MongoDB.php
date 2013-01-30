<?php

namespace Servant\Mapper;

class MongoDB extends \Servant\Base
{

  public static function __callStatic($method, $arguments)
  {
    if (strpos($method, 'find_by_') === 0) {
      $where = \Grocery\Helpers::merge(substr($method, 8), $arguments);
      $row   = static::select(array(), $where, array('single' => TRUE));

      return $row ?: FALSE;
    } elseif (strpos($method, 'count_by_') === 0) {
      return static::count(\Grocery\Helpers::merge(substr($method, 9), $arguments));
    } elseif (strpos($method, 'find_or_create_by_') === 0) {
      $where = \Grocery\Helpers::merge(substr($method, 18), $arguments);
      $row   = static::select(array(), $where, array('single' => TRUE));

      return $row ?: static::create($where);
    }

    return parent::__callStatic($method, $arguments);
  }

  public function __toString()
  {
    return print_r($this->fields(), TRUE);
  }


  public function id()
  {
    return (string) $this->props[$this->pk()];
  }

  public function save($skip = FALSE)
  {
    if ($this->is_valid($skip)) {
      static::callback($this, 'before_save');

      $fields = static::stamp($this);

      unset($fields['_id']);

      if ($this->is_new()) {
        if (static::conn()->insert($fields, array('safe' => TRUE))) {
          $this->new_record = FALSE;
          $this->props = $fields;
        }
      } else {
        static::conn()->update(array(
          '_id' => $this->props['_id'],
        ), array(
          '$set' => $fields,
        ));
      }


      static::callback($this, 'after_save');
      $this->changed = array();

      return TRUE;
    }
  }

  public static function count(array $params = array())
  {
    return (int) static::conn()->count(static::parse( ! empty($params['where']) ? $params['where'] : $params));
  }

  public static function columns()
  {
    return array_merge(array(
      '_id' => array(
        'type' => 'primary_key',
      ),
    ), static::$columns);
  }

  public static function pk()
  {
    return '_id';
  }

  public static function delete_all(array $params = array())
  {
    return static::conn()->remove(static::parse($params));
  }

  public static function update_all(array $data, array $params = array())
  {
    $tmp = (object) $data;

    static::callback($tmp, 'before_save');

    $data = array('$set' => (array) $tmp);
    $out = static::conn()->update(static::parse($params), $data, array('multiple' => TRUE));

    static::callback($tmp, 'after_save');

    return $out;
  }



  private static function ids($set)
  {
    if (is_array($set)) {
      $tmp = array();
      foreach ($set as $k => $v) {
        $tmp []= new \MongoId($v);
      }
      return array('$in' => $tmp);
    } else {
      return new \MongoId($set);
    }
  }

  private static function select($fields, $where, $options, \Closure $lambda = NULL)
  {
    $where  = static::parse($where);
    $single = ! empty($options['single']);
    $method = $single ? 'findOne' : 'find';

    if (array_key_exists('_id', $where)) {
      if (sizeof($where['_id']) === 1) {
        $method = 'findOne';
        $single = TRUE;
      }
    }

    if ($set = static::conn()->$method($where, $fields)) {
      if (is_object($set)) {
        if ( ! empty($options['order'])) {
          foreach ($options['order'] as $key => $val) {
            $options['order'][$key] = $val == 'DESC' ? -1 : 1;
          }
          $set->sort($options['order']);
        }

        ! empty($options['limit']) && $set->limit($options['limit']);
        ! empty($options['offset']) && $set->skip($options['offset']);


        if ($lambda) {
          while ($set->hasNext()) {
            $lambda(new static($set->getNext(), 'after_find', FALSE, $options));
          }
        } elseif ($set->hasNext()) {
          return new static($set->getNext(), 'after_find', FALSE, $options);
        }
      } elseif ($lambda) {
        $lambda(new static($set, 'after_find', FALSE, $options));
      } else {
        return new static($set, 'after_find', FALSE, $options);
      }
    }
  }

  private static function parse($test)
  {
    $out = array();

    foreach ($test as $key => $val) {
      if ($key === '_id') {
        $out['_id'] = static::ids($val);
      } elseif (\Grocery\Helpers::is_keyword($key)) {
        $out['$' . strtolower($key)] = $val;
      } elseif (strpos($key, '/_or_/')) {
        $out['$or'] = array();

        foreach (explode('_or_') as $one) {
          $out['$or'] []= array($one => $val);
        }
      } elseif (preg_match('/^(.+?)(?:\s+(!=?|[<>]=?|<>|NOT|R?LIKE)\s*)$/', $key, $match)) {
        $old = isset($out[$match[1]]) ? $out[$match[1]] : array();

        if ($tmp = static::field($val)) {
          $val = $tmp;
        }

        switch ($match[2]) {
          case 'NOT'; case '<>'; case '!'; case '!=';
            $out[$match[1]] = array_merge($old, array(is_array($val) ? '$nin': '$ne' => $val));
          break;
          case '<'; case '<=';
            $out[$match[1]] = array_merge($old, array('$lt' . (substr($match[2], -1) === '=' ? 'e' : '') => $val));
          break;
          case '>'; case '>=';
            $out[$match[1]] = array_merge($old, array('$gt' . (substr($match[2], -1) === '=' ? 'e' : '') => $val));
          break;
          case 'RLIKE';
            $out[$match[1]] = array_merge($old, array('$regex' => str_replace('\\', '\\\\', $val), '$options' => 'us'));
          break;
          case 'LIKE';
            $val = preg_quote($val, '/');
            $val = strtr("^$val$", array('^%' => '', '%$' => '', '%' => '.*'));

            $out[$match[1]] = array_merge($old, array('$regex' => $val, '$options' => 'uis'));
          break;
        }
      } else {
        $out[$key] = is_array($val) ? array_merge($old, array('$in' => $val)) : $val;
      }
    }

    return $out;
  }

  private static function conn()
  {
    if ( ! defined('static::CONNECTION')) {
      throw new \Exception("The MongoDB connection was not defined");
    }


    if (isset(static::$registry[static::CONNECTION])) {
      $db = static::$registry[static::CONNECTION];
    } else {
      $dsn_string = \Servant\Config::get(static::CONNECTION);
      $database = substr($dsn_string, strrpos($dsn_string, '/') + 1);
      $mongo = $dsn_string ? new \Mongo($dsn_string) : new \Mongo;
      $db = $mongo->{$database ?: 'default'};

      static::$registry[static::CONNECTION] = $db;
    }


    if ( ! \Servant\Config::lock(static::CONNECTION)) {
      \Servant\Helpers::reindex($db->{static::table()}, static::indexes());
    }

    return $db->{static::table()};
  }


  protected static function block($get, $where, $params, $lambda)
  {
    static::select($get, $where, $params, $lambda);
  }

  protected static function finder($which, $what, $where, $options)
  {
    switch ($which) {
      case 'first';
      case 'last';
        $row = static::select($what, $where, array_merge(array(
          'offset' => $which === 'first' ? 0 : static::count($where) - 1,
          'limit' => 1,
        ), $options));

        return $row ?: FALSE;
      case 'all';
        $out = array();

        static::select($what, $where, $options, function ($row)
          use (&$out, $options) {
            $out []= $row;
          });

        return $out;
      default; // one
        return static::select($what, $where, $options) ?: FALSE;
    }
  }

  protected static function stamp($row)
  {
    $out = array();
    $old = parent::stamp($row);

    foreach ($old as $key => $val) {
      if ($tmp = static::field($val)) {
        $old[$key] = $tmp;
      }
    }

    return $old;
  }

  protected static function field($value)
  { // TODO: there is a better way?
    if (preg_match('/^\d{4}\D\d{2}\D\d{2}(?=\D?\d{2}:\d{2}(?::\d{2})|$)$/', $value)) {
      return new \MongoDate(strtotime($value));
    }
  }

}
