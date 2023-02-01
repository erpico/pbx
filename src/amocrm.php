<?php

use Slim\Http\Request;
use Slim\Http\Response;
use Erpico\User;
use App\Middleware\OnlyAdmin;
use App\Middleware\SecureRouteMiddleware;
use App\Middleware\SetRoles;

use AmoCRM\OAuth2\Client\Provider\AmoCRM;

require_once __DIR__."/EAmoCRM/EAmoCRM.php";

$app->any('/amocrm/register', function (Request $request, Response $response, array $args) use($app) {
    // $id = $request->getParam("id", "fc13c3a7-8990-4af3-bc42-88b6c742964e"/*"e268e13f-dcbf-4f5e-b55a-db5ac6abcb1f"*/);     // ID интеграции erpicoPBX
    $container = $app->getContainer();
    $id = $container->get('settings')['amocrm']['client_id'];
    
    // Параметр состояния, который будет передан в модальное окно
    $instance = $request->getParam("instance", $app->getContainer()['instance_id']); // дефолтный инстанс? берется из настроек (vpn_name)

    $amo = new EAmoCRM();

    echo '<html><body margin=0 padding=0 style="margin: 0;"><div>';
    echo $amo->getOAuthScript($id, $instance);
    echo '</div></body></html>';
    echo $amo->getOAuthScriptError();
    die;
});

$app->any('/amocrm[/]', function (Request $request, Response $response, array $args) use($app) {
  $data = json_decode(file_get_contents('php://input'), true);
  
  $container = $app->getContainer();
  $db = $container['db'];

  $code = $request->getParam("code", null);
  $referer = $request->getParam("referer", null);
  $platform = $request->getParam("platform", null);
  $client_id = $request->getParam("client_id", null);
  $instance_id = $request->getParam("state", null);

  if (str_contains($instance_id, "http")) {
      header("Location: $instance_id/amocrm?code=$code&referer=$referer&platform=$platform&client_id=$client_id");
      die();
  }

  $amo = new EAmoCRM();

  $provider = $amo->getProvider();

  if ($referer !== null) {
    $provider->setBaseDomain($referer);
  }

  // $received = date("d.m.Y H:i:s")."\nSERVER:".print_r($_SERVER,1)."\n\nPOST: ".print_r($_POST,1)."\n\nGET: ".print_r($_GET,1)."\n\nDATA: ".json_encode($data)."\n\n";    
  // file_put_contents("/var/www/html/logs/amocrm.log", $received, FILE_APPEND);

  if ($data === null && $code === null) {
    $_SESSION['oauth2state'] = bin2hex(random_bytes(16));
    if (true) {
        echo '&nbsp;<div>';
        echo $amo->getOAuthScript($provider->getClientId(), $_SESSION['oauth2state']);
        echo '</div>';
        echo $amo->getOAuthScriptError();
        die;
    } else {
        $authorizationUrl = $provider->getAuthorizationUrl(['state' => $_SESSION['oauth2state']]);
        header('Location: ' . $authorizationUrl);
    }   
  }

  if ($code !== null) {
    /**
     * Ловим обратный код
     */
    try {
      /** @var \League\OAuth2\Client\Token\AccessToken $access_token */
      $accessToken = $provider->getAccessToken(new League\OAuth2\Client\Grant\AuthorizationCode(), [
          'code' => $code,
      ]);
      
      if (!$accessToken->hasExpired()) {
        $amo->saveDeafaultSettings($accessToken);
      }
    } catch (Exception $e) {
        return $response->withJson(['result' => false, 'message' => ((string)$e)], 400);
    }

    /** @var \AmoCRM\OAuth2\Client\Provider\AmoCRMResourceOwner $ownerDetails */
    $ownerDetails = $provider->getResourceOwner($accessToken);

    printf('Приветствую, %s!', $ownerDetails->getName());
    printf("<script>window.self.close();</script>");

    die();
  }

  return $response->withJson([
      "result" => "OK"
  ]);
});

// Пользователи АМО
$app->any('/amocrm/users', function (Request $request, Response $response, array $args) use($app) {  
  $container = $app->getContainer();
  $db = $container['db'];
  $settings = new PBXSettings();
  $amo = new EAmoCRM();
  $provider = $amo->getProvider();

  if (!$amo->domain || !$amo->accessToken || !$amo->refreshToken || !$amo->expire) {
    return $response->withJson(['result' => false, 'message' => 'Ошибка интеграции'], 400);
  }  

  $users = json_decode($amo->settings->getSettingByHandle('amocrm.users')['val'], 1);

  $accessToken = new \League\OAuth2\Client\Token\AccessToken([
    'access_token' => $amo->accessToken,
    'refresh_token' => $amo->refreshToken,
    'expires' => $amo->expire,
    'baseDomain' => $amo->domain,
  ]);

  $provider->setBaseDomain($accessToken->getValues()['baseDomain']);

    /**
     * Проверяем активен ли токен и делаем запрос или обновляем токен
     */
    if ($accessToken->hasExpired()) {
        /**
         * Получаем токен по рефрешу
         */
        try {
            $accessToken = $provider->getAccessToken(new League\OAuth2\Client\Grant\RefreshToken(), [
                'refresh_token' => $accessToken->getRefreshToken(),
            ]);

            $amo->saveDeafaultSettings();
        } catch (Exception $e) {
          return $response->withJson(['result' => false, 'message' => ((string)$e)], 400);
        }
    }
    
    try {     
      $amoUsers = $amo->getUsers();

      if (count($amoUsers)) {
        $arr = [];
        foreach ($amoUsers as $u) {
          $e = [
            "id" => $u['id'],
            "name" => $u['name'],
            "phone" => ""
          ];
          foreach ($users as $k => $v) {
            if ($v == $u['id']) {
              $e['phone'] = $k;
            }
          }
          $arr[] = $e;
        }
        return $response->withJson($arr);
      }

      return $response->withJson([]);
    } catch (GuzzleHttp\Exception\GuzzleException $e) {
      print "Error: ";
      var_dump((string)$e);
  }

  return $response->withJson([]);
});

// Добавление пользователя в нашу систему из АМО (не оч понятно)
$app->post('/amocrm/users/save', function (Request $request, Response $response, array $args) use($app) {  
  $data = json_decode($request->getParam("data", "[]"), 1);

  $users = [];
  
  foreach($data as $v) {
    if (strlen($v['phone'])) {
      $users[$v['phone']] = $v['id'];      
    }
  }
  
  $amo = new EAmoCRM();

  $amo->settings->setDefaultSettings(json_encode([
    ['handle' => 'amocrm.users', 'val' => json_encode($users)]
  ]));

  return $response->withJson([ "result" => "OK"]);  
});

// Поиск контакта в АМО по номеру телефона
$app->get('/amocrm/contact', function (Request $request, Response $response, array $args) use($app) {
  $phone = $request->getParam("phone");

  if (!$phone) return $response->withJson(['result' => false, 'message' => 'Введите номер телефона'], 400);

  $amo = new EAmoCRM;
  $provider = $amo->getProvider();

  if (!$amo->domain || !$amo->accessToken || !$amo->refreshToken || !$amo->expire) {
    return $response->withJson(['result' => false, 'message' => 'Ошибка интеграции'], 400);
  }

  $accessToken = new \League\OAuth2\Client\Token\AccessToken([
    'access_token' => $amo->accessToken,
    'refresh_token' => $amo->refreshToken,
    'expires' => $amo->expire,
    'baseDomain' => $amo->domain,
  ]); 

  $provider->setBaseDomain($accessToken->getValues()['baseDomain']);

    /**
     * Проверяем активен ли токен и делаем запрос или обновляем токен
     */
    if ($accessToken->hasExpired()) {
        /**
         * Получаем токен по рефрешу
         */
        try {
            $accessToken = $provider->getAccessToken(new League\OAuth2\Client\Grant\RefreshToken(), [
                'refresh_token' => $accessToken->getRefreshToken(),
            ]);

            $amo->saveDeafaultSettings();
        } catch (Exception $e) {
          return $response->withJson(['result' => false, 'message' => ((string)$e)], 400);
        }
    }

    try {
        return $response->withJson($amo->getContact($phone));
    } catch (GuzzleHttp\Exception\GuzzleException $e) {
        return $response->withJson(['result' => false, 'message' => ((string)$e)], 400);
    }
    return $response->withJson(['result' => false, 'message' => 'API ERROR'], 400);
});

// Добавление звонка в АМО из нашей СРМ
$app->any('/amocrm/add', function (Request $request, Response $response, array $args) use($app) {
  // $domain      = $request->getParam("domain", "rpamoerpicoru.amocrm.ru");
  $intnum      = $request->getParam("intnum", "128");
  $phone       = $request->getParam("phone", "84852593100");
  $direction   = $request->getParam("direction", "inbound");
  $time        = (int)$request->getParam("time", time());
  $uniq        = $request->getParam("uniq", 0);
  $disposition = (int)$request->getParam("disposition", 4);
  $duration    = (int)$request->getParam("duration", 0);  

  $amo = new EAmoCRM();
  $provider = $amo->getProvider();
  $webUrl = $amo->settings->getSettingByHandle('web.url')['val'];

  if (!$amo->domain || !$amo->accessToken || !$amo->refreshToken || !$amo->expire || !$webUrl) {
    return $response->withJson('Ошибка интеграции', 400);
  }

  $instance = $amo->settings->getSettingByHandle('amocrm.domain')['val'];
  $users = json_decode($amo->settings->getSettingByHandle('amocrm.users')['val'], 1);

  $user_id = 0;  

  if (isset($users[$intnum])) {    
    $user_id = (int)$users[$intnum];
  } else {
    return $response->withJson('User not found', 404);
  }

  if (!strlen($phone)) {
    return $response->withJson('Phone number empty', 400);
  }

  $call = [          
    'uniq'             => $uniq,
    'phone_number'     => $phone,
    'source'           => "erpicopbx",
    'created_at'       => $time,
    'duration'         => $duration,
    'call_status'      => $disposition,
    'direction'        => $direction    
  ];

  if ($duration > 0) {
  $call['link'] = $request->getParam("link", "$webUrl/recording/$uniq.mp3");
  }

  if ($user_id) {
    $call['created_by'] = $user_id;
  }

  $accessToken = new \League\OAuth2\Client\Token\AccessToken([
    'access_token' => $amo->accessToken,
    'refresh_token' => $amo->refreshToken,
    'expires' => $amo->expire,
    'baseDomain' => $amo->domain,
  ]);  

  $provider->setBaseDomain($accessToken->getValues()['baseDomain']);

    /**
     * Проверяем активен ли токен и делаем запрос или обновляем токен
     */
    if ($accessToken->hasExpired()) {
        /**
         * Получаем токен по рефрешу
         */
        try {
            $accessToken = $provider->getAccessToken(new League\OAuth2\Client\Grant\RefreshToken(), [
                'refresh_token' => $accessToken->getRefreshToken(),
            ]);

            $amo->saveDeafaultSettings();
        } catch (Exception $e) {
          return $response->withJson(['result' => false, 'message' => ((string)$e)], 400);
        }
    }

    try {
        $result = $amo->logCall($call);
        return $response->withJson($result);
    } catch (GuzzleHttp\Exception\GuzzleException $e) {
        print "Error: ";
        return $response->withJson(['result' => false, 'message' => ((string)$e)], 400);
    }
    die("OK");
});

// Проигрывание файла. В принципе можно роут не использовать так как можно просто получать по ссылке
$app->get("/amocrm/record/{instance}/{id}", function (Request $request, Response $response, array $args) {
  // Reverse proxy to instance
  $id = str_replace(".mp3", "", $args['id']);
  $id = str_replace(".", "", $id);
  $pars = explode(".", $args['instance']);
  $args['instance'] = $pars[0];
  $url = "http://{$args['instance']}.clients.tlc.local/controllers/findrecord.php?id={$id}";

  function getRequestHeaders($multipart_delimiter=NULL) {
    $headers = array();
    foreach($_SERVER as $key => $value) {
        if(preg_match("/^HTTP/", $key)) { # only keep HTTP headers
            if(preg_match("/^HTTP_HOST/", $key) == 0 && # let curl set the actual host/proxy
            preg_match("/^HTTP_ORIGIN/", $key) == 0 &&
            preg_match("/^HTTP_CONTENT_LEN/", $key) == 0 && # let curl set the actual content length
            preg_match("/^HTTPS/", $key) == 0
            ) {
                $key = str_replace(' ', '-', ucwords(strtolower(str_replace('_', ' ', substr($key, 5)))));
                if ($key)
                    array_push($headers, "$key: $value");
            }
        } elseif (preg_match("/^CONTENT_TYPE/", $key)) {

            $key = "Content-Type";

            if(preg_match("/^multipart/", strtolower($value)) && $multipart_delimiter) {
                $value = "multipart/form-data; boundary=" . $multipart_delimiter;
                array_push($headers, "$key: $value");
            }
            else if(preg_match("/^application\/json/", strtolower($value))) {
                // Handle application/json
                array_push($headers, "$key: $value");
            }
        }
    }
    return $headers;
  }

  $curl = curl_init( $url );
  curl_setopt( $curl, CURLOPT_HTTPHEADER, getRequestHeaders() );
  curl_setopt( $curl, CURLOPT_FOLLOWLOCATION, true ); # follow redirects
  curl_setopt( $curl, CURLOPT_HEADER, true ); # include the headers in the output
  curl_setopt( $curl, CURLOPT_RETURNTRANSFER, true ); # return output as string

  $contents = curl_exec( $curl ); # reverse proxy. the actual request to the backend server.
  curl_close( $curl ); # curl is done now

  list( $header_text, $contents ) = preg_split( '/([\r\n][\r\n])\\1/', $contents, 2 );

  $headers_arr = preg_split( '/[\r\n]+/', $header_text ); 

  // Propagate headers to response.
  foreach ( $headers_arr as $header ) {
    if ( !preg_match( '/^Transfer-Encoding:/i', $header ) ) {
        if ( preg_match( '/^Location:/i', $header ) ) {
            # rewrite absolute local redirects to relative ones
            $header = str_replace($backend_url, "/", $header);
        }        
        header( $header, false );
    }
  }

  print $contents; # return the proxied request result to the browser
  die();
})->setOutputBuffering(false);
