#!/usr/bin/php
<?php

require __DIR__ . '/vendor/autoload.php';

session_start();

// Instantiate the app
$settings = require __DIR__ . '/src/settings.php';
$app = new \Slim\App($settings);

// Set up dependencies
require __DIR__ . '/src/dependencies.php';

// Register middleware
require __DIR__ . '/src/middleware.php';

$phones = new PBXPhone();
$peers = new PBXChannel();
$queues = new PBXQueue();

$f = fopen(__DIR__ ."/configs/sip.conf.new", "wt");
fputs($f, $phones->getConfig());
fputs($f, $peers->getConfig());
fclose($f);

$f = fopen(__DIR__ ."/configs/sip.registry.conf.new", "wt");
fputs($f, $peers->getRegConfig());
fclose($f);

$f = fopen(__DIR__ ."/configs/queues.conf.new", "wt");
fputs($f, $queues->getConfig());
fclose($f);

$f = fopen(__DIR__ ."/configs/pjsip.conf.new", "wt");
fputs($f, $phones->getPjsipConfig());
fputs($f, $peers->getPjsipConfig());
fclose($f);

?>