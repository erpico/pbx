<?php

use Slim\Http\Request;
use Slim\Http\Response;
use Erpico\User;
use Erpico\PBXRules;
use App\Middleware\OnlyAdmin;
use App\Middleware\SecureRouteMiddleware;
use App\Middleware\SetRoles;
// Routes

$app->post('/auth/login', function (Request $request, Response $response, array $args) {
    $login = $request->getParam('login');
    $password = $request->getParam('password');

    if (!strlen($login)) return $response->withJson([ "error" => 1, "message" => "No login provided" ]);

    $user = new Erpico\User($this->db);
    $result = $user->login($login, $password, $request->getAttribute('ip_address'));

    return $response->withJson($result);
});

$app->get('/cdr/list', function (Request $request, Response $response, array $args) use($app) {
    $key = $request->getParam('key', 0);
    $status = $request->getParam('status', 0);
    $call = $request->getParam('call', 0);
    $missed = $request->getParam('missed', 0);
    $password = $request->getParam('password', 0);
    $filter = $request->getParam('filter', 0);
    $start = $request->getParam('start', 0);
    $count = $request->getParam('count', 20);
    $lcd = $request->getParam('lcd', 0);
    $lli = $request->getParam('lli', 0);

    $cdr = new PBXCdr($key, $status, $call, $missed);
    // return $response->withJson($cdr->getReport($filter, $start, $count, 0));
    return $response->withJson([
        "data" => $cdr->getReport($filter, $start, $count, 0, 0, $lcd),
        "total_count" => $cdr->getReport($filter, $start, $count, 1),
        "pos" => $lli,
        "server_footer" => $cdr->getReport($filter, $start, $count, 0, 1)
    ]);
})->add('\App\Middleware\OnlyAuthUser');

$app->get('/cdr/list/{id}', function (Request $request, Response $response, array $args) use($app) {
   // $id = $request->getParam('id', 0);
    $id = (float)$args["id"];
   // die(var_dump($id));
    $key = $request->getParam('key', 0);
    $status = $request->getParam('status', 0);
    $call = $request->getParam('call', 0);
    $missed = $request->getParam('missed', 0);

    $cdr = new PBXCdr($key, $status, $call, $missed);
    return $response->withJson($cdr->getReportsByUid($id));
})->add('\App\Middleware\OnlyAuthUser');
/*$app->get('/controllers/findrecord.php', function (Request $request, Response $response, array $args) use($app) {
  $id = $request->getParam('id', 0);
  return $response->withRedirect("/recording/$id"); 
});*/

$findrecord = function (Request $request, Response $response, array $args) use($app) {
  $uid = isset($args['id']) ? $args['id'] : $request->getParam('id', 0);
  $uid = str_replace(".mp3", "", $uid);
  $uid = str_replace(".", "", $uid);

  $cdr = new PBXCdr();
  $row = $cdr->findById($uid);
  if (!is_array($row)) {
      return $response->withStatus(404)
          ->withHeader('Content-Type', 'text/html')
          ->write('Record not found in database');
  }
  
  $filename = "";
  
  if (isset($row['agentname'])) {
      // Queue
      $date = str_replace(" ", "-", $row['calldate']);
  
      $agent = str_replace("/", "-", $row['agentname']);
      
      $uniqid = $row['uniqid'];
      $cid = $row['src'];
  
      $fname = "$date-$cid-$agent-q-$uniqid.wav";
      $path_parts = pathinfo($fname);    

      $filename = "/var/spool/asterisk/monitor/queues/".substr($fname,0,10)."/".substr($fname,11,2)."/".$path_parts['dirname'].'/'.$path_parts['filename'];
      
      if(file_exists($filename.".WAV")) {
        $filename = $filename.".WAV";
      }
      else if(file_exists($filename.".wav")) {
        $filename = $filename.".wav";
      }
      else if(file_exists($filename.".mp3")) {
        $filename = $filename.".mp3";
      } else if (file_exists($filename)) {
      } else {
        $filename = "";
      }
  }
  
  if ($filename == "") {
      // Regular
      $date = str_replace(" ", "-", $row['calldate']);
      $time = strtotime($row['calldate']);    
      $uniqid = $row['uniqueid'];//substr($row['uniqueid'], 0, /*-2*/0);
      if (strlen($uniqid) == 0) $uniqid = $row['uniqid'];
      $src = $row['src'];
      $dst = $row['dst'];
//  var_dump($row);
//  die($uniqid);
      $files = glob("/var/spool/asterisk/monitor/".date('Y-m-d', $time)."/".date('H',$time)."/*-".$uniqid."*");    
//var_dump($files);
      if (!is_array($files) || !count($files)) {        
	  // Last change....
	  
	  $files = glob("/var/spool/asterisk/monitor/".date('Y-m-d', $time)."/".date('H',$time)."/*-$src-*".substr($uniqid, 0, -2)."*");    
	  //var_dump($files);die();
	  if (!is_array($files) || !count($files)) {
            return $response->withStatus(404)
              ->withHeader('Content-Type', 'text/html')
              ->write('Record not found in filesystem');
          }
      }
      $filename = $files[0];    

      if (!file_exists($filename)) {
        return $response->withStatus(404)
          ->withHeader('Content-Type', 'text/html')
          ->write('Record not found in filesystem');
      
      }
  }
//  die($filename);
  $fh = fopen($filename, 'rb');
  $stream = new Slim\Http\Stream($fh);
  return $response            
          ->withBody($stream)
          ->withHeader('Content-Type', 'audio/mpeg')
          ->withHeader('Accept-Ranges', 'bytes')
          ->withHeader('Content-Length', filesize($filename))
          ->withHeader('Content-Transfer-Encoding', 'binary')
          ->withHeader('Content-Disposition', 'attachment; filename="' . basename($filename) . '"');
};

$app->get('/recording/{id}', $findrecord)->setOutputBuffering(false);//->add('\App\Middleware\OnlyAuthUser');
$app->get('/controllers/findrecord.php', $findrecord)->setOutputBuffering(false);//->add('\App\Middleware\OnlyAuthUser');


$app->post('/action/call', function (Request $request, Response $response, array $args) use($app) {
    $number = $request->getParam('number');
   
    if (strlen($number) < 1) {
        return $response->withJson([ "error" => 1, "message" => "Empty number" ]);
    }

    $user = $app->getContainer()['auth'];
    $ext = $user->getExt();

    $result = json_decode(file_get_contents("http://localhost:5039/pbx?originate=$ext&action=$number"), 1);

    if ($result['result'] == 1) $result['error'] = 0;
    else $result['error'] = 2;

    return $response->withJson($result);

})->add('\App\Middleware\OnlyAuthUser');

$app->get('/users/list', function (Request $request, Response $response, array $args) use($app) {
    $filter = $request->getParam('filter', "");
    $start = $request->getParam('start', 0);
    $count = $request->getParam('count', 20);

    $user = new User();

    return $response->withJson([
        "data" => $user->fetchList($filter, $start, $count, 0),        
        "pos" => (int)$start,
        "total_count" => $user->fetchList($filter, $start, $count, 1)
    ]);
})->add('\App\Middleware\OnlyAuthUser');

$app->post('/users/{id}/save[/{disable_rules}]', function (Request $request, Response $response, array $args) use($app) {
    $params = $request->getParams();
    $id = intval($args['id']);
    $user = new User();
    $result = $args['disable_rules'] ? $response->withJson($user->addUpdate($params, true)) : $response->withJson($user->addUpdate($params));
    return $result;
});
  
$app->post('/users/{id}/remove', function (Request $request, Response $response, array $args) use($app) {
    $id = intval($args['id']);
    $user = new User();
    
    return $response->withJson($user->remove($id));
  });

$app->get('/users/user_groups', function (Request $request, Response $response, array $args) use($app) {
    $user = new User();
    return $response->withJson($user->fetchGroups());
})->add('\App\Middleware\OnlyAuthUser');

$app->post('/settings/{key}/{id}/save', function (Request $request, Response $response, array $args) use($app) {
  $id = intval($args['id']);
  $settings = new PBXSettings();
  $params = $request->getParam("settings", "");
  if (trim($args['key']) == 'user') {
    return $response->withJson(["result"=>$settings->setUserSettings($id, $params)]);
  } else if (trim($args['key']) == 'group') {
    return $response->withJson(["result"=>$settings->setGroupSettings($id, $params)]);
  }
  return $response->withJson(["result"=> FALSE]);
});

$app->get('/settings/{key}/{id}', function (Request $request, Response $response, array $args) use($app) {
  $id = intval($args['id']);
  $settings = new PBXSettings();
  if (trim($args['key']) == 'user') {
    return $response->withJson(["data"=>$settings->getUserSettings($id)]);
  } else if (trim($args['key']) == 'group') {
    return $response->withJson(["data"=>$settings->getGroupSettings($id)]);
  }
  return $response->withJson(["data"=>[]]);
});

$app->get('/settings/user/{user_id}/default/{handle}', function (Request $request, Response $response, array $args) use($app) {
  $settings = new PBXSettings();  
  if (!(int)$args['user_id']) {
    return $response
    ->withJson(['result' => false, 'message' => 'Пользователь не обнаружен'])
    ->withStatus(400);  
  }

  return $response->withJson($settings->deleteUserSettingByHandle(trim($args['handle']), (int)$args['user_id']));  
});

$app->get('/settings/default', function (Request $request, Response $response, array $args) use($app) {
  $settings = new PBXSettings();  
  
  return $response->withJson($settings->getDefaultSettings());
});

$app->post('/settings/default/save', function (Request $request, Response $response, array $args) use($app) {
  $params = $request->getParam("settings", "");
  $set = new PBXSettings();  

  if (isset($params)) {
    return $response->withJson(["result"=>$set->setDefaultSettings($params)]);
  }
  return $response->withJson(["result"=> FALSE]);
});

$app->get('/groups/list', function (Request $request, Response $response, array $args) use($app) {
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

$app->post('/groups/{id}/save', function (Request $request, Response $response, array $args) use($app) {
    $params = $request->getParams();
    $id = intval($args['id']);
    $user = new User();

    return $response->withJson($user->addUpdateGroup($params));
});

$app->post('/groups/{id}/remove', function (Request $request, Response $response, array $args) use($app) {
  $params = $request->getParams();
  $id = intval($args['id']);
  $user = new User();
  
  return $response->withJson($user->removeGroup($id));
})->add('\App\Middleware\OnlyAuthUser');

$app->get('/groups/users/short', function (Request $request, Response $response, array $args) use($app) {
    $user = new User();    
    $filter = $request->getParam('filter', "");
    $nameAsValue = intval($request->getParam('nameasvalue', 0));
    
    $count = $user->fetchList($filter, 0, 0, 1);
    return $response->withJson( $user->fetchList($filter, 0, $count, 0, 1, $nameAsValue)
    );
})->add('\App\Middleware\OnlyAuthUser');

$app->get('/contact_groups/list', function (Request $request, Response $response, array $args) use($app) {
  $contact_groups = new PBXContactGroups();

  $filter = $request->getParam('filter', "");
  $start = $request->getParam('start', 0);
  $count = $request->getParam('count', 20);

  return $response->withJson([
      "data" => $contact_groups->fetchList($filter, $start, $count, 0),
      "total_count" => $contact_groups->fetchList($filter, $start, $count, 1),
      "pos" => $start
  ]);
})->add(new SecureRouteMiddleware($app->getContainer()->get('roleProvider')))->add(new SetRoles(['phc.admin','erpico.admin']));

$app->post('/contact_groups/{id}/save', function (Request $request, Response $response, array $args) use($app) {
  $id = intval($args["id"]);  
  $contact_groups = new PBXContactGroups($id);

  $name = $request->getParam("name", "");    
  $queues = $request->getParam("queues", "");
  $items_users = $request->getParam("items_users", "");
  $items_queues = $request->getParam("items_queues", "");

  return $response->withJson($contact_groups->save($name, $queues, $items_users, $items_queues));
})->add(new SecureRouteMiddleware($app->getContainer()->get('roleProvider')))->add(new SetRoles(['phc.admin','erpico.admin']));
  
$app->post('/contact_groups/{id}/remove', function (Request $request, Response $response, array $args) use($app) {
  $id = intval($args["id"]);
  $contact_groups = new PBXContactGroups($id);
  
  return $response->withJson($contact_groups->remove($id));
})->add(new SecureRouteMiddleware($app->getContainer()->get('roleProvider')))->add(new SetRoles(['phc.admin','erpico.admin']));

$app->post('/contact_groups/{id}', function (Request $request, Response $response, array $args) use($app) {
  $id = intval($args["id"]);  
  $contact_groups = new PBXContactGroups($id);

  return $response->withJson($contact_groups->getFullInfo());
})->add(new SecureRouteMiddleware($app->getContainer()->get('roleProvider')))->add(new SetRoles(['phc.admin','erpico.admin']));

$app->get('/auth/info', function (Request $request, Response $response, array $args) use($app) {    
    return $response->withJson($app->getContainer()['auth']->getInfo());
})->add('\App\Middleware\OnlyAuthUser');

$app->get('/auth/logout', function (Request $request, Response $response, array $args) use($app) {    
    return $response->withJson([ "error" => !$app->getContainer()['auth']->logout() ]);
})->add('\App\Middleware\OnlyAuthUser');

$app->get('/auth/settings', function (Request $request, Response $response, array $args) use($app) { 
  return $response->withJson(["data"=>$app->getContainer()['auth']->getAuthUserSettings()]);   
})->add('\App\Middleware\OnlyAuthUser');

$app->get('/phones/list', function (Request $request, Response $response, array $args) use($app) {
    $phone = new PBXPhone();

    $filter = $request->getParam('filter', "");
    $start = $request->getParam('start', 0);
    $count = $request->getParam('count', 20);
    $sort = $request->getParam('sort', "");

    return $response->withJson([
        "data" => $phone->fetchList($filter, $start, $count, 0,true, $sort),
        "total_count" => $phone->fetchList($filter, $start, $count, 1),
        "pos" => $start
    ]);
})->add('\App\Middleware\OnlyAuthUser');

$app->get('/phones/list/short', function (Request $request, Response $response, array $args) use($app) {
  $phone = new PBXPhone();

  $filter = $request->getParam('filter', "");

  return $response->withJson($phone->fetchList($filter));
})->add('\App\Middleware\OnlyAuthUser');

$app->post('/phones/{phone_id}/save', function (Request $request, Response $response, array $args) use($app) {
    $phone = new PBXPhone();

    $values = $request->getParams();
    $res = $phone->addUpdate($values);
    return $response->withJson($res);
})->add('\App\Middleware\OnlyAuthUser');
  
  $app->post('/phones/{phone_id}/remove', function (Request $request, Response $response, array $args) use($app) {
    $phone = new PBXPhone();
  
    $id = intval($args["phone_id"]);
    $res = $phone ->remove($id);
    return $response->withJson($res);
  })->add('\App\Middleware\OnlyAuthUser');

$app->get('/queues/list', function (Request $request, Response $response, array $args) use($app) {
    $queue = new PBXQueue();
    $filter = $request->getParam('filter', "");
    $start = $request->getParam('start', 0);
    $count = $request->getParam('count', 20);

    return $response->withJson([
        "data" => $queue->fetchList($filter, $start, $count, 0),
        "total_count" => $queue->fetchList($filter, $start, $count, 1),
        "pos" => $start
    ]);
})->add(new SecureRouteMiddleware($app->getContainer()->get('roleProvider')))->add(new SetRoles(['phc.admin','erpico.admin']));

$app->get('/queues/list/short', function (Request $request, Response $response, array $args) use($app) {
  $queue = new PBXQueue();
  $count = $queue->fetchList("", 0, 0, 1);
  $nameAsValue = intval($request->getParam('nameasvalue', 0));

  return $response->withJson($queue->fetchList("", 0, $count, 0,$nameAsValue));
})->add('\App\Middleware\OnlyAuthUser');

$app->post('/queues/{queues_id}/save', function (Request $request, Response $response, array $args) use($app) {
    $queue = new PBXQueue();

    $values = $request->getParams();
    $res = $queue->addUpdate($values);
    
    return $response->withJson($res);
})->add(new SecureRouteMiddleware($app->getContainer()->get('roleProvider')))->add(new SetRoles(['phc.admin','erpico.admin']));

$app->post('/queues/{queues_id}/remove', function (Request $request, Response $response, array $args) use($app) {
  $queue = new PBXQueue();
  
  $id = intval($args["queues_id"]);
  $res = $queue->remove($id);
  
  return $response->withJson($res);
})->add(new SecureRouteMiddleware($app->getContainer()->get('roleProvider')))->add(new SetRoles(['phc.admin','erpico.admin']));

$app->get('/queues/code', function (Request $request, Response $response, array $args) use($app) {
  $queue = new PBXQueue();

  $name = $request->getParam("name");
  $res = $queue->getCode($name);
  
  return $response->withJson([
    "result"=> true,
    "message"=>$res
    ]);
})->add(new SecureRouteMiddleware($app->getContainer()->get('roleProvider')))->add(new SetRoles(['phc.admin','erpico.admin']));

$app->get('/channels/code', function (Request $request, Response $response, array $args) use($app) {
  $channels = new PBXChannel();

  $name = $request->getParam("name");
  $res = $channels->getCode($name);
  return $response->withJson([
    "result"=> true,
    "message"=>$res
    ]);
})->add('\App\Middleware\OnlyAuthUser');

$app->get('/channels/list', function (Request $request, Response $response, array $args) use($app) {
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

$app->post('/channels/{channels_id}/save', function (Request $request, Response $response, array $args) use($app) {
    $channels = new PBXChannel();

    $values = $request->getParams();
    $res = $channels->addUpdate($values);
    return $response->withJson($res);
})->add('\App\Middleware\OnlyAuthUser');
  
  $app->post('/channels/{channels_id}/remove', function (Request $request, Response $response, array $args) use($app) {
    $channels = new PBXChannel();
    
    $id = intval($args["channels_id"]);
    $res = $channels->remove($id);
    return $response->withJson($res);
  })->add('\App\Middleware\OnlyAuthUser');
 
$app->get('/outgoingcampaign/list', function (Request $request, Response $response, array $args) use($app) {
    $outgoingcampaign = new PBXOutgoingCampaign();

    $filter = $request->getParam('filter', "");
    $start = $request->getParam('start', 0);
    $count = $request->getParam('count', 20);

    return $response->withJson([
        "data" => $outgoingcampaign->fetchList($filter, $start, $count, 0),
        "total_count" => $outgoingcampaign->fetchList($filter, $start, $count, 1),
        "pos" => $start
    ]);
})->add(new SecureRouteMiddleware($app->getContainer()->get('roleProvider')))->add(new SetRoles(['phc.oc','erpico.admin']));

$app->get('/outgoingcampaign/{id}/queues', function (Request $request, Response $response, array $args) use($app) {
  $outgoingcampaign = new PBXOutgoingCampaign();

  return $response->withJson(
    $outgoingcampaign->getContacts(intval($args["id"]))
  );
})->add(new SecureRouteMiddleware($app->getContainer()->get('roleProvider')))->add(new SetRoles(['phc.oc','erpico.admin']));

$app->get('/outgoingcampaign/{id}/results', function (Request $request, Response $response, array $args) use($app) {
  $outgoingcampaign = new PBXOutgoingCampaign();

  return $response->withJson(
    $outgoingcampaign->getContactsResults(intval($args["id"]))
  );
})->add(new SecureRouteMiddleware($app->getContainer()->get('roleProvider')))->add(new SetRoles(['phc.oc','erpico.admin']));

$app->get('/outgoingcampaign/result/{id}/calls', function (Request $request, Response $response, array $args) use($app) {
  $outgoingcampaign = new PBXOutgoingCampaign();

  return $response->withJson($outgoingcampaign->getContactCalls(intval($args["id"])));
})->add(new SecureRouteMiddleware($app->getContainer()->get('roleProvider')))->add(new SetRoles(['phc.oc','erpico.admin']));

$app->post('/outgoingcampaign/{id}/save', function (Request $request, Response $response, array $args) use($app) {
  $outgoingcampaign = new PBXOutgoingCampaign();
  $values = $request->getParams();
  
  return $response->withJson($outgoingcampaign->addUpdate($values));
})->add(new SecureRouteMiddleware($app->getContainer()->get('roleProvider')))->add(new SetRoles(['phc.oc','erpico.admin']));

$app->get('/outgoingcampaign/{id}/state/{state}', function (Request $request, Response $response, array $args) use($app) {
  $outgoingcampaign = new PBXOutgoingCampaign();
  $id = intval($args['id']);
  $state = intval($args['state']);
  
  return $response->withJson($outgoingcampaign->setState($id, $state));
})->add(new SecureRouteMiddleware($app->getContainer()->get('roleProvider')))->add(new SetRoles(['phc.oc','erpico.admin']));

$app->get('/outgoingcampaign/{id}/settings', function (Request $request, Response $response, array $args) use($app) {
  $outgoingcampaign = new PBXOutgoingCampaign();
  $id = intval($args['id']);
  
  return $response->withJson($outgoingcampaign->getSettings($id));
})->add(new SecureRouteMiddleware($app->getContainer()->get('roleProvider')))->add(new SetRoles(['phc.oc','erpico.admin']));

$app->post('/outgoingcampaign/{id}/settings/save', function (Request $request, Response $response, array $args) use($app) {
  $outgoingcampaign = new PBXOutgoingCampaign();
  $id = intval($args['id']);
  $settings = $request->getParam("settings", 0);
  
  return $response->withJson(["result"=>$outgoingcampaign->updateSettings($id,$settings)]);
})->add(new SecureRouteMiddleware($app->getContainer()->get('roleProvider')))->add(new SetRoles(['phc.oc','erpico.admin']));

// SMS messaging

$app->get('/acd/sms', function (Request $request, Response $response, array $args) use($app) {
    return $this->renderer->render($response, 'sms.phtml', $args);
});
  
// RULES 

$app->get('/rules/list', function (Request $request, Response $response, array $args) use($app) {
  $rule = new PBXRules();

  return $response->withJson($rule->fetchList());
})->add('\App\Middleware\OnlyAuthUser');

$app->post('/rules/groups/{id}/save', function (Request $request, Response $response, array $args) use($app) {
  $rule = new PBXRules();
  $rules = $request->getParam("rules", "");

  $res = $rule->saveGroup($rules, intval($args['id']));
  return $response->withJson(["result" => $res]);
  
})->add(new SecureRouteMiddleware($app->getContainer()->get('roleProvider')))->add(new SetRoles(['erpico.admin']));

$app->post('/rules/users/{id}/save', function (Request $request, Response $response, array $args) use($app) {
  $rule = new PBXRules();
  $rules = $request->getParam("rules", "");

  $res = $rule->saveUser($rules, intval($args['id']));
  
  return $response->withJson(["result" => $res]);
})->add(new SecureRouteMiddleware($app->getContainer()->get('roleProvider')))->add(new SetRoles(['erpico.admin']));


$app->get('/phones/groups/list', function (Request $request, Response $response, array $args) use($app) {
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

$app->get('/phones/groups/code', function (Request $request, Response $response, array $args) use($app) {
  $phone = new PBXPhone();

  $name = $request->getParam("name");
  $res = $phone->getGroupCode($name);
  return $response->withJson([
    "result"=> true,
    "message"=>$res
    ]);
})->add(new SecureRouteMiddleware($app->getContainer()->get('roleProvider')))->add(new SetRoles(['erpico.admin']));

$app->get('/phones/groups/list/short', function (Request $request, Response $response, array $args) use($app) {
  $phone = new PBXPhone();

  $filter = $request->getParam('filter', "");

  return $response->withJson($phone->fetchGroupsList($filter));
})->add(new SecureRouteMiddleware($app->getContainer()->get('roleProvider')))->add(new SetRoles(['erpico.admin']));

$app->post('/phones/groups/{id}/save', function (Request $request, Response $response, array $args) use($app) {
  $id = intval($args["id"]);  
  $phone = new PBXPhone();
  $params = $request->getParams();

  return $response->withJson($phone->addUpdatePhoneGroup($params));
})->add(new SecureRouteMiddleware($app->getContainer()->get('roleProvider')))->add(new SetRoles(['erpico.admin']));
  
$app->post('/phones/groups/{id}/remove', function (Request $request, Response $response, array $args) use($app) {
  $id = intval($args["id"]);
  $phone = new PBXPhone();
  
  return $response->withJson($phone->removePhoneFroup($id));
})->add(new SecureRouteMiddleware($app->getContainer()->get('roleProvider')))->add(new SetRoles(['erpico.admin']));

$app->any('/config/sip', function (Request $request, Response $response, array $args) use($app) {
  $helper = new PBXConfigHelper();
  
  return $response->withJson($helper->getOptions(PBXConfigHelper::SIP_FILE));
})->add(new SecureRouteMiddleware($app->getContainer()->get('roleProvider')))->add(new SetRoles(['phc.admin', 'erpico.admin']));

$app->any('/config/queues', function (Request $request, Response $response, array $args) use($app) {
  $helper = new PBXConfigHelper();

  return $response->withJson($helper->getOptions(PBXConfigHelper::QUEUES_FILE));
})->add(new SecureRouteMiddleware($app->getContainer()->get('roleProvider')))->add(new SetRoles(['phc.admin', 'erpico.admin']));

$app->any('/config/extensions', function (Request $request, Response $response, array $args) use($app) {
  $helper = new PBXConfigHelper();

  return $response->withJson($helper->getOptions(PBXConfigHelper::RULES_FILE));
})->add(new SecureRouteMiddleware($app->getContainer()->get('roleProvider')))->add(new SetRoles(['phc.admin', 'erpico.admin']));

$app->get('/extended_calls/list', function (Request $request, Response $response, array $args) use($app) {
  $oldCdr = new PBXOldCdr();
  $filter = $request->getParam("filter", []);

  return $response->withJson($oldCdr->fetchList($filter));
})->add(new SecureRouteMiddleware($app->getContainer()->get('roleProvider')))->add(new SetRoles(['phc.reports','erpico.admin']));

$app->get('/extended_calls/list/trafic', function (Request $request, Response $response, array $args) use($app) {
  $oldCdr = new PBXOldCdr();
  $filter = $request->getParam("filter", []);

  return $response->withJson($oldCdr->getTrafic($filter));
})->add(new SecureRouteMiddleware($app->getContainer()->get('roleProvider')))->add(new SetRoles(['phc.reports','erpico.admin']));

$app->get('/extended_contact_calls/list', function (Request $request, Response $response, array $args) use($app) {
  $oldContactCdr = new PBXOldContactCdr();
  $filter = $request->getParam("filter", []);

  return $response->withJson($oldContactCdr->fetchList($filter));
})->add(new SecureRouteMiddleware($app->getContainer()->get('roleProvider')))->add(new SetRoles(['phc.reports','erpico.admin']));

$app->any('/config/phone/{mac}', function (Request $request, Response $response, array $args) use($app) {
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

  // Search for phone with such MAC
  $po = new PBXPhone();
  $list = $po->fetchList(["mac" => $mac]);
  if (count($list) == 0) {
    return $response->withStatus(404)
          ->withHeader('Content-Type', 'text/plain')
          ->write('No configuration file for this phone');
  }

  $data = $list[0];

  $data['server'] = $container['server_host'];

  $template = file_get_contents(__DIR__."/../templates/phones/yealink-t.tpl");

  foreach ($data as $k => $v) {
    $template = str_replace("#$k#", $v, $template);
  }

  return $response->withStatus(200)
          ->withHeader('Content-Type', 'text/plain')
          ->write($template);
  
});//->add(new SecureRouteMiddleware($app->getContainer()->get('roleProvider')))->add(new SetRoles(['erpico.admin']));

$app->any('/config/contacts', function (Request $request, Response $response, array $args) use($app) {

  $user = new User();
  $users = $user->fetchList([ "state" => 1], 0, 1000, 0);

  $result = '<YeastarIPPhoneDirectory>'."\n";

  foreach ($users as $e) {
    if (!strlen($e['phone'])) continue;
    $result .= '<DirectoryEntry><Name>'.$e['fullname'].'</Name><Telephone>'.$e['phone'].'</Telephone></DirectoryEntry>'."\n";        
  }

  $result .= "</YeastarIPPhoneDirectory>";

  $response->getBody()->write($result);
  return $response->withStatus(200)
          ->withHeader('Content-Type', 'text/xml');          

});

$app->post('/phones/provisioning/start', function (Request $request, Response $response, array $args) use($app) {
  $data = $request->getParams();

  $ip_phone  = $data['ip'];
  $mac       =  $data['mac'];
  $port_phone= 5060;
  $ip_pbx    = "192.168.139.210";
  $port_pbx  = "5060";

  $phone_user = 'autoprovision_user';
  $sock = socket_create(AF_INET, SOCK_DGRAM, SOL_UDP);

  $url = "http://tns-e-main.clients.tlc.local/config/phone/80:5E:C0:18:09:E9";//http://$ip_pbx/config/phone/$mac";

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

  $msg =  "NOTIFY sip:{$phone_user}@{$ip_phone}:{$port_phone};ob SIP/2.0\r\n".
      "Via: SIP/2.0/UDP {$ip_pbx}:{$port_pbx};branch=z9hG4bK12fd4e5c;rport\r\n".
      "Max-Forwards: 70\r\n".
      "From: \"asterisk\" <sip:asterisk@{$ip_pbx}>;tag=as54cd2be9\r\n".
      "To: <sip:{$phone_user}@{$ip_phone}:{$port_phone};ob>\r\n".
      "Contact: <sip:asterisk@{$ip_pbx}:{$port_pbx}>\r\n".
      "Call-ID: 4afab6ce2bff0be11a4af41064340242@{$ip_pbx}:{$port_pbx}\r\n".
      //"CSeq: 102 NOTIFY\r\n".
      "User-Agent: mikopbx\r\n".
      "Allow: INVITE, ACK, CANCEL, OPTIONS, BYE, REFER, SUBSCRIBE, NOTIFY, INFO, PUBLISH, MESSAGE\r\n".
      "Supported: replaces, timer\r\n".
      "Subscription-State: terminated\r\n".
      "Event: check-sync;reboot=true\r\n".
      "Content-Length: 0\r\n\n";

  $len = strlen($msg);
  socket_sendto($sock, $msg, $len, 0, $ip_phone, $port_phone);
  socket_close($sock);

  return $response->withStatus(200)
          ->withHeader('Content-Type', 'text/plain')
          ->write("Something happen: $url");
  
})->add(new SecureRouteMiddleware($app->getContainer()->get('roleProvider')))->add(new SetRoles(['erpico.admin']));

$app->get('/aliases/list', function (Request $request, Response $response, array $args) use($app) {
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

$app->get('/aliases/type/short', function (Request $request, Response $response, array $args) use($app) {
  $aliases = new PBXAliases();    
  
  return $response->withJson($aliases->getType());
})->add(new SecureRouteMiddleware($app->getContainer()->get('roleProvider')))->add(new SetRoles(['erpico.admin']));

$app->post('/aliases/{alias_id}/save', function (Request $request, Response $response, array $args) use($app) {
  $aliases = new PBXAliases();

  $values = $request->getParams();
  $res = $aliases->addUpdate($values);
  return $response->withJson($res);
})->add(new SecureRouteMiddleware($app->getContainer()->get('roleProvider')))->add(new SetRoles(['erpico.admin']));

$app->post('/aliases/{alias_id}/remove', function (Request $request, Response $response, array $args) use($app) {
  $aliases = new PBXAliases();
  $id = intval($args["alias_id"]);
  $res = $aliases->remove($id);

  return $response->withJson($res);
})->add(new SecureRouteMiddleware($app->getContainer()->get('roleProvider')))->add(new SetRoles(['erpico.admin']));

$app->any('/user/exten/save', function (Request $request, Response $response, array $args) use($app) {
  $user = $app->getContainer()['auth'];

  $ext = $request->getParam('ext', '');
  if (strlen($ext) == 0) {
    return $response->withJson([ "error" => 1, "message" => "No extension provided" ]);
  }

  $result = $user->saveExt($ext);

  return $response->withJson( [ "error" => $result ]);
})->add(new SecureRouteMiddleware($app->getContainer()->get('roleProvider')))->add(new SetRoles(['erpico.admin']));

$app->get('/blacklist', function (Request $request, Response $response, array $args) use($app){

    if (!($filter = $request->getParam('filter', ""))){
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

$app->post('/blacklist/add', function (Request $request, Response $response, array $args) use($app){
    $blacklist = new PBXBlacklist($app->getContainer());
    $result = $blacklist->saveBlacklistItem($request->getParams());

    return $response->withJson([
        "result" => $result,
        "message" => $result ? "Сохранение прошло успешно!" : "Ошибка сохранения"
    ]);
})->add('\App\Middleware\OnlyAuthUser')->add(new SetRoles(['erpico.admin']));

$app->post('/blacklist/{id}/update', function (Request $request, Response $response, array $args) use($app){
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

$app->post('/blacklist/{id}/remove', function (Request $request, Response $response, array $args) use($app){
    $id = intval($args["id"]);
    $blacklist = new PBXBlacklist($app->getContainer());
    $result = $blacklist->remove($id);

    return $response->withJson([
        "result" => $result,
        "message" => $result ? "Удаление прошло успешно!" : "Ошибка удаления"
    ]);
})->add('\App\Middleware\OnlyAuthUser')->add(new SetRoles(['erpico.admin']));

$app->get('/[{name}]', function (Request $request, Response $response, array $args) {
  // Sample log message
  $this->logger->info("Loading WebApp");

  // Render index view
  return $this->renderer->render($response, 'index.phtml', $args);
});