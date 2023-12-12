<?php

describe('Servant', function () {
  $datasources = array_filter(array(
    'sqlite::memory:',
    'sqlite::memory:#pdo',
    getenv('CI') ? 'mysql://mysql:mysql@localhost:33306/ci_db_test' : '',
    getenv('CI') ? 'mysql://mysql:mysql@localhost:33306/ci_db_test#pdo' : '',
    (getenv('CI') && !defined('HHVM_VERSION')) ? 'pgsql://postgres:postgres@localhost/ci_db_test' : '',
    (getenv('CI') && !defined('HHVM_VERSION')) ? 'pgsql://postgres:postgres@localhost/ci_db_test#pdo' : '',
  ));

  $suitcase = function ($conn) {
    $db = null;
    $version = null;
    #$db2 = \Servant\Base::connect($conn);
    #$version = json_encode($db->version());

    describe("Using $conn / $version", function () use ($db) {
      let('db', $db);
      #let('db', $db->reset());

      describe('Base', function () {
        xit('TODO', function ($db) {});
      });
    });
  };

  array_map($suitcase, $datasources);
});
