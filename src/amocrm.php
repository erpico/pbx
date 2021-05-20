<?php

use Slim\Http\Request;
use Slim\Http\Response;
use Erpico\User;
use Erpico\PBXRules;
use App\Middleware\OnlyAdmin;
use App\Middleware\SecureRouteMiddleware;
use App\Middleware\SetRoles;

use AmoCRM\OAuth2\Client\Provider\AmoCRM;

$app->any('/amocrm/register', function (Request $request, Response $response, array $args) use($app) {
    $id = $request->getParam("id", "e268e13f-dcbf-4f5e-b55a-db5ac6abcb1f");    
    $instance = $request->getParam("instance", $app->getContainer()['instance_id']);

    echo '<html><body margin=0 padding=0 style="margin: 0;"><div>
        <script
            class="amocrm_oauth"
            charset="utf-8"
            data-client-id="' . $id . '"
            data-title="Установить интеграцию"
            data-compact="false"
            data-class-name="className"
            data-color="default"
            data-state="' . $instance . '"
            data-error-callback="handleOauthError"
            data-mode="post_message"
            src="https://www.amocrm.ru/auth/button.min.js"
        ></script>
        </div></body></html>';
    echo '<script>
    handleOauthError = function(event) {
        alert(\'ID клиента - \' + event.client_id + \' Ошибка - \' + event.error);
    }
    </script>';
    die;
  });