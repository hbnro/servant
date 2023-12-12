<?php

class User extends \Servant\Mapper\Database {
    const CONNECTION = 'default';
    const COLUMNS = [
        'password' => ['type' => 'string', 'length' => 255, 'default' => '', 'not_null' => false],
        'email' => ['type' => 'string', 'length' => 255, 'default' => '', 'not_null' => false],
        'id' => ['type' => 'primary_key', 'length' => 0, 'default' => '', 'not_null' => true],
    ];
}

describe('Servant', function () {
    $datasources = array_filter([
        'sqlite::memory:',
        'sqlite::memory:#pdo',
        getenv('CI') ? 'mysql://mysql:mysql@localhost:33306/ci_db_test' : '',
        getenv('CI') ? 'mysql://mysql:mysql@localhost:33306/ci_db_test#pdo' : '',
        (getenv('CI') && !defined('HHVM_VERSION')) ? 'pgsql://postgres:postgres@localhost/ci_db_test' : '',
        (getenv('CI') && !defined('HHVM_VERSION')) ? 'pgsql://postgres:postgres@localhost/ci_db_test#pdo' : '',
    ]);

    $suitcase = function ($conn) {
        \Servant\Config::set('default', $conn);

        let('conn', $db = User::db());

        $version = json_encode($db->version());

        describe("Using $conn / $version", function () {
            it('should pass a smoke-test', function ($conn) {
                $conn->reset();

                User::create(['email' => 'foo@candy.bar']);

                expect(User::find()->email)->toBe('foo@candy.bar');
                expect(User::count())->toBe(1);
            });
        });
    };

    test(fn () => array_map($suitcase, $datasources));
});
