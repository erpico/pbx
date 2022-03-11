<?php

use Slim\Http\Request;
use Slim\Http\Response;
use Monolog\Logger;
use Monolog\Handler\StreamHandler;
use Symfony\Component\HttpClient\HttpClient;
use Erpico\User;

require_once __DIR__."/Bitrix24/CMBitrix.php";
require_once __DIR__."/Bitrix24/EBitrix.php";

$app->get('/bitrix24/app', function (Request $request, Response $response, array $args) {
  $settings = new PBXSettings();
  $appId = $settings->getSettingByHandle('bitrix.app_id')['val'];
  $secret = $settings->getSettingByHandle('bitrix.secret')['val'];
  $domain = $settings->getSettingByHandle('bitrix.domain')['val'];

  $client = HttpClient::create(['http_version' => '2.0']);

  $code = $request->getParam("code");
  $master = $request->getParam("master");
  $state = $request->getParam("state");

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
    if ($state == "master") {
        $settings->setDefaultSettings(json_encode([['handle' => 'bitrix24.member_id', 'val' => $content['member_id']], ['handle' => 'bitrix24.access_token', 'val' => $content['access_token']], ['handle' => 'bitrix24.refresh_token', 'val' => $content['refresh_token']]]));
    }
  }

    $bitrix24_token = json_decode(gzdecode(base64_decode($this->request->getCookieParam('bitrix24_token'))), true);
    if ($master && $bitrix24_token['member_id'] && $bitrix24_token['access_token'] && $bitrix24_token['refresh_token']) {
      $settings->setDefaultSettings(json_encode([['handle' => 'bitrix24.member_id', 'val' => $bitrix24_token['member_id']], ['handle' => 'bitrix24.access_token', 'val' => $bitrix24_token['access_token']], ['handle' => 'bitrix24.refresh_token', 'val' => $bitrix24_token['refresh_token']]]));
      return $response->withJson(['res' => true]);
    }

    if (!strlen($bitrix24_token['member_id'])) {
      if ($master) return $response->withJson(['link' => "https://$domain/oauth/authorize/?client_id=$appId&state=master"]);
      header("Location: https://$domain/oauth/authorize/?client_id=$appId");
      die();
    }

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

  if ($master && isset($bitrix24_token['member_id']) && !($obB24App->isAccessTokenExpire())) return $response->withJson(['res' => true]);

  // create a log channel

  $log = new Logger('bitrix24');
  $log->pushHandler(new StreamHandler(__DIR__."/../logs/bitrix24.log", Logger::DEBUG));

  // get information about current user from bitrix24
  while (1) {
  try {
    $obB24User = new \Bitrix24\User\User($obB24App);
    $arCurrentB24User = $obB24User->current();
  } catch (\Bitrix24\Exceptions\Bitrix24TokenIsExpiredException $e) {
    $obB24App->setRedirectUri("https://".$_SERVER['HTTP_HOST']."/bitrix24/app");
    $newToken = $obB24App->getNewAccessToken();
    setcookie('bitrix24_token', base64_encode(gzencode(json_encode(['member_id' => $bitrix24_token['member_id'], 'access_token' => $newToken['access_token'], 'refresh_token' => $newToken['refresh_token']]))), 0, '/');
    if ($master) $settings->setDefaultSettings(json_encode([['handle' => 'bitrix24.member_id', 'val' => $bitrix24_token['member_id']], ['handle' => 'bitrix24.access_token', 'val' => $newToken['access_token']], ['handle' => 'bitrix24.refresh_token', 'val' => $newToken['refresh_token']]]));
    $obB24App->setAccessToken($newToken['access_token']);
    $obB24App->setRefreshToken($newToken['refresh_token']);
    continue;
  }
  break;
  }

  // Create and auth user
  $u = new User();
  $user = $u->trustedLogin($arCurrentB24User['result']['UF_PHONE_INNER'] ?: $arCurrentB24User['ID'], $request->getAttribute('ip_address'));
  if (!$user['fullname']) {
      $arCurrentB24User = $arCurrentB24User['result'];
      $userInfo = [
            "state" => 1,
            "name" => trim($arCurrentB24User['UF_PHONE_INNER']) ?: trim($arCurrentB24User['ID']),
            "fullname" => (strlen(trim($arCurrentB24User['LAST_NAME'])) ? trim($arCurrentB24User['LAST_NAME']) . " " : "") . $arCurrentB24User['NAME'],
            "phone" => trim($arCurrentB24User['UF_PHONE_INNER']) ?: trim($arCurrentB24User['ID']),
            "description" => "email=" . $arCurrentB24User['EMAIL'] . ";mobile=" . $arCurrentB24User['WORK_PHONE'] . ";title=" . $arCurrentB24User['WORK_POSITION']
        ];
    $res = $u->addUpdate($userInfo);
    if ($res['result'] == true) {
      $user = $u->trustedLogin($arCurrentB24User['UF_PHONE_INNER'] ?: $arCurrentB24User['ID'], $request->getAttribute('ip_address'));
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
  if(!isset($bitrix24_token['member_id'])) $bitrix24_token['member_id'] = $settings->getSettingByHandle('bitrix24.member_id')['val'];
  if(!isset($bitrix24_token['access_token'])) $bitrix24_token['access_token'] = $settings->getSettingByHandle('bitrix24.access_token')['val'];
  if(!isset($bitrix24_token['refresh_token'])) $bitrix24_token['refresh_token'] = $settings->getSettingByHandle('bitrix24.refresh_token')['val'];

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
  if ($bitrix24_token['member_id'] == null) return $response->withJson(['res' => false, 'message' => 'Отуствует member_id']);
  $obB24App->setMemberId($bitrix24_token['member_id']);
  $obB24App->setAccessToken($bitrix24_token['access_token']);
  $obB24App->setRefreshToken($bitrix24_token['refresh_token']);

  if ($obB24App->isAccessTokenExpire()) {
      $obB24App->setRedirectUri("https://".$_SERVER['HTTP_HOST']."/bitrix24/app");
      $newToken = $obB24App->getNewAccessToken();
      setcookie('bitrix24_token', base64_encode(gzencode(json_encode(['member_id' => $bitrix24_token['member_id'], 'access_token' => $newToken['access_token'], 'refresh_token' => $newToken['refresh_token']]))), 0, '/');
      $settings->setDefaultSettings(json_encode([['handle' => 'bitrix24.member_id', 'val' => $bitrix24_token['member_id']], ['handle' => 'bitrix24.access_token', 'val' => $newToken['access_token']], ['handle' => 'bitrix24.refresh_token', 'val' => $newToken['refresh_token']]]));
      $obB24App->setAccessToken($newToken['access_token']);
      $obB24App->setRefreshToken($newToken['refresh_token']);
  }

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

  return $response->withJson(['res' => true, 'users' => $users]);
});

$app->get('/bitrix24/call/add', function (Request $request, Response $response, array $args) {
    $intnum = $request->getParam('intnum', ''); //номер сотрудника внт битрикс 1998
    $extnum = $request->getParam('extnum', ''); // номер пользователя кому звоним
    $type = $request->getParam('type', 2); // 1 - исходящий, 2 - входящий, 3 - входящий с перенаправлением, 4 - обратный
    $crm_create = $request->getParam('crm_create', 1); // создание сущности в срм
    $line_number = $request->getParam('line_number', ''); //Номер внешней линии, через который совершался звонок
    $channel = $request->getParam('channel', '');

    $helper = new EBitrix($request, $channel);

    $result = $helper->runInputCall($intnum, $extnum, $type, $crm_create, $line_number, '', $channel);

    print $result;

    die();
});

$app->any('/bitrix24/call/record', function (Request $request, Response $response, array $args) {
    $call_id = $request->getParam('call_id', '');//id звонка из BTX
    $FullFname = $request->getParam('FullFname', '');//URL файла (желательно mp3) с записью звонка
    $CallIntNum = $request->getParam('CallIntNum', 0);//номер внутр сотрудника
    $CallDuration = $request->getParam('CallDuration', '');//продолжительность разгововра, всего ли?
    $CallDisposition = $request->getParam('CallDisposition', '');//код вызова
    $lineNumber = $request->getParam('line_number', '');
    $channel = $request->getParam('channel', '');

    $helper = new EBitrix($request, $channel);

    $result = $helper->uploadRecordedFile($call_id, $FullFname, $CallIntNum, $CallDuration, $CallDisposition, $lineNumber, $channel);

    return $response->withJson($result);
});

$app->any('/bitrix24/call/sync', function (Request $request, Response $response, array $args) {
    $callId = $request->getParam('callId', '');
    $cdr = new PBXCdr();

    $synchronizedCalls = [];
    $exceptions = [];

    if ($callId) {
        $crmCalls = $cdr->getReportsByUid($callId, 1);
    } else {
        $currentDatetime = new DateTime();
        $yesterdayDatetime = new DateTime();
        $yesterdayDatetime->modify('-1 day');

        $filter['time'] = '{"start":"' . $yesterdayDatetime->format('Y-m-d H:i:00') . '","end":"' . $currentDatetime->format('Y-m-d H:i:59') . '"}';
//        $filter['time'] = '{"start":"2021-12-23 08:00:00","end":"2021-12-23 09:00:00"}';
        $crmCalls = $cdr->getReport($filter, 0, 1000000);
    }

    if (count($crmCalls)) {
        foreach ($crmCalls as $crmCall) {
            if (isset($crmCall['uniqid'])) $crmCall['uid'] = $crmCall['uniqid'];
            $helper = new EBitrix($request, $crmCall['uid']);
            if ($callSync = $helper->getSynchronizedCalls($crmCall['uid'])) {
                if ($callSync['status'] == 1) {
                    $result = $helper->addCall($crmCall, $callSync['call_id']);
                    isset($result['exception']) ? ($exceptions[] = $result) : ($synchronizedCalls[] = $result);
                }
            } else {
                $result = $helper->addCall($crmCall);
                isset($result['exception']) ? ($exceptions[] = $result) : ($synchronizedCalls[] = $result);
            }
        }
    }

    return $response->withJson(['synchronizedCalls' => $synchronizedCalls, 'exception' => $exceptions]);
});

$app->get('/bitrix24/lead/search', function (Request $request, Response $response, array $args) {
    $phone = $request->getParam('phone', '');
    $redirect = $request->getParam('redirect', 0);
    $channel = $request->getParam('channel', '');
    $helper = new CMBitrix($channel);
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
  $channel = $request->getParam('channel', '');
  $helper = new CMBitrix($channel);
  $settings = new PBXSettings();
  if (!$settings->getDefaultSettingsByHandle('bitrix.enable')['value']) return $response->withJson(["res" => false, "message" => 'Интеграция с Битрикс24 выключена!']);
  $filters = $request->getParam('filters');
  $next = $request->getParam('next');

  $res = $helper->getLeadsByFilters($filters, $next);
  if ($res) {
    if (isset($res['res']) && $res['res'] == false) return $response->withJson($res);
    return $response->withJson(['res'=> true, 'data' => $res]);
  } else {
    return $response->withJson(['res'=> false, "message" => 'Лиды с указаными фильтрами не найдены!']);
  }
});

$app->get('/bitrix24/users', function (Request $request, Response $response, array $args) {
    $helper = new EBitrix($request);
    $settings = new PBXSettings();
    if (!$settings->getDefaultSettingsByHandle('bitrix.enable')['value']) return $response->withJson(["res" => false, "message" => 'Интеграция с Битрикс24 выключена!']);

    $res = $helper->getUsers();
    if ($res) {
        return $response->withJson(['res' => true, 'data' => $res]);
    }
});

$app->get('/bitrix24/status', function (Request $request, Response $response, array $args) {
  $helper = new EBitrix($request);
  $settings = new PBXSettings();
  if (!$settings->getDefaultSettingsByHandle('bitrix.enable')['value']) return $response->withJson(["res" => false, "message" => 'Интеграция с Битрикс24 выключена!']);

  $res = $helper->getStatuses();
  if ($res) {
    return $response->withJson(['res' => true, 'data' => $res]);
  }
});

$app->get('/bitrix24/lead/{lead_id}/state', function (Request $request, Response $response, array $args) {
    $helper = new EBitrix($request);
    $settings = new PBXSettings();
    if (!$settings->getDefaultSettingsByHandle('bitrix.enable')['value']) return $response->withJson(["res" => false, "message" => 'Интеграция с Битрикс24 выключена!']);

    $leadId = intval($args['lead_id']);

    $res = $helper->getLeadStatus($leadId);

    return $response->withJson($res);
});

$app->get('/bitrix24/lead/{lead_id}/state/{state}', function (Request $request, Response $response, array $args) {
  $helper = new EBitrix($request);
  $settings = new PBXSettings();
  if (!$settings->getDefaultSettingsByHandle('bitrix.enable')['value']) return $response->withJson(["res" => false, "message" => 'Интеграция с Битрикс24 выключена!']);

  $leadId = intval($args['lead_id']);
  $state = intval($args['state']);
  $lead_status_user = intval($request->getParam('lead_status_user', ''));

  $res = $helper->updateLeadState($leadId, $state, $lead_status_user);

  return $response->withJson(['res' => $res]);
});
