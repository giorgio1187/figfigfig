<?php

use Illuminate\Support\Str;

return [

    /*
    |--------------------------------------------------------------------------
    | Default Database Connection Name
    |--------------------------------------------------------------------------
    |
    | Here you may specify which of the database connections below you wish
    | to use as your default connection for database operations. This is
    | the connection that will be used for each database operation.
    |
    */

    'default' => env('DB_CONNECTION', 'pgsql'),

    /*
    |--------------------------------------------------------------------------
    | Database Connections
    |--------------------------------------------------------------------------
    |
    | Below are each of the database connections configured for your application.
    | Of course, examples of configuring each database platform that is
    | supported by Laravel is shown below to make development simple.
    |
    | All database work in Laravel is done through the PHP PDO facilities
    | so make sure you have the driver for your particular database of
    | choice installed on your machine before you begin development.
    |
    */

    'connections' => [

        'sqlite' => [
            'driver' => 'sqlite',
            'url' => env('DB_URL'),
            'database' => env('DB_DATABASE', database_path('database.sqlite')),
            'prefix' => '',
            'foreign_key_constraints' => env('DB_FOREIGN_KEYS', true),
        ],

        'mysql' => [
            'driver' => 'mysql',
            'url' => env('DB_URL'),
            'host' => env('DB_HOST', '127.0.0.1'),
            'port' => env('DB_PORT', '3306'),
            'database' => env('DB_DATABASE', 'forge'),
            'username' => env('DB_USERNAME', 'forge'),
            'password' => env('DB_PASSWORD', ''),
            'unix_socket' => env('DB_SOCKET', ''),
            'charset' => 'utf8mb4',
            'collation' => 'utf8mb4_unicode_ci',
            'prefix' => '',
            'prefix_indexes' => true,
            'strict' => true,
            'engine' => null,
            'options' => extension_loaded('pdo_mysql') && env('MYSQL_ATTR_SSL_CA')
                ? [1009 => env('MYSQL_ATTR_SSL_CA')]  // 1009 = PDO::MYSQL_ATTR_SSL_CA (renamed in PHP 8.5 but constant value unchanged)
                : [],
        ],

        'pgsql' => [
            'driver' => 'pgsql',
            'url' => env('DB_URL'),
            'host' => preg_replace('/^([\'"])(.*)\1$/', '$2', trim(strval(env('DB_HOST', '127.0.0.1')))),
            'port' => preg_replace('/^([\'"])(.*)\1$/', '$2', trim(strval(env('DB_PORT', '5432')))),
            'database' => preg_replace('/^([\'"])(.*)\1$/', '$2', trim(strval(env('DB_DATABASE', 'forge')))),
            'username' => preg_replace('/^([\'"])(.*)\1$/', '$2', trim(strval(env('DB_USERNAME', 'forge')))),
            'password' => preg_replace('/^([\'"])(.*)\1$/', '$2', trim(strval(env('DB_PASSWORD', '')))),
            'charset' => 'utf8',
            'prefix' => '',
            'prefix_indexes' => true,
            'search_path' => 'public',
            'sslmode' => preg_replace('/^([\'"])(.*)\1$/', '$2', trim(strval(env('DB_SSLMODE', 'require')))), // Requerido para conexiones seguras con Supabase

            /*
            |--------------------------------------------------------------------------
            | NOTA ARQUITECTÓNICA PARA SUPABASE & PGBOUNCER:
            |--------------------------------------------------------------------------
            | Supabase utiliza PgBouncer para administrar el pooling de conexiones en
            | modo 'Transaction Mode' (típicamente puerto 6543). En este modo, no se
            | permiten 'prepared statements' porque múltiples peticiones comparten la 
            | misma conexión física.
            |
            | Si utiliza el puerto directo de Postgres (5432) o el modo Session, puede
            | desactivar la emulación. Pero si usa el pooler en puerto 6543, DEBE 
            | establecer PDO::ATTR_EMULATE_PREPARES en true.
            |
            */
            'options' => [
                PDO::ATTR_PERSISTENT => false,
                PDO::ATTR_EMULATE_PREPARES => env('DB_EMULATE_PREPARES', true), // Por defecto true para evitar fallos con PgBouncer en Supabase
            ],
        ],

        'sqlsrv' => [
            'driver' => 'sqlsrv',
            'url' => env('DB_URL'),
            'host' => env('DB_HOST', 'localhost'),
            'port' => env('DB_PORT', '1433'),
            'database' => env('DB_DATABASE', 'forge'),
            'username' => env('DB_USERNAME', 'forge'),
            'password' => env('DB_PASSWORD', ''),
            'charset' => 'utf8',
            'prefix' => '',
            'prefix_indexes' => true,
            // 'encrypt' => env('DB_ENCRYPT', 'yes'),
            // 'trust_server_certificate' => env('DB_TRUST_SERVER_CERTIFICATE', 'false'),
        ],

    ],

    /*
    |--------------------------------------------------------------------------
    | Migration Repository Table
    |--------------------------------------------------------------------------
    |
    | This table keeps track of all the migrations that have already run for
    | your application. Using this information, we can determine which of
    | the migrations on disk haven't actually been run on the database.
    |
    */

    'migrations' => [
        'table' => 'migrations',
        'update_date_on_publish' => true,
    ],

    /*
    |--------------------------------------------------------------------------
    | Redis Databases
    |--------------------------------------------------------------------------
    |
    | Redis is an open source, fast, and advanced key-value store that also
    | provides a richer body of commands than a typical key-value system
    | such as Memcached. You may define your connection settings here.
    |
    */

    'redis' => [

        'client' => env('REDIS_CLIENT', 'phpredis'),

        'options' => [
            'cluster' => env('REDIS_CLUSTER', 'redis'),
            'prefix' => env('REDIS_PREFIX', Str::slug(env('APP_NAME', 'laravel'), '_') . '_database_'),
        ],

        'default' => [
            'url' => env('REDIS_URL'),
            'host' => env('REDIS_HOST', '127.0.0.1'),
            'username' => env('REDIS_USERNAME'),
            'password' => env('REDIS_PASSWORD'),
            'port' => env('REDIS_PORT', '6379'),
            'database' => env('REDIS_DB', '0'),
        ],

        'cache' => [
            'url' => env('REDIS_URL'),
            'host' => env('REDIS_HOST', '127.0.0.1'),
            'username' => env('REDIS_USERNAME'),
            'password' => env('REDIS_PASSWORD'),
            'port' => env('REDIS_PORT', '6379'),
            'database' => env('REDIS_DB', '1'),
        ],

    ],

];
