<?php

namespace Servant\Mapper;

class MongoDB extends \Servant\Base
{

  public static function __callStatic($method, $arguments)
  {
    if (strpos($method, 'find_by_') === 0) {
      $where = \Grocery\Helpers::merge(substr($method, 8), $arguments);
      $row   = static::select(array(), $where, array('single' => TRUE));

      return $row ? new static($row, 'after_find') : FALSE;
    } elseif (strpos($method, 'count_by_') === 0) {
      return static::count(\Grocery\Helpers::merge(substr($method, 9), $arguments));
    } elseif (strpos($method, 'find_or_create_by_') === 0) {
      $where = \Grocery\Helpers::merge(substr($method, 18), $arguments);
      $res   = static::select(array(), $where, array('single' => TRUE));

      return $res ? new static($res, 'after_find') : static::create($where);
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

  public function save()
  {
    static::callback($this, 'before_save');

    $fields = static::stamp($this);

    unset($fields['_id']);

    if ($this->is_new()) {
      if (static::conn()->insert($fields)) {
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

  public function fields($updated = FALSE)
  {
    $out = $this->props;

    if ($updated) {
      foreach ($out as $key => $val) {
        if ( ! in_array($key, $this->changed)) {
          unset($out[$key]);
        }
      }
    }

    $out['_id'] = (string) $out['_id'];

    return $out;
  }


  public static function count(array $params = array())
  {
    return (int) static::conn()->count( ! empty($params['where']) ? $params['where'] : $params);
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
    if (array_key_exists('_id', $params)) {
      $params['_id'] = static::ids($params['_id']);
    }

    return static::conn()->remove($params);
  }

  public static function update_all(array $data, array $params = array())
  {
    $tmp = (object) $data;

    static::callback($tmp, 'before_save');

    $data = array('$set' => (array) $tmp);

    if (array_key_exists('_id', $params)) {
      $params['_id'] = static::ids($params['_id']);
    }

    $out = static::conn()->update($params, $data, array('multiple' => TRUE));

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

  private static function select($fields, $where, $options)
  {
    $where  = static::parse($where);
    $method = ! empty($options['single']) ? 'findOne' : 'find';

    if (array_key_exists('_id', $where)) {
      $where['_id'] = static::ids($where['_id']);
      $method = 'findOne';
    }

    $row = static::conn()->$method($where, $fields);

    ! empty($options['limit']) && $row->limit($options['limit']);
    ! empty($options['offset']) && $row->skip($options['offset']);


    if ( ! empty($options['order'])) {
      foreach ($options['order'] as $key => $val) {
        $options['order'][$key] = $val === 'DESC' ? -1 : 1;
      }
      $row->sort($options['order']);
    }

    return is_object($row) ? iterator_to_array($row) : $row;
  }

  private static function parse($test)
  {
    foreach ($test as $key => $val) {
      unset($test[$key]);

      if (\Grocery\Helpers::is_keyword($key)) {
        $test['$' . strtolower($key)] = $val;
      } elseif (strpos($key, '/_or_/')) {
        $test['$or'] = array();

        foreach (explode('_or_') as $one) {
          $test['$or'] []= array($one => $val);
        }
      } elseif (preg_match('/^(.+?)(?:\s+(!=?|[<>]=?|<>|NOT|R?LIKE)\s*)$/', $key, $match)) {
        switch ($match[2]) {
          case 'NOT'; case '<>'; case '!'; case '!=';
            $test[$match[1]] = array(is_array($val) ? '$nin': '$ne' => $val);
          break;
          case '<'; case '<=';
            $test[$match[1]] = array('$lt' . (substr($match[2], -1) === '=' ? 'e' : '') => $val);
          break;
          case '>'; case '>=';
            $test[$match[1]] = array('$gt' . (substr($match[2], -1) === '=' ? 'e' : '') => $val);
          break;
          case 'RLIKE';
            $test[$match[1]] = array('$regex' => str_replace('\\', '\\\\', $val), '$options' => 'us');
          break;
          case 'LIKE';
            $val = preg_quote($val, '/');
            $val = strtr("^$val$", array('^%' => '', '%$' => '', '%' => '.*'));

            $test[$match[1]] = array('$regex' => $val, '$options' => 'uis');
          break;
          default;
            $test[$match[1]] = is_array($val) ? array('$in' => $val) : $val;
          break;
        }
      }
    }

    return $test;
  }

  private static function conn()
  { // TODO: memoize connection
    $dsn_string = defined('static::CONNECTION') ? \Servant\Config::get(static::CONNECTION) : '';
    $database   = substr($dsn_string, strrpos($dsn_string, '/') + 1);

    $mongo    = $dsn_string ? new \Mongo($dsn_string) : new \Mongo;
    $database = $database ?: 'default';

    return $mongo->$database->{static::table()};
  }


  protected static function block($get, $where, $params, $lambda)
  {
    $res = static::select($get, $where, $params);

    while ($row = array_shift($res)) {
      $lambda(new static($row, 'after_find', FALSE, $params));
    }
  }

  protected static function finder($wich, $what, $where, $options)
  {
    switch ($wich) {
      case 'first';
      case 'last';
        $row = static::select($what, $where, array(
          'offset' => $wich === 'first' ? 0 : static::count($where) - 1,
          'limit' => 1,
        ));

        return $row ? new static(array_shift($row), 'after_find', FALSE, $options) : FALSE;
      break;
      case 'all';
        $out = array();
        $res = static::select($what, $where, $options);

        while ($row = array_shift($res)) {
          $out []= new static($row, 'after_find', FALSE, $options);
        }
        return $out;
      break;
      default;
        $row = static::select($what, array(
          '_id' => $wich,
        ), $options);

        return $row ? new static($row, 'after_find', FALSE, $options) : FALSE;
      break;
    }
  }

}
