<?php
/**
 * This file is part of webman.
 *
 * Licensed under The MIT License
 * For full copyright and license information, please see the MIT-LICENSE.txt
 * Redistributions of files must retain the above copyright notice.
 *
 * @author    walkor<walkor@workerman.net>
 * @copyright walkor<walkor@workerman.net>
 * @link      http://www.workerman.net/
 * @license   http://www.opensource.org/licenses/mit-license.php MIT License
 */
$hostArr = explode(",", env('MONGODB_HOST'));
return [

    'default' => 'mongodb_main',

    'connections' => [

       /* 'mysql' => [
            'driver' => 'mysql',
            'host' => env('DB_HOST', '127.0.0.1'),
            'port' => env('DB_PORT', '3306'),
            'database' => env('DB_DATABASE', 'forge'),
            'username' => env('DB_USERNAME', 'forge'),
            'password' => env('DB_PASSWORD', ''),
            'unix_socket' => env('DB_SOCKET', ''),
            'charset' => 'utf8',
            'collation' => 'utf8_unicode_ci',
            'prefix' => '',
            'strict' => true,
            'engine' => null,
        ],

        'sqlite' => [
            'driver' => 'sqlite',
            'database' => env('DB_DATABASE', ''),
            'prefix' => '',
        ],

        'pgsql' => [
            'driver' => 'pgsql',
            'host' => env('DB_HOST', '127.0.0.1'),
            'port' => env('DB_PORT', '5432'),
            'database' => env('DB_DATABASE', 'forge'),
            'username' => env('DB_USERNAME', 'forge'),
            'password' => env('DB_PASSWORD', ''),
            'charset' => 'utf8',
            'prefix' => '',
            'schema' => 'public',
            'sslmode' => 'prefer',
        ],

        'sqlsrv' => [
            'driver' => 'sqlsrv',
            'host' => env('DB_HOST', 'localhost'),
            'port' => env('DB_PORT', '1433'),
            'database' => env('DB_DATABASE', 'forge'),
            'username' => env('DB_USERNAME', 'forge'),
            'password' => env('DB_PASSWORD', ''),
            'charset' => 'utf8',
            'prefix' => '',
        ],*/

        'mongodb_main' => [
            'driver' => 'mongodb',
            'host' => $hostArr,
            'port' =>  env('MONGODB_PORT'),
            'database' => 'GAME_MAIN',
            'username' => env('MONGODB_USERNAME'),
            'password' => env('MONGODB_PASSWORD'),
            'options' => [
                // here you can pass more settings to the Mongo Driver Manager
                // see https://www.php.net/manual/en/mongodb-driver-manager.construct.php under "Uri Options" for a list of complete parameters that you can use
//                'replicaSet' => env('MONGODB_REPLICASET'),
                'database' => env('DB_AUTHENTICATION_DATABASE', 'admin'), // required with Mongo 3+
                'readPreference' => 'primaryPreferred',
            ],
        ],
        'mongodb_config' => [
            'driver' => 'mongodb',
            'host' => $hostArr,
            'port' =>  env('MONGODB_PORT'),
            'database' => 'GAME_CONFIG',
            'username' => env('MONGODB_USERNAME'),
            'password' => env('MONGODB_PASSWORD'),
            'options' => [
                // here you can pass more settings to the Mongo Driver Manager
                // see https://www.php.net/manual/en/mongodb-driver-manager.construct.php under "Uri Options" for a list of complete parameters that you can use
//                'replicaSet' => env('MONGODB_REPLICASET'),
                'database' => env('DB_AUTHENTICATION_DATABASE', 'admin'), // required with Mongo 3+
                'readPreference' => 'primaryPreferred',
            ],
        ],
        'mongodb_oa' => [
            'driver' => 'mongodb',
            'host' => $hostArr,
            'port' =>  env('MONGODB_PORT'),
            'database' => 'oa',
            'username' => env('MONGODB_USERNAME'),
            'password' => env('MONGODB_PASSWORD'),
            'options' => [
                // here you can pass more settings to the Mongo Driver Manager
                // see https://www.php.net/manual/en/mongodb-driver-manager.construct.php under "Uri Options" for a list of complete parameters that you can use
//                'replicaSet' => env('MONGODB_REPLICASET'),
                'database' => env('DB_AUTHENTICATION_DATABASE', 'admin'), // required with Mongo 3+
//                'readPreference' => 'primaryPreferred',
            ],
        ],
        'mongodb_friend' => [
            'driver' => 'mongodb',
            'host' => $hostArr,
            'port' =>  env('MONGODB_PORT'),
            'database' => 'GAME_FRIEND',
            'username' => env('MONGODB_USERNAME'),
            'password' => env('MONGODB_PASSWORD'),
            'options' => [
                // here you can pass more settings to the Mongo Driver Manager
                // see https://www.php.net/manual/en/mongodb-driver-manager.construct.php under "Uri Options" for a list of complete parameters that you can use
//                'replicaSet' => env('MONGODB_REPLICASET'),
                'database' => env('DB_AUTHENTICATION_DATABASE', 'admin'), // required with Mongo 3+
                'readPreference' => 'primaryPreferred',
            ],
        ],
        'mongodb_club' => [
            'driver' => 'mongodb',
            'host' => $hostArr,
            'port' =>  env('MONGODB_PORT'),
            'database' => 'GAME_CLUB',
            'username' => env('MONGODB_USERNAME'),
            'password' => env('MONGODB_PASSWORD'),
            'options' => [
                // here you can pass more settings to the Mongo Driver Manager
                // see https://www.php.net/manual/en/mongodb-driver-manager.construct.php under "Uri Options" for a list of complete parameters that you can use
//                'replicaSet' => env('MONGODB_REPLICASET'),
                'database' => env('DB_AUTHENTICATION_DATABASE', 'admin'), // required with Mongo 3+
                'readPreference' => 'primaryPreferred',
            ],
        ],
    ],
];
