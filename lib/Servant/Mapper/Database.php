<?php

namespace Servant\Mapper;

class Database extends \Servant\Base
{

  public static function __callStatic($method, $arguments)
  {
    if (strpos($method, 'find_by_') === 0) {
      $test = \Grocery\Helpers::merge(substr($method, 8), $arguments);
      $row  = static::conn()->select(static::defaults(), $test)->fetch();

      return $row ? new static($row->to_a(), 'after_find') : FALSE;
    } elseif (strpos($method, 'find_or_create_by_') === 0) {
      $test = \Grocery\Helpers::merge(substr($method, 18), $arguments);
      $res  = static::conn()->select(static::defaults(), $test)->fetch();

      return $res ? new static($res->to_a(), 'after_find') : static::create($test);
    }

    if (method_exists(static::conn(), $method)) {
      return call_user_func_array(array(static::conn(), $method), $arguments);
    }

    return parent::__callStatic($method, $arguments);
  }

  public function save($skip = FALSE)
  {
    if ($this->is_valid($skip)) {
      static::callback($this, 'before_save');

      $fields = static::stamp($this);
      $fields = static::values($fields);

      unset($fields[static::pk()]);

      if ($this->is_new()) {
        $this->props[static::pk()] = static::conn()->insert($fields, static::pk());
        $this->new_record = FALSE;
      } else {
        static::conn()->update($fields, array(
          static::pk() => $this->props[static::pk()],
        ));
      }
      static::callback($this, 'after_save');
      $this->changed = array();

      return TRUE;
    }
  }

  public static function count(array $params = array())
  {
    return (int) static::conn()->select('COUNT(*)', ! empty($params['where']) ? $params['where'] : array())->result();
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
    return static::conn()->delete($params);
  }

  public static function update_all(array $data, array $params = array())
  {
    $tmp = (object) $data;

    static::callback($tmp, 'before_save');

    return static::conn()->update((array) $tmp, $params);

    static::callback($tmp, 'after_save');
  }

  private static function defaults($out = NULL, array $params = array())
  {
    if (! $out) {
      $out = array_keys(static::columns());
    } else {
      $out = is_array($out) ? $out : array($out);
    }

    // TODO: user cases
    if (empty($params['group'])) {
      in_array(static::pk(), $out) OR array_unshift($out, static::pk());
    }

    $top = static::table();
    $out = array_filter($out);

    foreach ($out as $k => $v) {
      $out[$k] = "$top.$v";
    }

    return $out;
  }

  private static function conn()
  {
    if ( ! defined('static::CONNECTION')) {
      throw new \Exception("The database connection was not defined");
    }

    if (isset(static::$registry[static::CONNECTION])) {
      $db = static::$registry[static::CONNECTION];
    } else {
      $dsn = \Servant\Config::get(static::CONNECTION);
      $db = \Grocery\Base::connect($dsn);

      static::$registry[static::CONNECTION] = $db;
    }

    if ( ! \Servant\Config::lock(static::CONNECTION)) {
      if ( ! isset($db[static::table()])) {
        $db[static::table()] = static::columns();
      } else {
        \Grocery\Helpers::hydrate($db[static::table()], static::columns(), static::indexes());
      }
    }

    return $db->{static::table()};
  }

  protected static function block($get, $where, $params, $lambda)
  {
    $res = static::conn()->select(static::defaults($get), $where, $params);
    while ($row = $res->fetch()) {
      $lambda(new static($row->to_a(), 'after_find', FALSE, $params));
    }
  }

  protected static function finder($which, $what, $where, $options)
  {
    switch ($which) {
      case 'first';
      case 'last';
        $options['limit'] = 1;

        if (empty($options['order'])) {
          $options['order'] = array(
            static::pk() => $which === 'first' ? 'ASC' : 'DESC',
          );
        }

        $row = static::conn()->select(static::defaults($what, $options), $where, $options)->fetch();

        return $row ? new static($row->to_a(), 'after_find', FALSE, $options) : FALSE;
      case 'all';
        $out = array();
        $res = static::conn()->select(static::defaults($what, $options), $where, $options);

        while ($row = $res->fetch()) {
          $out []= new static($row->to_a(), 'after_find', FALSE, $options);
        }

        return $out;
      default; // one
        $row = static::conn()->select(static::defaults($what, $options), $where, $options)->fetch();

        return $row ? new static($row->to_a(), 'after_find', FALSE, $options) : FALSE;
    }
  }

}
