<?php

use Slim\Http\Request;
use Slim\Http\Response;
use Monolog\Logger;
use Monolog\Handler\StreamHandler;
use Symfony\Component\HttpClient\HttpClient;
use Erpico\User;

require_once __DIR__."/Bitrix24/CMBitrix.php";

$app->get('/bitrix24/app', function (Request $request, Response $response, array $args) {
  $settings = new PBXSettings();
  $appId = $settings->getSettingByHandle('bitrix.app_id')['val'];
  $secret = $settings->getSettingByHandle('bitrix.secret')['val'];
  $domain = $settings->getSettingByHandle('bitrix.domain')['val'];
  $bitrix24_token = json_decode(gzdecode(base64_decode($this->request->getCookieParam('bitrix24_token'))), true);

  $client = HttpClient::create(['http_version' => '2.0']);

  $code = $request->getParam("code");

  if (strlen($code)) {
    $result = $client->request(
        'GET',
        "https://oauth.bitrix.info/oauth/token/",
        [
            "query" => [
                "grant_type" => "authorization_code",
                "client_id"  => $appId,
                "client_secret" => $secret,
                "code" => $request->getParam("code")
            ]
        ]
        );

    if ($result->getStatusCode() != 200) {
        print "Ошибка авторизации<br><a href=\"https://$domain/oauth/authorize/?client_id=$appId\">Повторить вход</a>";
        die();
    }

    $content = $result->toArray();

    setcookie('bitrix24_token', base64_encode(gzencode(json_encode(['member_id' => $content['member_id'], 'access_token' => $content['access_token'], 'refresh_token' => $content['refresh_token']]))), 0, '/');
  }

  if (!isset($bitrix24_token['member_id'])) {
      header("Location: https://$domain/oauth/authorize/?client_id=$appId");
      die();
  }

  // create a log channel
 
  $log = new Logger('bitrix24');
  $log->pushHandler(new StreamHandler(__DIR__."/../logs/bitrix24.log", Logger::DEBUG));

  // init lib
  $obB24App = new \Bitrix24\Bitrix24(false, $log);
  $obB24App->setApplicationScope([ 'crm' ]);
  $obB24App->setApplicationId($appId);
  $obB24App->setApplicationSecret($secret);

  // set user-specific settings
  $obB24App->setDomain($domain);
  $obB24App->setMemberId($bitrix24_token['member_id']);
  $obB24App->setAccessToken($bitrix24_token['access_token']);
  $obB24App->setRefreshToken($bitrix24_token['refresh_token']);
  
  // get information about current user from bitrix24
  while (1) {
  try {
    $obB24User = new \Bitrix24\User\User($obB24App);
    $arCurrentB24User = $obB24User->current();
  } catch (\Bitrix24\Exceptions\Bitrix24TokenIsExpiredException $e) {
    $obB24App->setRedirectUri("https://".$_SERVER['HTTP_HOST']."/bitrix24/app");
    $newToken = $obB24App->getNewAccessToken();
    setcookie('bitrix24_token', base64_encode(gzencode(json_encode(['member_id' => $bitrix24_token['member_id'], 'access_token' => $newToken['access_token'], 'refresh_token' => $newToken['refresh_token']]))), 0, '/');
    $obB24App->setAccessToken($newToken['access_token']);
    $obB24App->setRefreshToken($newToken['refresh_token']);
    continue;
  }
  break;
  }
  if (!strlen($arCurrentB24User['result']['UF_PHONE_INNER'])) {
    die("Cannot login user without phone.");
  }
  // Create and auth user
  $u = new User();
  $user = $u->trustedLogin($arCurrentB24User['result']['UF_PHONE_INNER'], $request->getAttribute('ip_address'));
  if (!$user['fullname']) {
      $arCurrentB24User = $arCurrentB24User['result'];
      $userInfo = [
            "state" => 1,
            "name" => trim($arCurrentB24User['UF_PHONE_INNER']),
            "fullname" => (strlen(trim($arCurrentB24User['LAST_NAME'])) ? trim($arCurrentB24User['LAST_NAME']) . " " : "") . $arCurrentB24User['NAME'],
            "phone" => trim($arCurrentB24User['UF_PHONE_INNER']),
            "description" => "email=" . $arCurrentB24User['EMAIL'] . ";mobile=" . $arCurrentB24User['WORK_PHONE'] . ";title=" . $arCurrentB24User['WORK_POSITION']
        ];
    $res = $u->addUpdate($userInfo);
    if ($res['result'] == true) {
      $user = $u->trustedLogin($arCurrentB24User['UF_PHONE_INNER'], $request->getAttribute('ip_address'));
      if (!$user['fullname']) die(['res' => false, 'text' => 'Произошла ошибка']);
    } else {
        die(['res' => $res]);
    }
  }
  setcookie("token", '"'.$user['token'].'"', 0, '/');

  ?>
  Auth success. Please wait.
  <script>
      if (typeof window.JsonRequest !== 'function') {
          document.location.href = "/";
      } else {
          // Auth in app
          var req = {
              "action": "login",
              "token": "<?=$user['token']?>",
              "id": "<?=$user['id']?>",
              "name": "<?=$user['name']?>",
              "fullname": "<?=$user['fullname']?>"
          };
          window.JsonRequest(JSON.stringify(req), function (response) {
            window.close();
            return 1;
          });
      }
  </script>
  <?

  die();

  return $response->withJson($arCurrentB24User);
});

$app->get('/bitrix24/sync', function (Request $request, Response $response, array $args) {
  $settings = new PBXSettings();
  $appId = $settings->getSettingByHandle('bitrix.app_id')['val'];
  $secret = $settings->getSettingByHandle('bitrix.secret')['val'];
  $domain = $settings->getSettingByHandle('bitrix.domain')['val'];
  $bitrix24_token = json_decode(gzdecode(base64_decode($this->request->getCookieParam('bitrix24_token'))), true);

    // create a log channel
  $log = new Logger('bitrix24');
  $log->pushHandler(new StreamHandler(__DIR__."/../logs/bitrix24.log", Logger::DEBUG));

  // init lib
  $obB24App = new \Bitrix24\Bitrix24(false, $log);
  $obB24App->setApplicationScope([ 'crm' ]);
  $obB24App->setApplicationId($appId);
  $obB24App->setApplicationSecret($secret);

  // set user-specific settings
  $obB24App->setDomain($domain);
  $obB24App->setMemberId($bitrix24_token['member_id']);
  $obB24App->setAccessToken($bitrix24_token['access_token']);
  $obB24App->setRefreshToken($bitrix24_token['refresh_token']);

  $i = 0;
  $users = [];

  $obB24User = new \Bitrix24\User\User($obB24App);

  while (1) {
    $busers = $obB24User->get('NAME', 'ASC', '', $i);
    if (!count($busers['result'])) break;
    foreach ($busers['result'] as $u) {
        if ($u['ACTIVE'] != 'Y') continue;
        if (empty($u['UF_PHONE_INNER'])) continue;
        $users[] = [
            "id"   => $u['ID'],
            "name" => trim($u['NAME']),
            "last_name" => trim($u['LAST_NAME']),
            "phone" => $u['UF_PHONE_INNER'],
            "title" => trim($u['WORK_POSITION']),
            "email" => trim($u['EMAIL']),
            "mobile" => trim($u['WORK_PHONE']),
            //"all"  => $u
        ];
    }
    if (!isset($busers['next'])) {
        break;
    }
    $i = $busers['next'];
  }

  // Import users
  $user = new User();
  $phone = new PBXPhone();
  $ulist = $user->fetchList("", 0, 100000, 0, 1);
  $eusers = [];
  foreach ($ulist as $u) {
      if (empty($u['phone'])) continue;
      $eusers[$u['phone']] = $u;
  }
  foreach ($users as &$u) {
      if (isset($eusers[$u['phone']])) {
          $uinfo = $eusers[$u['phone']];
      } else {
          $uinfo = [
              "id" => 0,
          ];
      }
      $uinfo['state'] = 1;
      $uinfo['name'] = $u['phone'];
      $uinfo['fullname'] = (strlen($u['last_name']) ? $u['last_name']." " : "").$u['name'];
      $uinfo['phone'] = $u['phone'];
      $uinfo['description'] = "email=".$u['email'].";mobile=".$u['mobile'].";title=".$u['title'];
      $u['result'] = $user->addUpdate($uinfo, $disable_rules = 1);
      $p = $phone->fetchList(['phone' => $u['phone'], "active" => 1], 0, 3, 0, 0);
      if($p && $u['result']['id']) {
        $res = $phone->setPhoneUser(["id" => $p[0]['id'], "login" => $u['phone'], "password" => $p[0]['password'],"phone" => $u['phone'], "model" => "erpico", "user_id" => $u['result']['id']]);
      }
  }

  return $response->withJson($users);
});

$app->get('/bitrix24/call/add', function (Request $request, Response $response, array $args) {
    $intnum = $request->getParam('intnum', '');
    $extnum = $request->getParam('extnum', '');
    $type = $request->getParam('type', '');
    $crm_create = $request->getParam('crm_create', '');
    $line_number = $request->getParam('line_number', '');
    $channel = $request->getParam('channel', '');

    $helper = new CMBitrix($channel);

    $resultFromB24 = $helper->runInputCall($intnum, 
                                           $extnum,
                                           $type,
                                           $crm_create,
                                           $line_number); 

    print $resultFromB24;

    die();
});

$app->any('/bitrix24/call/record', function (Request $request, Response $response, array $args) {
    $call_id = $request->getParam('call_id', '');
    $FullFname = $request->getParam('FullFname', '');
    $CallIntNum = $request->getParam('CallIntNum', '');
    $CallDuration = $request->getParam('CallDuration', '');
    $CallDisposition = $request->getParam('CallDisposition', '');
    $channel = $request->getParam('channel', '');

    $helper = new CMBitrix($channel);

    $resultFromB24 = $helper->uploadRecordedFile($call_id,
                                                 $FullFname,
                                                 $CallIntNum,
                                                 $CallDuration,
                                                 $CallDisposition); 

    return $response->withJson($resultFromB24);
});

$app->get('/bitrix24/lead/search', function (Request $request, Response $response, array $args) {
    $phone = $request->getParam('phone', '');
    $redirect = $request->getParam('redirect', 0);
    $helper = new CMBitrix();
    $settings = new PBXSettings();
    $domain = $settings->getSettingByHandle('bitrix.domain')['val'];

    $res = $helper->getLeadLinkByPhone($phone);
    if ($res) {
        if ($redirect) {
            header("Location: https://$domain/crm/lead/details/$res/");
            die();
        } else {
            return $response->withJson(['link' => "https://$domain/crm/lead/details/$res/"]);
        }
    } else {
        return $response->withJson(['res'=> false]);
    }
});

$app->get('/bitrix24/lead/import', function (Request $request, Response $response, array $args) {
  $helper = new CMBitrix();
  $settings = new PBXSettings();
  $domain = $settings->getSettingByHandle('bitrix.domain')['val'];
  $filters = $request->getParam('filters');

  $res = $helper->getLeadsByFilters($filters);
  if ($res) {
    return $response->withJson(['res'=> true, 'total' => count($res), 'data' => $res, ]);
  } else {
    return $response->withJson(['res'=> false]);
  }
});
