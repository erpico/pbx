<?php

use Slim\Http\Request;
use Slim\Http\Response;
use Erpico\User;
use Erpico\PBXRules;
use App\Middleware\OnlyAdmin;
use App\Middleware\SecureRouteMiddleware;
use App\Middleware\SetRoles;
use App\helpers\ErpicoFileUploader;
use PHPMailer\PHPMailer\PHPMailer;
use GuzzleHttp\Client;
use Supervisor\Supervisor;
// Routes

$app->post('/auth/login', function (Request $request, Response $response, array $args) {
  $login = $request->getParam('login');
  $password = $request->getParam('password');

  if (!strlen($login)) return $response->withJson(["error" => 1, "message" => "No login provided"]);

  $user = new Erpico\User($this->db);
  $result = $user->login($login, $password, $request->getAttribute('ip_address'));

  return $response->withJson($result);
});

$app->map(['GET', 'OPTIONS'], '/cdr/list', function (Request $request, Response $response, array $args) use ($app) {
    if ($request->getMethod() === 'OPTIONS') {
      return $response->withJson([]);
    }
    $direction = $request->getParam('direction',  ''); //all(none) incoming(in) outgoing(out)
    $answered = $request->getParam('answered', -1); //all(-1) answered(1) not answered(0)
    $missed = $request->getParam('missed', 0); //1 0

    $filter = $request->getParam('filter', 0);
    $start = $request->getParam('start', 0);
    $count = $request->getParam('count', 20);
    $lcd = $request->getParam('lcd', 0);
    $lli = $request->getParam('lli', 0);

    $cdr = new PBXCdr($direction, $answered, $missed);

    return $response->withJson([
    "data" => $cdr->getReport($filter, $start, $count, 0, 0, $lcd),
    "total_count" => $cdr->getReport($filter, $start, $count, 1),
    "pos" => $lli,
    "server_footer" => $cdr->getReport($filter, $start, $count, 0, 1)
  ]);
})->add('\App\Middleware\OnlyAuthUser');

$app->get('/cdr/list/{id}', function (Request $request, Response $response, array $args) use ($app) {
  // $id = $request->getParam('id', 0);
  $id = $args["id"];
  // die(var_dump($id));
  $key = $request->getParam('key', 0);
  $status = $request->getParam('status', 0);
  $call = $request->getParam('call', 0);
  $missed = $request->getParam('missed', 0);

  $cdr = new PBXCdr($key, $status, $call, $missed);
  return $response->withJson($cdr->getReportsByUid($id));
})->add('\App\Middleware\OnlyAuthUser');

$app->get('/cdr/list/sync/{id}', function (Request $request, Response $response, array $args) use ($app) {
  $id = $args["id"];

  $cdr = new PBXCdr();
  return $response->withJson($cdr->getSyncByUid($id));
})->add('\App\Middleware\OnlyAuthUser');
/*$app->get('/controllers/findrecord.php', function (Request $request, Response $response, array $args) use($app) {
  $id = $request->getParam('id', 0);
  return $response->withRedirect("/recording/$id"); 
});*/

$findRecord = function (Request $request, Response $response, array $args) use ($app) {
  $uid = $args['id'] ?? $request->getParam('id', 0);
  $cdr = new PBXCdr();
  $result = $cdr->findRecord($uid);

  if ($result['result'] === false) {
    return $response->withStatus(404)
      ->withHeader('Content-Type', 'text/html')
      ->write('Record not found in ' . $result['errorType']);
  }

  return $response
    ->withBody($result['stream'])
    ->withHeader('Content-Type', 'audio/mpeg')
    ->withHeader('Accept-Ranges', 'bytes')
    ->withHeader('Content-Length', filesize($result['filename']))
    ->withHeader('Content-Transfer-Encoding', 'binary')
    ->withHeader('Content-Disposition', 'attachment; filename="' . basename($result['filename']) . '"');
};

$app->get('/recording/{id}', $findRecord)->setOutputBuffering(false); //->add('\App\Middleware\OnlyAuthUser');
$app->get('/controllers/findrecord.php', $findRecord)->setOutputBuffering(false); //->add('\App\Middleware\OnlyAuthUser');


$app->post('/action/call', function (Request $request, Response $response, array $args) use ($app) {
  $number = $request->getParam('number');

  if (strlen($number) < 1) {
    return $response->withJson(["error" => 1, "message" => "Empty number"]);
  }

  $user = $app->getContainer()['auth'];
  $ext = $user->getExt();

  $result = json_decode(file_get_contents("http://localhost:5039/pbx?originate=$ext&action=$number"), 1);

  if ($result['result'] == 1) $result['error'] = 0;
  else $result['error'] = 2;

  return $response->withJson($result);
})->add('\App\Middleware\OnlyAuthUser');

$app->get('/users/list', function (Request $request, Response $response, array $args) use ($app) {
  $filter = $request->getParam('filter', "");
  $start = $request->getParam('start', 0);
  $count = $request->getParam('count', 20);
  $short = $request->getParam('short', 0);

  $user = new User();

  if ($short) {
      return $response->withJson($user->fetchList($filter, $start, 1000, 0, 1));
  }

  return $response->withJson([
    "data" => $user->fetchList($filter, $start, $count, 0),
    "pos" => (int)$start,
    "total_count" => $user->fetchList($filter, $start, $count, 1)
  ]);
})->add('\App\Middleware\OnlyAuthUser');

$app->post('/users/{id}/save[/{disable_rules}]', function (Request $request, Response $response, array $args) use ($app) {
  $params = $request->getParams();
  $id = intval($args['id']);
  $user = new User();
  $result = $args['disable_rules'] ? $response->withJson($user->addUpdate($params, true)) : $response->withJson($user->addUpdate($params));
  return $result;
});

$app->post('/users/{id}/remove', function (Request $request, Response $response, array $args) use ($app) {
  $id = intval($args['id']);
  $user = new User();

  return $response->withJson($user->remove($id));
});

$app->get('/users/user_groups', function (Request $request, Response $response, array $args) use ($app) {
  $user = new User();
  return $response->withJson($user->fetchGroups());
})->add('\App\Middleware\OnlyAuthUser');

$app->post('/settings/{key}/{id}/save', function (Request $request, Response $response, array $args) use ($app) {
  $id = intval($args['id']);
  $settings = new PBXSettings();
  $params = $request->getParam("settings", "");
  if (trim($args['key']) == 'user') {
    return $response->withJson(["result" => $settings->setUserSettings($id, $params)]);
  } else if (trim($args['key']) == 'group') {
    return $response->withJson(["result" => $settings->setGroupSettings($id, $params)]);
  }
  return $response->withJson(["result" => FALSE]);
});

$app->get('/settings/{key}/{id}', function (Request $request, Response $response, array $args) use ($app) {
  $id = intval($args['id']);
  $settings = new PBXSettings();
  if (trim($args['key']) == 'user') {
    return $response->withJson(["data" => $settings->getUserSettings($id)]);
  } else if (trim($args['key']) == 'group') {
    return $response->withJson(["data" => $settings->getGroupSettings($id)]);
  }
  return $response->withJson(["data" => []]);
});

$app->get('/settings/user/{user_id}/default/{handle}', function (Request $request, Response $response, array $args) use ($app) {
  $settings = new PBXSettings();
  if (!(int)$args['user_id']) {
    return $response
      ->withJson(['result' => false, 'message' => 'Пользователь не обнаружен'])
      ->withStatus(400);
  }

  return $response->withJson($settings->deleteUserSettingByHandle(trim($args['handle']), (int)$args['user_id']));
});

$app->get('/settings/group/{group_id}/default/{handle}', function (Request $request, Response $response, array $args) use ($app) {
    $settings = new PBXSettings();
    if (!(int)$args['group_id']) {
        return $response
            ->withJson(['result' => false, 'message' => 'Группа не обнаружена'])
            ->withStatus(400);
    }

    return $response->withJson($settings->deleteGroupSettingByHandle(trim($args['handle']), (int)$args['group_id']));
});

$app->get('/settings/default', function (Request $request, Response $response, array $args) use ($app) {
  $settings = new PBXSettings();

  return $response->withJson($settings->getDefaultSettings());
});

$app->post('/settings/default/save', function (Request $request, Response $response, array $args) use ($app) {
  $params = $request->getParam("settings", "");
  $queues = $request->getParam("queues", 0);
  $set = new PBXSettings();

  if (isset($params)) {
    return $response->withJson(["result" => $set->setDefaultSettings($params, $queues)]);
  }
  return $response->withJson(["result" => FALSE]);
});

$app->get('/settings/dialingRules', function (Request $request, Response $response, array $args) use ($app) {
  $settings = new PBXSettings();

  return $response->withJson($settings->getDialingRules());
});

$app->post('/settings/dialingRules/save', function (Request $request, Response $response, array $args) use ($app) {
  $params = $request->getParam("dialingRules", "");
  $set = new PBXSettings();

  if (isset($params)) {
    $res = $set->setDialingRulesSettings($params);
    if (is_bool($res)) {
        return $response->withJson(["result" => $res]);
    } elseif (is_string($res)) {
      return $response->withJson(["result" => false, "message" => $res]);
    }
  }
  return $response->withJson(["result" => false]);
});

$app->get('/groups/list', function (Request $request, Response $response, array $args) use ($app) {
  $user = new User();

  $filter = $request->getParam('filter', "");
  $start = $request->getParam('start', 0);
  $count = $request->getParam('count', 20);

  return $response->withJson([
    "data" => $user->getAllGroups($filter, $start, $count, 0),
    "total_count" => $user->getAllGroups($filter, $start, $count, 1),
    "pos" => $start
  ]);
})->add('\App\Middleware\OnlyAuthUser');

$app->post('/groups/{id}/save', function (Request $request, Response $response, array $args) use ($app) {
  $params = $request->getParams();
  $id = intval($args['id']);
  $user = new User();

  return $response->withJson($user->addUpdateGroup($params));
});

$app->post('/groups/{id}/remove', function (Request $request, Response $response, array $args) use ($app) {
  $params = $request->getParams();
  $id = intval($args['id']);
  $user = new User();

  return $response->withJson($user->removeGroup($id));
})->add('\App\Middleware\OnlyAuthUser');

$app->get('/groups/users/short', function (Request $request, Response $response, array $args) use ($app) {
  $user = new User();
  $filter = $request->getParam('filter', "");
  $nameAsValue = intval($request->getParam('nameasvalue', 0));

  $count = $user->fetchList($filter, 0, 0, 1);
  return $response->withJson(
    $user->fetchList($filter, 0, $count, 0, 1, $nameAsValue)
  );
})->add('\App\Middleware\OnlyAuthUser');

$app->get('/contact_groups/list', function (Request $request, Response $response, array $args) use ($app) {
  $contact_groups = new PBXContactGroups();

  $filter = $request->getParam('filter', "");
  $start = $request->getParam('start', 0);
  $count = $request->getParam('count', 20);

  return $response->withJson([
    "data" => $contact_groups->fetchList($filter, $start, $count, 0),
    "total_count" => $contact_groups->fetchList($filter, $start, $count, 1),
    "pos" => $start
  ]);
})->add(new SecureRouteMiddleware($app->getContainer()->get('roleProvider')))->add(new SetRoles(['phc.admin', 'erpico.admin']));

$app->post('/contact_groups/{id}/save', function (Request $request, Response $response, array $args) use ($app) {
  $id = intval($args["id"]);
  $contact_groups = new PBXContactGroups($id);

  $name = $request->getParam("name", "");
  $queues = $request->getParam("queues", "");
  $items_users = $request->getParam("items_users", "");
  $items_queues = $request->getParam("items_queues", "");

  return $response->withJson($contact_groups->save($name, $queues, $items_users, $items_queues));
})->add(new SecureRouteMiddleware($app->getContainer()->get('roleProvider')))->add(new SetRoles(['phc.admin', 'erpico.admin']));

$app->post('/contact_groups/{id}/remove', function (Request $request, Response $response, array $args) use ($app) {
  $id = intval($args["id"]);
  $contact_groups = new PBXContactGroups($id);

  return $response->withJson($contact_groups->remove($id));
})->add(new SecureRouteMiddleware($app->getContainer()->get('roleProvider')))->add(new SetRoles(['phc.admin', 'erpico.admin']));

$app->post('/contact_groups/{id}', function (Request $request, Response $response, array $args) use ($app) {
  $id = intval($args["id"]);
  $contact_groups = new PBXContactGroups($id);

  return $response->withJson($contact_groups->getFullInfo());
})->add(new SecureRouteMiddleware($app->getContainer()->get('roleProvider')))->add(new SetRoles(['phc.admin', 'erpico.admin']));

$app->get('/auth/info', function (Request $request, Response $response, array $args) use ($app) {
  $data = $app->getContainer()['auth']->getInfo();
  $data['instance'] =  $app->getContainer()['instance_id'];
  return $response->withJson($data);
})->add('\App\Middleware\OnlyAuthUser');

$app->get('/auth/logout', function (Request $request, Response $response, array $args) use ($app) {
  return $response->withJson(["error" => !$app->getContainer()['auth']->logout()]);
})->add('\App\Middleware\OnlyAuthUser');

$app->get('/auth/settings', function (Request $request, Response $response, array $args) use ($app) {
  return $response->withJson(["data" => $app->getContainer()['auth']->getAuthUserSettings()]);
})->add('\App\Middleware\OnlyAuthUser');

$app->get('/phones/list', function (Request $request, Response $response, array $args) use ($app) {
  $phone = new PBXPhone();

  $filter = $request->getParam('filter', "");
  $start = $request->getParam('start', 0);
  $count = $request->getParam('count', 20);
  $sort = $request->getParam('sort', "");

  $data = $phone->fetchList($filter, $start, $count, 0, true, $sort);

  $phone->appendRealtime($data);

  return $response->withJson([
    "data" => $data,
    "total_count" => $phone->fetchList($filter, $start, $count, 1),
    "pos" => $start
  ]);
})->add("\App\Middleware\OnlyAdmin");

$app->get('/phones/list/short', function (Request $request, Response $response, array $args) use ($app) {
  $phone = new PBXPhone();

  $filter = $request->getParam('filter', "");
  $result = [];

  foreach ($phone->fetchList($filter) as $item) {
    $result[] = [
      'id' => $item['id'], 'phone' => $item['phone'], 'model' => $item['model']
    ];
  }

  return $response->withJson($result);
})->add('\App\Middleware\OnlyAuthUser');

$app->post('/phones/{phone_id}/save', function (Request $request, Response $response, array $args) use ($app) {
  $phone = new PBXPhone();

  $values = $request->getParams();
  $res = $phone->addUpdate($values);
  return $response->withJson($res);
})->add('\App\Middleware\OnlyAuthUser');

$app->post('/phones/{phone_id}/remove', function (Request $request, Response $response, array $args) use ($app) {
  $phone = new PBXPhone();

  $id = intval($args["phone_id"]);
  $res = $phone->remove($id);
  return $response->withJson($res);
})->add('\App\Middleware\OnlyAuthUser');

$app->get('/queues/list', function (Request $request, Response $response, array $args) use ($app) {
  $queue = new PBXQueue();
  $filter = $request->getParam('filter', "");
  $start = $request->getParam('start', 0);
  $count = $request->getParam('count', 20);

  return $response->withJson([
    "data" => $queue->fetchList($filter, $start, $count, 0),
    "total_count" => $queue->fetchList($filter, $start, $count, 1),
    "pos" => $start
  ]);
})->add(new SecureRouteMiddleware($app->getContainer()->get('roleProvider')))->add(new SetRoles(['phc.admin', 'erpico.admin']));

$app->get('/queues/list/short', function (Request $request, Response $response, array $args) use ($app) {
  $queue = new PBXQueue();
  $count = $queue->fetchList("", 0, 0, 1);
  $nameAsValue = intval($request->getParam('nameasvalue', 0));

  return $response->withJson($queue->fetchList("", 0, $count, 0, $nameAsValue));
})->add('\App\Middleware\OnlyAuthUser');

$app->post('/queues/{queues_id}/save', function (Request $request, Response $response, array $args) use ($app) {
  $queue = new PBXQueue();

  $values = $request->getParams();
  $res = $queue->addUpdate($values);

  return $response->withJson($res);
})->add(new SecureRouteMiddleware($app->getContainer()->get('roleProvider')))->add(new SetRoles(['phc.admin', 'erpico.admin']));

$app->post('/queues/{queues_id}/remove', function (Request $request, Response $response, array $args) use ($app) {
  $queue = new PBXQueue();

  $id = intval($args["queues_id"]);
  $res = $queue->remove($id);

  return $response->withJson($res);
})->add(new SecureRouteMiddleware($app->getContainer()->get('roleProvider')))->add(new SetRoles(['phc.admin', 'erpico.admin']));

$app->get('/queues/code', function (Request $request, Response $response, array $args) use ($app) {
  $queue = new PBXQueue();

  $name = $request->getParam("name");
  $res = $queue->getCode($name);

  return $response->withJson([
    "result" => true,
    "message" => $res
  ]);
})->add(new SecureRouteMiddleware($app->getContainer()->get('roleProvider')))->add(new SetRoles(['phc.admin', 'erpico.admin']));

$app->get('/channels/code', function (Request $request, Response $response, array $args) use ($app) {
  $channels = new PBXChannel();

  $name = $request->getParam("name");
  $res = $channels->getCode($name);
  return $response->withJson([
    "result" => true,
    "message" => $res
  ]);
})->add('\App\Middleware\OnlyAuthUser');

$app->get('/channels/list', function (Request $request, Response $response, array $args) use ($app) {
  $channels = new PBXChannel();

  $filter = $request->getParam('filter', "");
  $start = $request->getParam('start', 0);
  $count = $request->getParam('count', 20);

  return $response->withJson([
    "data" => $channels->fetchList($filter, $start, $count, 0),
    "total_count" => $channels->fetchList($filter, $start, $count, 1),
    "pos" => $start
  ]);
})->add('\App\Middleware\OnlyAuthUser');

$app->post('/channels/{channels_id}/save', function (Request $request, Response $response, array $args) use ($app) {
  $channels = new PBXChannel();

  $values = $request->getParams();
  $res = $channels->addUpdate($values);
  return $response->withJson($res);
})->add('\App\Middleware\OnlyAuthUser');

$app->post('/channels/{channels_id}/remove', function (Request $request, Response $response, array $args) use ($app) {
  $channels = new PBXChannel();

  $id = intval($args["channels_id"]);
  $res = $channels->remove($id);
  return $response->withJson($res);
})->add('\App\Middleware\OnlyAuthUser');

$app->get('/outgoingcampaign/list', function (Request $request, Response $response, array $args) use ($app) {
  $outgoingcampaign = new PBXOutgoingCampaign();

  $filter = $request->getParam('filter', "");
  $start = $request->getParam('start', 0);
  $count = $request->getParam('count', 20);

  return $response->withJson([
    "data" => $outgoingcampaign->fetchList($filter, $start, $count, 0),
    "total_count" => $outgoingcampaign->fetchList($filter, $start, $count, 1),
    "pos" => $start
  ]);
})->add(new SecureRouteMiddleware($app->getContainer()->get('roleProvider')))->add(new SetRoles(['phc.oc', 'erpico.admin']));

$app->get('/outgoingcampaign/{id}/queues', function (Request $request, Response $response, array $args) use ($app) {
  $outgoingcampaign = new PBXOutgoingCampaign();

  return $response->withJson(
    $outgoingcampaign->getContacts(intval($args["id"]))
  );
})->add(new SecureRouteMiddleware($app->getContainer()->get('roleProvider')))->add(new SetRoles(['phc.oc', 'erpico.admin']));

$app->get('/outgoingcampaign/{id}/results', function (Request $request, Response $response, array $args) use ($app) {
  $outgoingcampaign = new PBXOutgoingCampaign();

  return $response->withJson(
    $outgoingcampaign->getContactsResults(intval($args["id"]))
  );
})->add(new SecureRouteMiddleware($app->getContainer()->get('roleProvider')))->add(new SetRoles(['phc.oc', 'erpico.admin']));

$app->get('/outgoingcampaign/result/{id}/calls', function (Request $request, Response $response, array $args) use ($app) {
  $outgoingcampaign = new PBXOutgoingCampaign();

  return $response->withJson($outgoingcampaign->getContactCalls(intval($args["id"])));
})->add(new SecureRouteMiddleware($app->getContainer()->get('roleProvider')))->add(new SetRoles(['phc.oc', 'erpico.admin']));

$app->post('/outgoingcampaign/{id}/save', function (Request $request, Response $response, array $args) use ($app) {
  $outgoingcampaign = new PBXOutgoingCampaign();
  $values = $request->getParams();

  return $response->withJson($outgoingcampaign->addUpdate($values));
})->add(new SecureRouteMiddleware($app->getContainer()->get('roleProvider')))->add(new SetRoles(['phc.oc', 'erpico.admin']));

$app->get('/outgoingcampaign/{id}/copy', function (Request $request, Response $response, array $args) use ($app) {
    $outgoingcampaign = new PBXOutgoingCampaign();
    $queues = $request->getParam('queues', 0);


    $copyResult = $outgoingcampaign->copy(intval($args["id"]), $queues);
    return $response->withJson([
        "result" => 1,
        "id" => $copyResult,
        "message" => $copyResult ? "Копирование прошло успешно!" : 'Ошибка копирования',
    ]);
})->add(new SecureRouteMiddleware($app->getContainer()->get('roleProvider')))->add(new SetRoles(['phc.oc', 'erpico.admin']));

$app->post('/outgoingcampaign/{id}/remove', function (Request $request, Response $response, array $args) use ($app) {
  $outgoingcampaign = new PBXOutgoingCampaign();
  $result = $outgoingcampaign->remove(intval($args["id"]));
  return $response->withJson([
    "result" => $result,
    "message" => $result ? "Удаление прошло успешно!" : 'Ошибка удаления',
  ]);
})->add(new SecureRouteMiddleware($app->getContainer()->get('roleProvider')))->add(new SetRoles(['phc.oc', 'erpico.admin']));

$app->get('/outgoingcampaign/{id}/state/{state}', function (Request $request, Response $response, array $args) use ($app) {
  $outgoingcampaign = new PBXOutgoingCampaign();
  $id = intval($args['id']);
  $state = intval($args['state']);

  return $response->withJson($outgoingcampaign->setState($id, $state));
})->add(new SecureRouteMiddleware($app->getContainer()->get('roleProvider')))->add(new SetRoles(['phc.oc', 'erpico.admin']));

$app->get('/outgoingcampaign/{id}/settings', function (Request $request, Response $response, array $args) use ($app) {
  $outgoingcampaign = new PBXOutgoingCampaign();
  $id = intval($args['id']);

  return $response->withJson($outgoingcampaign->getSettings($id));
})->add(new SecureRouteMiddleware($app->getContainer()->get('roleProvider')))->add(new SetRoles(['phc.oc', 'erpico.admin']));

$app->post('/outgoingcampaign/{id}/settings/save', function (Request $request, Response $response, array $args) use ($app) {
  $outgoingcampaign = new PBXOutgoingCampaign();
  $id = intval($args['id']);
  $actions_after_call = $request->getParam("actions_after_call", 0);
  $stop_campaign = $request->getParam("stop_company");
  $other = $request->getParam("other");

  return $response->withJson(["result" => $outgoingcampaign->updateSettings($id, $actions_after_call, $stop_campaign, $other)]);
})->add(new SecureRouteMiddleware($app->getContainer()->get('roleProvider')))->add(new SetRoles(['phc.oc', 'erpico.admin']));

$app->get('/outgoingcampaign/{id}/journal', function (Request $request, Response $response, array $args) use ($app) {
    $outgoingcampaign = new PBXOutgoingCampaign();
    $id = intval($args['id']);
    $filters['start'] = $request->getParam("start", '');
    $filters['end'] = $request->getParam("end", '');

    return $response->withJson($outgoingcampaign->getJournal($id, $filters));
})->add(new SecureRouteMiddleware($app->getContainer()->get('roleProvider')))->add(new SetRoles(['phc.oc', 'erpico.admin']));

$app->get('/outgoingcampaign/{id}/journal/{j_id}', function (Request $request, Response $response, array $args) use ($app) {
  $outgoingcampaign = new PBXOutgoingCampaign();
  $j_id = intval($args['j_id']);
  $filters['start'] = $request->getParam("start", '');
  $filters['end'] = $request->getParam("end", '');

  return $response->withJson($outgoingcampaign->getJournalLeads($j_id, $filters));
})->add(new SecureRouteMiddleware($app->getContainer()->get('roleProvider')))->add(new SetRoles(['phc.oc', 'erpico.admin']));

$app->get('/outgoingcampaign/statistics', function (Request $request, Response $response, array $args) use ($app) {
  $outgoingcampaign = new PBXOutgoingCampaign();
  $filter = $request->getParam('filter', "");

  $campaigns = $outgoingcampaign->fetchList($filter);

  $statistics = [];
  foreach ($campaigns as $campaign) {
    $statistics[] = $outgoingcampaign->getStatistics($campaign['id'], $campaign['name']);
  }

  return $response->withJson($statistics);
})->add(new SecureRouteMiddleware($app->getContainer()->get('roleProvider')))->add(new SetRoles(['phc.oc', 'erpico.admin']));

$app->get('/outgoings/phone', function (Request $request, Response $response, array $args) use ($app) {
  $outgoingcampaign = new PBXOutgoingCampaign();
  $phone = $request->getParam('phone', "");

  return $response->withJson($outgoingcampaign->getOutgoingIdsByPhone($phone));
})->add(new SecureRouteMiddleware($app->getContainer()->get('roleProvider')))->add(new SetRoles(['phc.oc', 'erpico.admin']));

$app->get('/outgoingcampaign/statistics/{id}', function (Request $request, Response $response, array $args) use ($app) {
  $outgoingcampaign = new PBXOutgoingCampaign();
  $id = intval($args['id']);
  $filter = $request->getParam('filter', "");
  $filter['id'] = $id;

  $campaign = $outgoingcampaign->fetchList($filter)[0];

  return $response->withJson($outgoingcampaign->getStatistics($id, $campaign['name']));
})->add(new SecureRouteMiddleware($app->getContainer()->get('roleProvider')))->add(new SetRoles(['phc.oc', 'erpico.admin']));

$app->post('/outgoingcampaign/{id}/archive', function (Request $request, Response $response, array $args) use ($app) {
  $outgoingcampaign = new PBXOutgoingCampaign();
  $id = intval($args['id']);

  $res = $outgoingcampaign->toggleArchive($id);

  if (isset($res['action'])) {
    $action = $res['action'];
    $res = $res['res'];
  } else {
    $message = $res;
    $res = false;
  }

  return $response->withJson(['res' => $res, 'message' => $message ?? null, 'action' => $action ?? null]);
})->add(new SecureRouteMiddleware($app->getContainer()->get('roleProvider')))->add(new SetRoles(['phc.oc', 'erpico.admin']));

// SMS messaging

$app->get('/acd/sms', function (Request $request, Response $response, array $args) use ($app) {
  return $this->renderer->render($response, 'sms.phtml', $args);
});

// RULES 

$app->get('/rules/list', function (Request $request, Response $response, array $args) use ($app) {
  $rule = new PBXRules();

  return $response->withJson($rule->fetchList());
})->add('\App\Middleware\OnlyAuthUser');

$app->post('/rules/groups/{id}/save', function (Request $request, Response $response, array $args) use ($app) {
  $rule = new PBXRules();
  $rules = $request->getParam("rules", "");

  $res = $rule->saveGroup($rules, intval($args['id']));
  return $response->withJson(["result" => $res]);
})->add(new SecureRouteMiddleware($app->getContainer()->get('roleProvider')))->add(new SetRoles(['erpico.admin']));

$app->post('/rules/users/{id}/save', function (Request $request, Response $response, array $args) use ($app) {
  $rule = new PBXRules();
  $rules = $request->getParam("rules", "");

  $res = $rule->saveUser($rules, intval($args['id']));

  return $response->withJson(["result" => $res]);
})->add(new SecureRouteMiddleware($app->getContainer()->get('roleProvider')))->add(new SetRoles(['erpico.admin']));


$app->get('/phones/groups/list', function (Request $request, Response $response, array $args) use ($app) {
  $phone = new PBXPhone();

  $filter = $request->getParam('filter', "");
  $start = $request->getParam('start', 0);
  $count = $request->getParam('count', 20);

  return $response->withJson([
    "data" => $phone->fetchGroupsList($filter, $start, $count, 0),
    "total_count" => $phone->fetchGroupsList($filter, $start, $count, 1),
    "pos" => $start
  ]);
})->add(new SecureRouteMiddleware($app->getContainer()->get('roleProvider')))->add(new SetRoles(['erpico.admin']));

$app->get('/phones/groups/code', function (Request $request, Response $response, array $args) use ($app) {
  $phone = new PBXPhone();

  $name = $request->getParam("name");
  $res = $phone->getGroupCode($name);
  return $response->withJson([
    "result" => true,
    "message" => $res
  ]);
})->add(new SecureRouteMiddleware($app->getContainer()->get('roleProvider')))->add(new SetRoles(['erpico.admin']));

$app->get('/phones/groups/list/short', function (Request $request, Response $response, array $args) use ($app) {
  $phone = new PBXPhone();

  $filter = $request->getParam('filter', "");

  return $response->withJson($phone->fetchGroupsList($filter));
})->add(new SecureRouteMiddleware($app->getContainer()->get('roleProvider')))->add(new SetRoles(['erpico.admin']));

$app->post('/phones/groups/{id}/save', function (Request $request, Response $response, array $args) use ($app) {
  $id = intval($args["id"]);
  $phone = new PBXPhone();
  $params = $request->getParams();

  return $response->withJson($phone->addUpdatePhoneGroup($params));
})->add(new SecureRouteMiddleware($app->getContainer()->get('roleProvider')))->add(new SetRoles(['erpico.admin']));

$app->post('/phones/groups/{id}/remove', function (Request $request, Response $response, array $args) use ($app) {
  $id = intval($args["id"]);
  $phone = new PBXPhone();

  return $response->withJson($phone->removePhoneFroup($id));
})->add(new SecureRouteMiddleware($app->getContainer()->get('roleProvider')))->add(new SetRoles(['erpico.admin']));

$app->any('/config/sip', function (Request $request, Response $response, array $args) use ($app) {
  $helper = new PBXConfigHelper();

  return $response->withJson($helper->getOptions(PBXConfigHelper::SIP_FILE));
})->add(new SecureRouteMiddleware($app->getContainer()->get('roleProvider')))->add(new SetRoles(['phc.admin', 'erpico.admin']));

$app->any('/config/queues', function (Request $request, Response $response, array $args) use ($app) {
  $helper = new PBXConfigHelper();

  return $response->withJson($helper->getOptions(PBXConfigHelper::QUEUES_FILE));
})->add(new SecureRouteMiddleware($app->getContainer()->get('roleProvider')))->add(new SetRoles(['phc.admin', 'erpico.admin']));

$app->any('/config/extensions', function (Request $request, Response $response, array $args) use ($app) {
  $helper = new PBXConfigHelper();

  return $response->withJson($helper->getOptions(PBXConfigHelper::RULES_FILE));
})->add(new SecureRouteMiddleware($app->getContainer()->get('roleProvider')))->add(new SetRoles(['phc.admin', 'erpico.admin']));

$app->any('/config/channel_drivers', function (Request $request, Response $response, array $args) use ($app) {
  $conf = $app->getContainer()->get('dbConfig');
  $channel_drivers = $conf['channel_drivers'];
  $channel_drivers = explode(',', $channel_drivers);

  return $response->withJson($channel_drivers);
})->add(new SecureRouteMiddleware($app->getContainer()->get('roleProvider')))->add(new SetRoles(['phc.admin', 'erpico.admin']));

$app->get('/extended_calls/list', function (Request $request, Response $response, array $args) use ($app) {
  $oldCdr = new PBXOldCdr();
  $filter = $request->getParam("filter", []);

  return $response->withJson($oldCdr->fetchList($filter));
})->add(new SecureRouteMiddleware($app->getContainer()->get('roleProvider')))->add(new SetRoles(['phc.reports', 'erpico.admin']));

$app->get('/extended_calls/list/trafic', function (Request $request, Response $response, array $args) use ($app) {
  $oldCdr = new PBXOldCdr();
  $filter = $request->getParam("filter", []);

  return $response->withJson($oldCdr->getTrafic($filter));
})->add(new SecureRouteMiddleware($app->getContainer()->get('roleProvider')))->add(new SetRoles(['phc.reports', 'erpico.admin']));

$app->get('/extended_contact_calls/list', function (Request $request, Response $response, array $args) use ($app) {
  $oldContactCdr = new PBXOldContactCdr();
  $filter = $request->getParam("filter", []);

  return $response->withJson($oldContactCdr->fetchList($filter));
})->add(new SecureRouteMiddleware($app->getContainer()->get('roleProvider')))->add(new SetRoles(['phc.reports', 'erpico.admin']));

$app->any('/config/phone/{mac}', function (Request $request, Response $response, array $args) use ($app) {
  global $app;
  $container = $app->getContainer();

  $mac = strtoupper(str_replace(".cfg", "", $args['mac']));

  if (strlen($mac) == 12) {
    // Add dashes
    $nmac = "";
    for ($i = 0; $i < strlen($mac); $i++) {
      $nmac .= $mac[$i];
      if ($i % 2) $nmac .= ":";
    }
    $mac = trim($nmac, ":");
  }

  $po = new PBXPhone();

  $settings = new PBXSettings();
  $remoteConfigPhoneDeny = $settings->getSettingByHandle('remote.config.phone.deny')['val'];
  if ($remoteConfigPhoneDeny === '1') {

    if (!$mac) {
      return $response->withJson(['result' => false, 'message' => 'Отсутствует мак-адрес телефона'], 400);
    }

    $phone = $po->fetchList(['mac' => $mac])[0];
    if (isset($phone['group_id'])) {
      $groups = $po->fetchGroupsList(['id' => $phone['group_id']]);
      $remoteGroupConfigPhoneAddresses = [];
      foreach ($groups as $group) {
        foreach (explode(',', $group['remote_config_phone_addresses']) as $address) {
          if ($address !== '') $remoteGroupConfigPhoneAddresses[] = $address;
        }
      }
    }

    if (isset($phone['remote_config_phone_addresses']) && $phone['remote_config_phone_addresses'] !== '') {
      $remoteConfigPhoneAddresses = explode(',', $phone['remote_config_phone_addresses']);
    } else if ($remoteGroupConfigPhoneAddresses && $remoteGroupConfigPhoneAddresses !== '') {
      $remoteConfigPhoneAddresses = $remoteGroupConfigPhoneAddresses;
    } else {
      $remoteConfigPhoneAddresses = $settings->getSettingByHandle('remote.config.phone.addresses')['val'];
      $remoteConfigPhoneAddresses = explode(',', $remoteConfigPhoneAddresses);
    }

    if (!in_array($_SERVER['REMOTE_ADDR'], $remoteConfigPhoneAddresses)) {
      return $response->withJson(['result' => false, 'message' => 'Incorrect ip address of client'], 400);
    }
  }

  // Search for phone with such MAC
  $list = $po->fetchList(["mac" => $mac]);
  if (count($list) == 0) {
    return $response->withStatus(404)
      ->withHeader('Content-Type', 'text/plain')
      ->write('No configuration file for this phone');
  }

  $data = $list[0];

  $data['server'] = $container['server_host'];

  $template = file_get_contents(__DIR__ . "/../templates/phones/yealink-t.tpl");

  foreach ($data as $k => $v) {
    $template = str_replace("#$k#", $v, $template);
  }

  return $response->withStatus(200)
    ->withHeader('Content-Type', 'text/plain')
    ->write($template);
}); //->add(new SecureRouteMiddleware($app->getContainer()->get('roleProvider')))->add(new SetRoles(['erpico.admin']));

$app->any('/config/contacts', function (Request $request, Response $response, array $args) use ($app) {

  $user = new User();
  $users = $user->fetchList(["state" => 1], 0, 1000, 0);

  $result = '<YeastarIPPhoneDirectory>' . "\n";

  foreach ($users as $e) {
    if (!strlen($e['phone'])) continue;
    $result .= '<DirectoryEntry><Name>' . $e['fullname'] . '</Name><Telephone>' . $e['phone'] . '</Telephone></DirectoryEntry>' . "\n";
  }

  $result .= "</YeastarIPPhoneDirectory>";

  $response->getBody()->write($result);
  return $response->withStatus(200)
    ->withHeader('Content-Type', 'text/xml');
});

$app->post('/phones/provisioning/start', function (Request $request, Response $response, array $args) use ($app) {
  $data = $request->getParams();

  $ip_phone  = $data['ip'];
  $mac       =  $data['mac'];
  $port_phone = 5060;
  $ip_pbx    = "192.168.139.210";
  $port_pbx  = "5060";

  $settings = new PBXSettings();
  $remoteConfigPhoneDeny = $settings->getSettingByHandle('remote.config.phone.deny')['val'];
  if ($remoteConfigPhoneDeny === '1') {

    if (!$mac) {
      return $response->withJson(['result' => false, 'message' => 'Отсутствует мак-адрес телефона'], 400);
    }

    $PBXPhone = new PBXPhone();
    $phone = $PBXPhone->fetchList(['mac' => $mac])[0];
    if (isset($phone['group_id'])) {
      $groups = $PBXPhone->fetchGroupsList(['id' => $phone['group_id']]);
      $remoteGroupConfigPhoneAddresses = [];
      foreach ($groups as $group) {
        foreach (explode(',', $group['remote_config_phone_addresses']) as $address) {
          if ($address !== '') $remoteGroupConfigPhoneAddresses[] = $address;
        }
      }
    }

    if (isset($phone['remote_config_phone_addresses']) && $phone['remote_config_phone_addresses'] !== '') {
      $remoteConfigPhoneAddresses = explode(',', $phone['remote_config_phone_addresses']);
    } else if ($remoteGroupConfigPhoneAddresses && $remoteGroupConfigPhoneAddresses !== '') {
      $remoteConfigPhoneAddresses = $remoteGroupConfigPhoneAddresses;
    } else {
      $remoteConfigPhoneAddresses = $settings->getSettingByHandle('remote.config.phone.addresses')['val'];
      $remoteConfigPhoneAddresses = explode(',', $remoteConfigPhoneAddresses);
    }

    if (!in_array($_SERVER['REMOTE_ADDR'], $remoteConfigPhoneAddresses)) {
      return $response->withJson(['result' => false, 'message' => 'Incorrect ip address of client'], 400);
    }
  }

  $phone_user = 'autoprovision_user';
  $sock = socket_create(AF_INET, SOCK_DGRAM, SOL_UDP);

  $url = "http://tns-e-main.clients.tlc.local/config/phone/80:5E:C0:18:09:E9"; //http://$ip_pbx/config/phone/$mac";

  /*$msg =  "NOTIFY sip:{$phone_user}@{$ip_phone}:{$port_phone};ob SIP/2.0\r\n".
        "Via: SIP/2.0/UDP {$ip_pbx}:{$port_pbx};branch=z9hG4bK12fd4e5c;rport\r\n".
        "Max-Forwards: 70\r\n".
        "From: \"asterisk\" <sip:asterisk@{$ip_pbx}>;tag=as54cd2be9\r\n".
        "To: <sip:{$phone_user}@{$ip_phone}:{$port_phone};ob>\r\n".
        "Contact: <sip:asterisk@{$ip_pbx}:{$port_pbx}>\r\n".
        //"Call-ID: 4afab6ce2bff0be11a4af41064340242@{$ip_pbx}:{$port_pbx}\r\n".
        //"CSeq: 10 NOTIFY\r\n".
        "User-Agent: mikopbx\r\n".
        "Content-Type: application/url\r\n".
        "Subscription-State: terminated;reason=timeout\r\n".
        "Event: ua-profile;profile-type=\"device\";vendor=\"Erpico\";model=\"ErpicoPBX\";version=\"3.0\"\r\n".
        'Content-Length: '.strlen($url)."\r\n".
         "\r\n".
         $url;
  $len = strlen($msg);
  socket_sendto($sock, $msg, $len, 0, $ip_phone, $port_phone);
  */

  $msg =  "NOTIFY sip:{$phone_user}@{$ip_phone}:{$port_phone};ob SIP/2.0\r\n" .
    "Via: SIP/2.0/UDP {$ip_pbx}:{$port_pbx};branch=z9hG4bK12fd4e5c;rport\r\n" .
    "Max-Forwards: 70\r\n" .
    "From: \"asterisk\" <sip:asterisk@{$ip_pbx}>;tag=as54cd2be9\r\n" .
    "To: <sip:{$phone_user}@{$ip_phone}:{$port_phone};ob>\r\n" .
    "Contact: <sip:asterisk@{$ip_pbx}:{$port_pbx}>\r\n" .
    "Call-ID: 4afab6ce2bff0be11a4af41064340242@{$ip_pbx}:{$port_pbx}\r\n" .
    //"CSeq: 102 NOTIFY\r\n".
    "User-Agent: mikopbx\r\n" .
    "Allow: INVITE, ACK, CANCEL, OPTIONS, BYE, REFER, SUBSCRIBE, NOTIFY, INFO, PUBLISH, MESSAGE\r\n" .
    "Supported: replaces, timer\r\n" .
    "Subscription-State: terminated\r\n" .
    "Event: check-sync;reboot=true\r\n" .
    "Content-Length: 0\r\n\n";

  $len = strlen($msg);
  socket_sendto($sock, $msg, $len, 0, $ip_phone, $port_phone);
  socket_close($sock);

  return $response->withStatus(200)
    ->withHeader('Content-Type', 'text/plain')
    ->write("Something happen: $url");
})->add(new SecureRouteMiddleware($app->getContainer()->get('roleProvider')))->add(new SetRoles(['erpico.admin']));

$app->get('/aliases/list', function (Request $request, Response $response, array $args) use ($app) {
  $filter = $request->getParam('filter', "");
  $start = $request->getParam('start', 0);
  $count = $request->getParam('count', 20);

  $aliases = new PBXAliases();

  return $response->withJson([
    "data" => $aliases->fetchList($filter, $start, $count, 0),
    "pos" => (int)$start,
    "total_count" => $aliases->fetchList($filter, $start, $count, 1)
  ]);
})->add(new SecureRouteMiddleware($app->getContainer()->get('roleProvider')))->add(new SetRoles(['erpico.admin']));

$app->get('/aliases/type/short', function (Request $request, Response $response, array $args) use ($app) {
  $aliases = new PBXAliases();

  return $response->withJson($aliases->getType());
})->add(new SecureRouteMiddleware($app->getContainer()->get('roleProvider')))->add(new SetRoles(['erpico.admin']));

$app->post('/aliases/{alias_id}/save', function (Request $request, Response $response, array $args) use ($app) {
  $aliases = new PBXAliases();

  $values = $request->getParams();
  $res = $aliases->addUpdate($values);
  return $response->withJson($res);
})->add(new SecureRouteMiddleware($app->getContainer()->get('roleProvider')))->add(new SetRoles(['erpico.admin']));

$app->post('/aliases/{alias_id}/remove', function (Request $request, Response $response, array $args) use ($app) {
  $aliases = new PBXAliases();
  $id = intval($args["alias_id"]);
  $res = $aliases->remove($id);

  return $response->withJson($res);
})->add(new SecureRouteMiddleware($app->getContainer()->get('roleProvider')))->add(new SetRoles(['erpico.admin']));

$app->any('/user/exten/save', function (Request $request, Response $response, array $args) use ($app) {
  $user = $app->getContainer()['auth'];

  $ext = $request->getParam('ext', '');
  if (strlen($ext) == 0) {
    return $response->withJson(["error" => 1, "message" => "No extension provided"]);
  }

  $result = $user->saveExt($ext);

  return $response->withJson(["error" => $result]);
})->add(new SecureRouteMiddleware($app->getContainer()->get('roleProvider')))->add(new SetRoles(['erpico.admin']));

$app->get('/blacklist', function (Request $request, Response $response, array $args) use ($app) {

  if (!($filter = $request->getParam('filter', ""))) {
    $filter = [];
  }
  $filter["deleted"] = 0;
  $start = $request->getParam('start', 0);
  $count = $request->getParam('count', 20);

  $blacklist = new PBXBlacklist($app->getContainer());

  return $response->withJson([
    "data" => $blacklist->fetchList($filter, $start, $count, 0),
    "filter" => $filter
  ]);
})->add('\App\Middleware\OnlyAuthUser')->add(new SetRoles(['erpico.admin']));

$app->post('/blacklist/add', function (Request $request, Response $response, array $args) use ($app) {
  $blacklist = new PBXBlacklist($app->getContainer());
  $result = $blacklist->saveBlacklistItem($request->getParams());

  return $response->withJson([
    "result" => $result,
    "message" => $result ? "Сохранение прошло успешно!" : "Ошибка сохранения"
  ]);
})->add('\App\Middleware\OnlyAuthUser')->add(new SetRoles(['erpico.admin']));

$app->post('/blacklist/{id}/update', function (Request $request, Response $response, array $args) use ($app) {
  $id = intval($args["id"]);
  $blacklist = new PBXBlacklist($app->getContainer());
  if ($id) {
    $result = $blacklist->updateBlacklistItem($id, $request->getParams());
    return $response->withJson([
      "result" => $result,
      "message" => $result ? "Изменение прошло успешно!" : "Ошибка изменения"
    ]);
  }
})->add('\App\Middleware\OnlyAuthUser')->add(new SetRoles(['erpico.admin']));

/*
$app->delete('/blacklist/{id}/delete', function (Request $request, Response $response, array $args) use($app){
    $id = intval($args["id"]);
    $blacklist = new PBXBlacklist($app->getContainer());
    $result = $blacklist->deleteFromBlacklist($id);

    return $response->withJson([
        "result" => $result,
        "message" => $result ? "Удаление прошло успешно!" : "Ошибка удаления"
        ]);
})->add('\App\Middleware\OnlyAuthUser')->add(new SetRoles(['erpico.admin']));*/

$app->post('/blacklist/{id}/remove', function (Request $request, Response $response, array $args) use ($app) {
  $id = intval($args["id"]);
  $blacklist = new PBXBlacklist($app->getContainer());
  $result = $blacklist->remove($id);

  return $response->withJson([
    "result" => $result,
    "message" => $result ? "Удаление прошло успешно!" : "Ошибка удаления"
  ]);
})->add('\App\Middleware\OnlyAuthUser')->add(new SetRoles(['erpico.admin']));

$app->get('/vm/email', function (Request $request, Response $response, array $args) {

  $settings = new PBXSettings();
  $smtp_address = $settings->getSettingByHandle('smtp.server')['val'];
  $port = $settings->getSettingByHandle('smtp.port')['val'];
  $user_name = $settings->getSettingByHandle('smtp.user')['val'];
  $password = $settings->getSettingByHandle('smtp.password')['val'];
  $email_sender = $settings->getSettingByHandle('smtp.email')['val'];

  $_email = $request->getParam("to", "rp@erpico.ru"); //"rp@erpico.ru";
  $_subj  = "ErpicoPBX: пропущенный звонок";
  $_text  = "Звонок с номера " . $request->getParam("tel") . " в " . date("d.m.Y H:i:s");

  $mail = new PHPMailer;

  $mail->Mailer = "smtp";
  $mail->Host = $smtp_address ?: 'mail.erpico.ru';  // Specify main and backup SMTP servers
  //$mail->Port = 25;
  $mail->SMTPAuth = true;                               // Enable SMTP authentication
  $mail->Username = $user_name ?: 'noreply'; // SMTP username
  $mail->Password = $password ?: 'oL(H&LVrh7lnyef';
  $mail->SMTPOptions = array(
    'ssl' => array(
      'verify_peer' => false,
      'verify_peer_name' => false,
      'allow_self_signed' => true
    )
  );

  $msg = "";

  $mail->setFrom($email_sender ?: 'noreply@erpicopbx.ru', $user_name ?: 'Erpico PBX');
  $mail->addAddress($_email);

  $mail->Subject = $_subj;
  $mail->IsHTML(true);
  $mail->CharSet = 'UTF-8';

  $mail->AltBody = $_text;
  $mail->Body = $_text;

  return $response->withJson(
    [
      "result" => $mail->send()
    ]
  );
});

$app->get('/system/services', function (Request $request, Response $response, array $args) {
  $guzzleClient = new \GuzzleHttp\Client([
    'auth' => ['erpicopbx', 'k9JjUk4FImZJSrc'],
  ]);

  // Pass the url and the guzzle client to the fXmlRpc Client
  $client = new \fXmlRpc\Client(
    'http://127.0.0.1:9001/RPC2',
    new \fXmlRpc\Transport\HttpAdapterTransport(
      new \Http\Message\MessageFactory\GuzzleMessageFactory(),
      new \Http\Adapter\Guzzle6\Client($guzzleClient)
    )
  );

  // Pass the client to the Supervisor library.
  $supervisor = new \Supervisor\Supervisor($client);

  $processes = $supervisor->getAllProcessInfo();

  return $response->withJson([
    "result" => true,
    "processes" => $processes
  ]);
});

$app->get('/', function (Request $request, Response $response, array $args) {
  // Render index view
  return $this->renderer->render($response, 'index.phtml', $args);
});

$app->post('/import', function (Request $request, Response $response, array $args) use ($app) {

  $result = null;
  $filename = $request->getParam('filename');
  if (empty($filename)) {
    $fileUpload = $_FILES['upload'];
    $filename = $fileUpload['tmp_name'];
  }

  if (file_exists($filename)) {
    $exportImport = new \App\ExportImport($filename);
    $result = $response->withJson([
      "result" => $exportImport->import()
    ]);
  } else {
    $result = $response->withJson([
      "result" => false
    ]);
  }

  return $result;
});

$app->post('/export', function (Request $request, Response $response, array $args) use ($app) {
  $filename = $args['filename'];
  $exportImport = new \App\ExportImport($filename);
  return $exportImport->export();
});

/*$app->post('/upload', function ($request, $response, $args) {
  $result = null;
  $tmpFile = tmpfile();

  $metadata = stream_get_meta_data($tmpFile);
  $result = $metadata;

  return $response->withJson($result);
});*/

//$app->group('/chat', function ($app) use ($app) {
    $user = new Erpico\User();
    $app->any('/chat', function (Request $request, Response $response, array $args) {
        $user = new Erpico\User($this->db);

        return $response->withJson(
            [
                'api' => [
                    "call" => [

                    ],
                    "chat" => [
                    ],
                    "message" => [
                        "Add" => 1,
                        "GetAll" => 1,
                        "Remove" => 1,
                        "ResetCounter" => 1,
                    ]
                ],
                'data' => [
                    'chats' => $user->fetchChatList(),
                    'user' => $user->getId(),
                    'users' => $user->fetchChatList()
                ],
                'websocket' => false
            ]
        );
    });
    $app->get('/chat/users',  function (Request $request, Response $response, array $args) {
        $user = new Erpico\User($this->db);

        return $response->withJson($user->fetchChatList());
    });
    $app->get('/chat/chats',  function (Request $request, Response $response, array $args) {
        $user = new Erpico\User($this->db);

        return $response->withJson($user->fetchChatList());
    });
    $app->get('/chat/users/{id}/messages',  function (Request $request, Response $response, array $args) use ($user) {
        /** @var \App\Chat\ChatMessageRepository $chatMessageRepository */
        $chatMessageRepository = $this->get(\App\Chat\ChatMessageRepository::class);

        return $response->withJson($chatMessageRepository->getAllByRecipientIdAndSenderId($args['id'], $user->getId()));
    });
    $app->post('/chat/users/{id}/messages',  function (Request $request, Response $response, array $args) use ($user) {
        /** @var \App\Chat\ChatMessageRepository $chatMessageRepository */
        $chatMessageRepository = $this->get(\App\Chat\ChatMessageRepository::class);
        $requestBody = $request->getParsedBody();

        $message = new \App\Chat\ChatMessage(
            null,
            $user->getId(),
            $requestBody['recipient_id'],
            false,
            $requestBody['content'],
            new DateTimeImmutable()
        );

        $chatMessageRepository->save($message);

       return $response->withJson($message);
    });
    $app->post('/chat/users/{id}/counter',  function (Request $request, Response $response, array $args) use ($app, $user) {
        /** @var \App\Chat\ChatMessageRepository $chatMessageRepository */
        $chatMessageRepository = $this->get(\App\Chat\ChatMessageRepository::class);

        return $response->withJson(
            $chatMessageRepository->resetUnreadCount($user->getId(), $args['id'])
        );
    });
//});

$app->post('/upload', function ($request, $response, $args) {
    $userId = $request->getParam('userId', 0);
    if ($userId == 0) return $response->withJson([ "status" => "error", "message" => "Неверный идентификатор пользователя"]);
    $config = require(__DIR__ . '/settings.php');

    $uploadPath = $config['settings']['uploadPath'];

    if (isset($_FILES['file'])) {
        $_FILES['upload'] = $_FILES['file'];
    }

    if (is_null($_FILES['upload'])) {
        return $response->withJson([ "status" => "error"]);
    }

    $result = ErpicoFileUploader::moveFile($_FILES['upload'], $userId, $uploadPath);

    return $response->withJson($result);
});

// API_KEYS
$app->get('/api_key', function ($request, $response, $args) {
    $key = new PBXApi_keys(0);

    return $response->withJson($key->getAllKeys());
})->add('\App\Middleware\OnlyAuthUser');

$app->post('/api_key/save', function ($request, $response, $args) {
    $key = new PBXApi_keys(0);
    $data = json_decode($request->getParam('data', ""));

    return $response->withJson($key->saveKey($data));
})->add('\App\Middleware\OnlyAuthUser');

$app->delete('/api_key/{key_id}/delete', function ($request, $response, $args) {
    $key_id = intval($args["key_id"]);
    $key = new PBXApi_keys($key_id);

    return $response->withJson(["result" => $key->deleteKey($key_id)]);
})->add('\App\Middleware\OnlyAuthUser');

//API_KEYS END

$app->get("/timezones", function (Request $request, Response $response, $args) {
    $filter = $request->getParam("filter", 0);
    $timezones = \DateTimeZone::listIdentifiers();
    $filteredTimezones = [];
    if (isset($filter['value']) && $filter['value'] != "") {
        array_walk($timezones, static function($timezone) use ($filter, &$filteredTimezones) {
            if (strpos(strtolower($timezone), strtolower($filter['value'])) !== false) {
                $filteredTimezones[] = ["id" => $timezone, "value" => $timezone];
            }
        });
        return $response->withJson($filteredTimezones);
    }
    return $response->withJson($timezones);
});

$app->get("/specific_phones", function (Request $request, Response $response, $args) {
    $settings = new PBXSettings();

    return $response->withJson($settings->getSpecificPhones());
})->add('\App\Middleware\OnlyAuthUser');

$app->post("/specific_phones/save", function (Request $request, Response $response, $args) {
    $settings = new PBXSettings();
    $data = $request->getParam('data', '');
    if (is_string($data)) $data = json_decode($data, 1);

    return $response->withJson($settings->setSpecificPhones($data));
})->add('\App\Middleware\OnlyAuthUser');