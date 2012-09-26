<?php

namespace Servant\Mapper;

class Database extends \Servant\Base
{

  public static function __callStatic($method, $arguments)
  {
    if (strpos($method, 'find_by_') === 0) {
      $test = \Grocery\Helpers::merge(substr($method, 8), $arguments);
      $row  = static::conn()->select(static::table(), static::defaults(), $test)->fetch();

      return $row ? new static($row->to_a(), 'after_find') : FALSE;
    } elseif (strpos($method, 'count_by_') === 0) {
      return static::count(\Grocery\Helpers::merge(substr($method, 9), $arguments));
    } elseif (strpos($method, 'find_or_create_by_') === 0) {
      $test = \Grocery\Helpers::merge(substr($method, 18), $arguments);
      $res  = static::conn()->select(static::table(), static::defaults(), $test)->fetch();

      return $res ? new static($res->to_a(), 'after_find') : static::create($test);
    }


    if (method_exists(static::conn(), $method)) {
      return call_user_func_array(array(static::conn(), $method), $arguments);
    }

    return parent::__callStatic($method, $arguments);
  }


  public function save()
  {
    static::callback($this, 'before_save');

    $fields = static::stamp($this);

    unset($fields[static::pk()]);

    if ($this->is_new()) {
      $this->props[static::pk()] = static::conn()->insert(static::table(), $fields, static::pk());
      $this->new_record = FALSE;
    } else {
      static::conn()->update(static::table(), $fields, array(
        static::pk() => $this->props[static::pk()],
      ));
    }

    static::callback($this, 'after_save');
    $this->changed = array();

    return TRUE;
  }

  public static function count(array $params = array())
  {
    return (int) static::conn()->select(static::table(), 'COUNT(*)', ! empty($params['where']) ? $params['where'] : $params)->result();
  }

  public static function columns()
  {
    return static::$columns;
  }

  public static function pk()
  {
    return defined('static::PK') ? static::PK : 'id';
  }

  public static function delete_all(array $params = array())
  {
    return static::conn()->delete(static::table(), $params);
  }

  public static function update_all(array $data, array $params = array())
  {
    $tmp = (object) $data;

    static::callback($tmp, 'before_save');

    return static::conn()->update(static::table(), (array) $tmp, $params);

    static::callback($tmp, 'after_save');
  }



  private static function defaults($out = NULL)
  {
    if ( ! $out) {
      $out = array_keys(static::columns());
    } else {
      $out = is_array($out) ? $out : array($out);
    }

    in_array(static::pk(), $out) OR array_unshift($out, static::pk());

    $out = array_filter($out);

    return $out;
  }

  private static function conn()
  {
    if ( ! defined('static::CONNECTION')) {
      throw new \Exception("The database connection was not defined.");
    }


    if ( ! isset(static::$registry[static::CONNECTION])) {
      $lock = \Servant\Config::lock(static::CONNECTION);
      $dsn = \Servant\Config::get(static::CONNECTION);
      $db = \Grocery\Base::connect($dsn);

      if ( ! $lock) {
        if ( ! isset($db[static::table()])) {
          $db[static::table()] = static::columns();
        } else {
          \Grocery\Helpers::hydrate($db[static::table()], static::columns(), static::indexes());
        }
      }

      static::$registry[static::CONNECTION] = $db;
    }
    return static::$registry[static::CONNECTION];
  }


  protected static function block($get, $where, $params, $lambda)
  {
    $res = static::conn()->select(static::table(), static::defaults($get), $where, $params);
    while ($row = $res->fetch()) {
      $lambda(new static($row->to_a(), 'after_find', FALSE, $params));
    }
  }

  protected static function finder($wich, $what, $where, $options)
  {
    switch ($wich) {
      case 'first';
      case 'last';
        $options['limit'] = 1;

        if (empty($options['order'])) {
          $options['order'] = array(
            static::pk() => $wich === 'first' ? 'ASC' : 'DESC',
          );
        }

        $row = static::conn()->select(static::table(), static::defaults($what), $where, $options)->fetch();

        return $row ? new static($row->to_a(), 'after_find', FALSE, $options) : FALSE;
      break;
      case 'all';
        $out = array();
        $res = static::conn()->select(static::table(), static::defaults($what), $where, $options);

        while ($row = $res->fetch()) {
          $out []= new static($row->to_a(), 'after_find', FALSE, $options);
        }
        return $out;
      break;
      default;
        $row = static::conn()->select(static::table(), static::defaults($what), array(
          static::pk() => $wich,
        ), $options)->fetch();

        return $row ? new static($row->to_a(), 'after_find', FALSE, $options) : FALSE;
      break;
    }
  }

}
