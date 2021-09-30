<?php

namespace Servant;

class Paginate implements \IteratorAggregate
{

  private $data = array();
  private $options = array(
              'link_text' => '%d',
              'link_href' => '?p=%d',
              'link_root' => '/',
              'current' => 0,
              'per_page' => 20,
              'num_links' => 10,
              'total_items' => 0,
            );


  private function __controller()
  {
  }

  public function __toString()
  {
    return join(', ', $this->navlinks());
  }

  public function getIterator()
  {
    return new \ArrayIterator($this->result());
  }


  public function __call($method, $arguments)
  {
    $callback = array($this->data, $method);

    if (is_callable($callback)) {
      return call_user_func_array($callback, $arguments);
    }

    throw new \Exception("Unable to execute '$method' callback");
  }

  public static function build($config = NULL)
  {
    $obj = new static;

    return $obj->set($config);
  }

  public function bind($set)
  {
    $this->data = $set;

    return $this;
  }

  public function set($key, $value = '')
  {
    if (is_array($key)) {
      $this->options = array_merge($this->options, $key);
    } elseif ($key instanceof \Closure) {
      $config = new stdClass;
      $key($config);

      $this->options = array_merge($this->options, (array) $config);
    } elseif ( $key && ! is_numeric($key)) {
      $this->options[$key] = $value;
    }

    return $this;
  }

  public function get($key, $default = FALSE)
  {
    return ! empty($this->options[$key]) ? $this->options[$key] : $default;
  }

  public function result()
  {
    $out = $this->data;

    if (is_object($out)) {
      $out = $out->offset($this->offset())->limit($this->per_page())->all();
    } else {
      $out = array_slice($out, $this->offset(), $this->per_page());
    }

    return $out;
  }

  public function total()
  {
    return (int) $this->get('total_items');
  }

  public function pages()
  {
    return ceil($this->total() / $this->get('per_page'));
  }

  public function per_page()
  {
    return (int) $this->get('per_page');
  }

  public function num_links()
  {
    return (int) $this->get('num_links');
  }

  public function current()
  {
    return (int) $this->get('current') ?: 1;
  }

  public function offset()
  {
    $index = $this->current() ? $this->current() - 1 : 0;
    $index = floor($index * $this->per_page());

    return $index;
  }

  public function navlinks($wrap = '[%s]')
  {
    $page_num = $this->current();
    $last_page = $this->pages();

    $next = $page_num + 1;
    $show = $this->get('num_links');

    $start = $page_num - $show;
    $end = $page_num + $show;


    if ($page_num == 1)
    {
      return $this->links(1, 1, min($last_page, $end), $wrap);
    }


    if ($page_num == $last_page)
    {
      return $this->links($last_page, max(1, $start), $last_page, $wrap);
    }

    return $this->links($page_num, max(1, $start), min($end, $last_page), $wrap);
  }

  public function links($current, $from, $to, $wrap = '[%s]')
  {
    $out = array();

    for ($i = $from; $i <= $to; $i += 1) {
      $link = $this->link($i, $this->get('link_text'));

      if ($current == $i) {
        $link = sprintf($wrap, $link);
      }

      $out []= $link;
    }

    return $out;
  }

  public function link($num, $text = '', array $args = array())
  {
    $text = $text ? sprintf($this->get('link_text'), number_format($num)) : number_format($num);
    $html = '<a href="' . $this->url_for($num) . '">' . $text . '</a>';

    return $html;
  }

  public function url_for($num)
  {
    return sprintf($num <= 1 ? $this->get('link_root') : $this->get('link_href'), $num);
  }

  public function prev_url()
  {
    return $this->current() > 1 ? $this->url_for($this->current() - 1) : FALSE;
  }

  public function next_url()
  {
    return $this->current() < $this->pages() ? $this->url_for($this->current() + 1) : FALSE;
  }

}
