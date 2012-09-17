<?php

namespace Servant;

class Base implements \Serializable, \ArrayAccess, \IteratorAggregate
{

  protected $props = array();
  protected $changed = array();

  protected $new_record = NULL;

  public static $columns = array();



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


  protected function __construct(array $params = array(), $method = NULL, $new = FALSE, array $rel = array())
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

  public function attr($key, $val = NULL)
  {
    if ( ! array_key_exists($key, $this->columns())) {
      die("undefined prop $key!!!");
    }

    if (func_num_args() === 1) {
      return isset($this->props[$key]) ? $this->props[$key] : NULL;
    } else {
      if ( ! in_array($key, $this->changed)) {
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

  public static function where(array $params)
  {
    return \Servant\Binding\Query::fetch(get_called_class(), 'where', $params);
  }

  public static function find()
  {
    $args    = func_get_args();

    $wich    = array_shift($args);
    $params  = array_pop($args);

    $where   =
    $options = array();

    if (\Grocery\Helpers::is_assoc($params)) {
      $options = (array) $params;
    } else {
      $args []= $params;
    }

    if ( ! empty($options['where'])) {
      $where = (array) $options['where'];
      unset($options['where']);
    }

    $what = array();

    if ( ! empty($options['select'])) {
      $what = (array) $options['select'];
      unset($options['select']);
    }

    return static::finder($wich, $what, $where, $options);
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
    return defined('static::TABLE') ? static::TABLE : \Servant\Helpers::underscore(get_called_class());
  }

  public static function __callStatic($method, $arguments)
  {
    if (isset(static::$$method)) {
      return \Servant\Binding\Chain::from(get_called_class(), $arguments)->$method;
    }


    if (in_array($method, array('first', 'last', 'all'))) {
      array_unshift($arguments, $method);
      return call_user_func_array(get_called_class() . '::find', $arguments);
    } elseif (preg_match('/^(build|create)_by_(.+)$/', $method, $match)) {
      return static::$match[1](\Grocery\Helpers::merge($match[2], $arguments));
    } elseif (preg_match('/^(?:find_)?(all|first|last)_by_(.+)$/', $method, $match)) {
      return static::find($match[1], array(
        'where' => \Grocery\Helpers::merge($match[2], $arguments),
      ));
    } elseif (preg_match('/^each_by_(.+)$/', $method, $match)) {
      $test = array_pop($arguments);

      if ( ! ($test instanceof \Closure)) {
        $arguments []= $test;
        $test = NULL;
      }

      return static::each(array(
        'block' => $test,
        'where' => \Grocery\Helpers::merge($match[1], $arguments),
      ));
    }

    die("method $method missing!!!");
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
