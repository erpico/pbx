<?php
return [
    'settings' => [
        'middlewareFifo' => true,
        'determineRouteBeforeAppMiddleware' => true, // get access to route within middleware
        'displayErrorDetails' => true, // set to false in production
        'addContentLengthHeader' => false, // Allow the web server to send the content-length header
        // Renderer settings
        'renderer' => [
            'template_path' => __DIR__ . '/../templates/',
        ],
        'server_host' => isset($_SERVER['SERVER_ADDR']) ? $_SERVER['SERVER_ADDR'] : '',
        // Monolog settings
        'logger' => [
            'name' => 'slim-app',
            'path' => isset($_ENV['docker']) ? 'php://stdout' : __DIR__ . '/../logs/app.log',
            'level' => \Monolog\Logger::DEBUG,
        ],
        'uploadPath' => __DIR__ . '/../public/photos',
        'amocrm' => [
            'client_id' => 'e268e13f-dcbf-4f5e-b55a-db5ac6abcb1f', // 'fc13c3a7-8990-4af3-bc42-88b6c742964e', //'eaf907ca-8717-46ca-8006-9f59d5d92c57',
            'secret'    => 'SZ176ACq339HQTADdcyDI2eVuPYqyoQRSmxRGBgh1FEiblyuKMGaw8OJoFWHtWCs', // 'Kfdf3eqI4SRmTgko3t2iRKMm3PvwJHk2w1aRc9JKe11XHQv5AUZlyng1X90OEO1r',
            'redirect_uri' => 'https://cloud.erpicopbx.ru/amocrm'
        ]
    ],
];
