#!/usr/bin/php
<?php

require __DIR__ . '/vendor/autoload.php';

use PAMI\Client\Impl\ClientImpl;
use PAMI\Message\Action\CommandAction;

session_start();

// Instantiate the app
$settings = require __DIR__ . '/src/settings.php';
$app = new \Slim\App($settings);

// Set up dependencies
require __DIR__ . '/src/dependencies.php';

// Register middleware
require __DIR__ . '/src/middleware.php';

if (!$container['db'] instanceof \PDO) {
  printf("No database connection");
  exit(2);
}

if ($argc != 2 || $argv[1] != 'noreload') {
  $ami = $container['ami'];

  if (!$ami instanceof \PAMI\Client\Impl\ClientImpl) {
    printf("No AMI configuration");
    exit(2);
  }

  try {
    $ami->open();
  } catch (Exception $e) {
    printf("No AMI connection");
    exit(2);
  }
}

$phones = new PBXPhone();
$peers = new PBXChannel();
$queues = new PBXQueue();

$reload = [];

$current = @file_get_contents(__DIR__ ."/configs/sip.conf");
$new = $phones->getConfig()."\n".$peers->getConfig();

if (strcmp($current, $new) != 0) {
  $f = fopen(__DIR__ ."/configs/sip.conf", "wt");
  fputs($f, $new);  
  fclose($f); 
  $reload['sip'] = "sip reload";
}

$current = @file_get_contents(__DIR__ ."/configs/sip.registry.conf");
$new = $peers->getRegConfig();

if (strcmp($current, $new) != 0) {
  $f = fopen(__DIR__ ."/configs/sip.registry.conf", "wt");
  fputs($f, $new);  
  fclose($f); 
  $reload['sip'] = "sip reload";
}

$current = @file_get_contents(__DIR__ ."/configs/queues.conf");
$new = $queues->getConfig();

if (strcmp($current, $new) != 0) {
  $f = fopen(__DIR__ ."/configs/queues.conf", "wt");
  fputs($f, $new);  
  fclose($f); 
  $reload['queue'] = "queue reload all";
}

$current = @file_get_contents(__DIR__ ."/configs/pjsip.conf");
$new = $phones->getPjsipConfig()."\n".$peers->getPjsipConfig();

if (strcmp($current, $new) != 0) {
  $f = fopen(__DIR__ ."/configs/pjsip.conf", "wt");
  fputs($f, $new);  
  fclose($f);   
}

if (isset($ami)) {
  foreach ($reload as $cmd) {
    $ami->send(new CommandAction($cmd));
  }

  $ami->close();
}

?>