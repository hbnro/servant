<?php

namespace Servant;

class Base implements \Serializable, \ArrayAccess, \IteratorAggregate
{

  protected $props = array();
  protected $changed = array();

  protected $new_record = NULL;

  public static $columns = array();
  public static $indexes = array();
  public static $validate = array();
  public static $related_to = array();

  protected static $registry = array();



  public function serialize()
  {
    return serialize($this->props);
  }

  public function unserialize($data)
  {
    $this->props = unserialize($data);
  }

  public function offsetSet($offset, $value)
  {
    $this->$offset = $value;
  }

  public function offsetExists($offset)
  {
    return isset($this->props[$offset]);
  }

  public function offsetUnset($offset)
  {
    $this->$offset = NULL;

    unset($this->props[$offset]);

    if ($key = array_search($offset, $this->changed)) {
      unset($this->changed[$key]);
    }
  }

  public function offsetGet($offset)
  {
    return $this->$offset;
  }

  public function __isset($key)
  {
    return $this->offsetExists($key);
  }

  public function __unset($key)
  {
    return $this->offsetUnset($key);
  }

  public function getIterator()
  {
    return new \ArrayIterator($this->props);
  }


  protected function __construct(array $params = array(), $method = NULL, $new = FALSE)
  {
    $this->new_record = (bool) $new;

    foreach (array_keys(static::columns()) as $key) {
      $this->props[$key] = isset($params[$key]) ? $params[$key] : NULL;
    }

    static::callback($this, $method);
  }

  public function __get($key)
  {
    $callback = "get_$key";

    if (method_exists($this, $callback)) {
      return $this->$callback();
    }
    return $this->attr($key);
  }

  public function __set($key, $value)
  {
    $callback = "set_$key";

    if (method_exists($this, $callback)) {
      $this->$callback($value);
    } else {
      $this->attr($key, $value);
    }
  }

  public function __call($method, $arguments)
  {
    $what  = '';
    $class = get_called_class();

    if (preg_match('/^(?:first|last|count|all)_by_/', $method)) {
      return $class::apply($method, $arguments);
    } elseif (preg_match('/^(.+?)_by_(.+?)$/', $method, $match)) {
      $method = $match[1];
      $what   = "find_by_$match[2]";
    }

    return call_user_func_array(array($this->$method, $what ?: 'all'), $arguments);
  }

  public function __toString()
  {
    return $this->to_s();
  }


  public function id()
  {
    return $this->props[static::pk()];
  }

  public function attr($key, $val = NULL, $fake = FALSE)
  {
    $test = FALSE;
    $test = (isset($this->props[$key]) OR array_key_exists($key, static::columns()));

    if ( ! $fake && ! $test) {
      throw new \Exception("Undefined '$key' property");
    } elseif (func_num_args() === 1) {
      return isset($this->props[$key]) ? $this->props[$key] : NULL;
    } else {
      if ( ! $fake && ! in_array($key, $this->changed)) {
        $this->changed []= $key;
      }
      $this->props[$key] = $val;
    }
  }

  public function fields($updated = FALSE)
  {
    if ($updated) {
      $out = array();

      foreach ($this->changed as $key) {
        $out[$key] = $this->props[$key];
      }

      $out[$this->pk()] = $this->id();

      return $out;
    }
    return $this->props;
  }

  public function is_new()
  {
    return $this->new_record;
  }

  public function is_valid($skip = FALSE)
  {
    if ( ! empty(static::$validate)) {
      $test = \Servant\Binding\Validate::setup($this, static::$validate);
      return $test->run();
    }
    return TRUE;
  }

  public function has_changed($field = FALSE)
  {
    if ($field) {
      return in_array($field, $this->changed);
    }
    return ! empty($this->changed);
  }

  public function update(array $props = array()) {
    if ( ! empty($props)) {
      foreach ($props as $key => $value) {
        $this->$key = $value;
      }
    }

    if ($this->has_changed()) {
      return $this->save();
    }
    return FALSE;
  }

  public function delete()
  {
    return static::delete_all(array(
      static::pk() => $this->props[static::pk()],
    ));
  }


  public function to_json()
  {
    return json_encode($this->to_a());
  }

  public function to_a()
  {
    return $this->fields();
  }

  public function to_s()
  {
    return print_r($this->fields(), TRUE);
  }


  public static function get()
  {
    return \Servant\Binding\Query::fetch(get_called_class(), 'select', func_get_args());
  }

  public static function with($from)
  {
    return \Servant\Binding\Eager::load(get_called_class(), $from);
  }

  public static function where(array $params)
  {
    return \Servant\Binding\Query::fetch(get_called_class(), 'where', $params);
  }

  public static function find()
  {
    $which = 'one';

    $ids =
    $what =
    $where =
    $params = array();

    foreach (func_get_args() as $one) {
      if (in_array($one, array('all', 'last', 'first'))) {
        $which = $one;
      } elseif (\Grocery\Helpers::is_assoc($one)) {
        $params += $one;
      } else {
        $ids []= $one;
      }
    }

    if ( ! empty($params['where'])) {
      $where = (array) $params['where'];
      unset($params['where']);
    }

    if ( ! empty($params['select'])) {
      $what = (array) $params['select'];
      unset($params['select']);
    }

    $ids && $where[static::pk()] = sizeof($ids) > 1 ? $ids : end($ids);

    return static::finder($which, $what, $where, $params);
  }

  public static function each($params = array(), \Closure $lambda = NULL)
  {
    if ($params instanceof \Closure) {
      $lambda = $params;
      $params = array();
    } elseif ( ! empty($params['block'])) {
      $lambda = $params['block'];
      unset($params['block']);
    }

    $get   = ! empty($params['select']) ? $params['select'] : array();
    $where = ! empty($params['where']) ? (array) $params['where'] : array();

    static::block($get, $where, $params, $lambda ?: function ($row) {
      var_dump($row->fields());
    });
  }

  public static function build(array $params = array())
  {
    $row = (object) $params;

    static::callback($row, 'before_create');

    return new static((array) $row, 'after_create', TRUE);
  }

  public static function create(array $params = array(), $skip = FALSE)
  {
    $obj = static::build($params);
    $obj->save($skip);
    return $obj;
  }

  public static function exists($params = array())
  {
    return static::count($params) > 0;
  }

  public static function table()
  {
    return defined('static::TABLE') ? static::TABLE : \Staple\Helpers::underscore(get_called_class());
  }

  public static function indexes()
  {
    return static::$indexes;
  }

  public static function __callStatic($method, $arguments)
  {
    if (isset(static::$$method)) {
      return \Servant\Binding\Chain::from(get_called_class(), $arguments)->$method;
    }

    switch (TRUE) {
      case in_array($method, array('first', 'last', 'all'));
        array_unshift($arguments, $method);
        return call_user_func_array(get_called_class() . '::find', $arguments);
      case preg_match('/^(\w+)_(first|last|each|all|pick|count)$/', $method, $match);
        return static::$match[1]()->$match[2]();
      case preg_match('/^(build|create)_by_(.+)$/', $method, $match);
        return static::$match[1](\Grocery\Helpers::merge($match[2], $arguments));
      case preg_match('/^(?:find_)?(all|first|last)_by_(.+)$/', $method, $match);
        return static::find($match[1], array(
          'where' => \Grocery\Helpers::merge($match[2], $arguments),
        ));
      case preg_match('/^each_by_(.+)$/', $method, $match);
        $test = array_pop($arguments);

        if ( ! ($test instanceof \Closure)) {
          $arguments []= $test;
          $test = NULL;
        }

        return static::each(array(
          'block' => $test,
          'where' => \Grocery\Helpers::merge($match[1], $arguments),
        ));
      case $method === 'pick';
        $limit = 1;
        $params = array();


        foreach ($arguments as $one) {
          if (is_numeric($one)) {
            $limit = (int) $one;
          } elseif (\Grocery\Helpers::is_assoc($one)) {
            $params += $one;
          }
        }

        $method = $limit > 1 ?  'fetch_all' : 'fetch';
        $params['limit'] = $limit;

        return static::find($params);
    }

    throw new \Exception("Method '$method' missing");
  }

  protected static function callback($row, $method)
  {
    method_exists(get_called_class(), $method) && static::$method($row);
  }

  protected static function stamp($row)
  {
    $current = @date('Y-m-d H:i:s');

    $props   = static::columns();
    $fields  = $row->props;

    if ( ! $row->is_new()) {
      foreach ($fields as $key => $val) {
        if ( ! in_array($key, $row->changed)) {
          unset($fields[$key]);
        }
      }
    } else {
      foreach ($fields as $key => $val) {
        if ($val === NULL) {
          unset($fields[$key]);
        }
      }

      if (array_key_exists('created_at', $props)) {
        $fields['created_at'] = $current;
        $row->created_at = $current;
      }
    }

    if (array_key_exists('modified_at', $props)) {
      $fields['modified_at'] = $current;
      $row->modified_at = $current;
    }

    return $fields;
  }

}
