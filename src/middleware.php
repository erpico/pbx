<?php
// Application middleware

// e.g: $app->add(new \Slim\Csrf\Guard);

$checkProxyHeaders = false;
$trustedProxies = [];
$app->add(new RKA\Middleware\IpAddress($checkProxyHeaders, $trustedProxies));
$app->add('\App\Middleware\AuthUser');