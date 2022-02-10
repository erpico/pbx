<?php

// Instantiate the app
$settings = require __DIR__ . '/src/settings.php';
$app = new \Slim\App($settings);

// Set up dependencies
require __DIR__ . '/src/dependencies.php';

$dbConfig = $container['dbConfig'];

return [
  'migration_dirs' => [
    'first' => __DIR__ . '/migrations',
  ],
  'environments' => [
    'local' => [
      'adapter' => 'mysql',
      'host' => $dbConfig['db_host'],
      'port' => $dbConfig['db_port'],
      'username' => $dbConfig['db_user'],
      'password' => $dbConfig['db_password'],
      'db_name' => $dbConfig['db_schema'],
      'charset' => 'utf8',
    ],
    'production' => [
      'adapter' => 'mysql',
      'host' => $dbConfig['db_host'],
      'port' => $dbConfig['db_port'],
      'username' => $dbConfig['db_user'],
      'password' => $dbConfig['db_password'],
      'db_name' => $dbConfig['db_schema'],
      'charset' => 'utf8',
    ],
  ],
  'default_environment' => 'local',
  'log_table_name' => 'migration_log',
];
