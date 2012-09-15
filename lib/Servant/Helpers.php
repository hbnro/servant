<?php

namespace Servant;

class Helpers
{

  public static function underscore($test)
  {
    $test = preg_replace('/[A-Z](?=\w)/', '_\\0', $test);
    $test = preg_replace_callback('/(^|\W)([A-Z])/', function ($match) {
      "$match[1]_" . strtolower($match[2]);
    }, $test);

    $test = trim(strtr($test, ' ', '_'), '_');
    $test = strtolower($test);

    return $test;
  }

}
