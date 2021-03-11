<?php

require __DIR__ . '/../vendor/autoload.php';

session_start();

// Instantiate the app
$settings = require __DIR__ . '/../src/settings.php';
$app = new \Slim\App($settings);

// Set up dependencies
require __DIR__ . '/../src/dependencies.php';

// Register middleware
require __DIR__ . '/../src/middleware.php';

// параметры
// 1 - имя файла
$filename = $argv[1];
// 2 - метод
$method = $argv[2];

// экспорт
if ($method === "export") {
  (new \App\ExportImport($filename ?: null))->export();
}

if ($method === "import") {
  (new \App\ExportImport($filename ?: null))->import(false);
}

