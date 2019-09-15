<?php

use Slim\Http\Request;
use Slim\Http\Response;
use Erpico\User;
use Erpico\PBXRules;
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

$app->get('/controllers/findrecord.php', function (Request $request, Response $response, array $args) use($app) {
  $id = $request->getParam('id', 0);
  return $response->withRedirect("/recording/$id"); 
});

$app->get('/recording/{id}', function (Request $request, Response $response, array $args) use($app) {
    $uid = $args['id'];
    $uid = str_replace(".mp3", "", $uid);
    $uid = str_replace(".", "", $uid);

    $cdr = new PBXCdr();
    $row = $cdr->findById($uid);
    if (!is_array($row)) {
        return $response->withStatus(404)
            ->withHeader('Content-Type', 'text/html')
            ->write('Record not found in database');
    }
    
    if (isset($row['agentname'])) {
        // Queue
        $date = str_replace(" ", "-", $row['calldate']);
    
        $agent = $row['agentname'];
        
        $uniqid = $row['uniqid'];
        $cid = $row['src'];
    
        $fname = "$date-$cid-$agent-q-$uniqid.wav";
        $path_parts = pathinfo($fname);    
    
        $filename = "/var/spool/asterisk/monitor/queues/".substr($fname,0,10)."/".substr($fname,11,2)."/".$path_parts['dirname'].'/'.$path_parts['filename'];
    } else {
        // Regular
        $date = str_replace(" ", "-", $row['calldate']);
        $time = strtotime($row['calldate']);    
        $uniqid = substr($row['uniqueid'], 0, -2);
        $src = $row['src'];
        $dst = $row['dst'];
    
        $files = glob("/var/spool/asterisk/monitor/".date('Y-m-d', $time)."/".date('H',$time)."/*$src*-".$uniqid."*");    
        if (!is_array($files) || !count($files)) {        
            return $response->withStatus(404)
                ->withHeader('Content-Type', 'text/html')
                ->write('Record not found in filesystem');
        }
        $filename = $files[0];    
    }
    
    if(file_exists($filename.".WAV")) {
        $filename = $filename.".WAV";
    }
    else if(file_exists($filename.".wav")) {
        $filename = $filename.".wav";
    }
    else if(file_exists($filename.".mp3")) {
        $filename = $filename.".mp3";
        $filenameB = "$date-$cid-$agent-q-$uniqid.mp3";
    } else if (file_exists($filename)) {
    } else {
        return $response->withStatus(404)
            ->withHeader('Content-Type', 'text/html')
            ->write('Record not found in filesystem');
      
    }  
    $fh = fopen($filename, 'rb');
    $stream = new Slim\Http\Stream($fh);
    return $response            
            ->withBody($stream)
            ->withHeader('Content-Type', 'audio/mpeg')
            ->withHeader('Accept-Ranges', 'bytes')
            ->withHeader('Content-Length', filesize($filename))
            ->withHeader('Content-Transfer-Encoding', 'binary')
            ->withHeader('Content-Disposition', 'attachment; filename="' . basename($filename) . '"');
})->setOutputBuffering(false);//->add('\App\Middleware\OnlyAuthUser');


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
        "total_count" => $user->fetchList($filter, $start, $count, 1),
        "pos" => $start
    ]);
})->add('\App\Middleware\OnlyAuthUser');

$app->post('/users/{id}/save', function (Request $request, Response $response, array $args) use($app) {
    $params = $request->getParams();
    $id = intval($args['id']);
    $user = new User();

    return $response->withJson($user->addUpdate($params));
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

$app->get('/groups/users/short', function (Request $request, Response $response, array $args) use($app) {
    $user = new User();    
    $count = $user->fetchList("", 0, 0, 1);
    return $response->withJson( $user->fetchList("", 0, $count, 0, 1)
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
})->add('\App\Middleware\OnlyAuthUser');

$app->post('/contact_groups/{id}/save', function (Request $request, Response $response, array $args) use($app) {
  $id = intval($args["id"]);  
  $contact_groups = new PBXContactGroups($id);

  $name = $request->getParam("name", "");    
  $queues = $request->getParam("queues", "");
  $items_users = $request->getParam("items_users", "");
  $items_queues = $request->getParam("items_queues", "");

  return $response->withJson($contact_groups->save($name, $queues, $items_users, $items_queues));
})->add('\App\Middleware\OnlyAuthUser');

$app->post('/contact_groups/{id}', function (Request $request, Response $response, array $args) use($app) {
  $id = intval($args["id"]);  
  $contact_groups = new PBXContactGroups($id);

  return $response->withJson($contact_groups->getFullInfo());
})->add('\App\Middleware\OnlyAuthUser');

$app->get('/auth/info', function (Request $request, Response $response, array $args) use($app) {    
    return $response->withJson($app->getContainer()['auth']->getInfo());
})->add('\App\Middleware\OnlyAuthUser');

$app->get('/auth/logout', function (Request $request, Response $response, array $args) use($app) {    
    return $response->withJson([ "error" => !$app->getContainer()['auth']->logout() ]);
})->add('\App\Middleware\OnlyAuthUser');

$app->get('/[{name}]', function (Request $request, Response $response, array $args) {
    // Sample log message
    $this->logger->info("Loading WebApp");

    // Render index view
    return $this->renderer->render($response, 'index.phtml', $args);
});

$app->get('/phones/list', function (Request $request, Response $response, array $args) use($app) {
    $phone = new PBXPhone();

    $filter = $request->getParam('filter', "");
    $start = $request->getParam('start', 0);
    $count = $request->getParam('count', 20);

    return $response->withJson([
        "data" => $phone->fetchList($filter, $start, $count, 0),
        "total_count" => $phone->fetchList($filter, $start, $count, 1),
        "pos" => $start
    ]);
})->add('\App\Middleware\OnlyAuthUser');

$app->post('/phones/{phone_id}/save', function (Request $request, Response $response, array $args) use($app) {
    $phone = new PBXPhone();

    $values = $request->getParams();
    $res = $phone->addUpdate($values);
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
})->add('\App\Middleware\OnlyAuthUser');

$app->get('/queues/list/short', function (Request $request, Response $response, array $args) use($app) {
  $queue = new PBXQueue();
  $count = $queue->fetchList("", 0, 0, 1);

  return $response->withJson($queue->fetchList("", 0, $count, 0));
})->add('\App\Middleware\OnlyAuthUser');

$app->post('/queues/{queues_id}/save', function (Request $request, Response $response, array $args) use($app) {
    $queue = new PBXQueue();

    $values = $request->getParams();
    $res = $queue->addUpdate($values);
    return $response->withJson($res);
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
})->add('\App\Middleware\OnlyAuthUser');

$app->get('/outgoingcampaign/{id}/queues', function (Request $request, Response $response, array $args) use($app) {
  $outgoingcampaign = new PBXOutgoingCampaign();

  return $response->withJson(
    $outgoingcampaign->getContacts(intval($args["id"]))
  );
})->add('\App\Middleware\OnlyAuthUser');

$app->get('/outgoingcampaign/{id}/results', function (Request $request, Response $response, array $args) use($app) {
  $outgoingcampaign = new PBXOutgoingCampaign();

  return $response->withJson(
    $outgoingcampaign->getContactsResults(intval($args["id"]))
  );
})->add('\App\Middleware\OnlyAuthUser');

$app->get('/outgoingcampaign/result/{id}/calls', function (Request $request, Response $response, array $args) use($app) {
  $outgoingcampaign = new PBXOutgoingCampaign();

  return $response->withJson(
    $outgoingcampaign->getContactCalls(intval($args["id"]))
  );
})->add('\App\Middleware\OnlyAuthUser');

$app->post('/outgoingcampaign/{id}/save', function (Request $request, Response $response, array $args) use($app) {
  $outgoingcampaign = new PBXOutgoingCampaign();
  
  $values = $request->getParams();
  return $response->withJson($outgoingcampaign->addUpdate($values));
})->add('\App\Middleware\OnlyAuthUser');

$app->get('/outgoingcampaign/{id}/state/{state}', function (Request $request, Response $response, array $args) use($app) {
  $outgoingcampaign = new PBXOutgoingCampaign();
  
  $id = intval($args['id']);
  $state = intval($args['state']);
  return $response->withJson($outgoingcampaign->setState($id, $state));
})->add('\App\Middleware\OnlyAuthUser');

$app->get('/outgoingcampaign/{id}/settings', function (Request $request, Response $response, array $args) use($app) {
  $outgoingcampaign = new PBXOutgoingCampaign();
  
  $id = intval($args['id']);
  return $response->withJson($outgoingcampaign->getSettings($id));
})->add('\App\Middleware\OnlyAuthUser');

$app->post('/outgoingcampaign/{id}/settings/save', function (Request $request, Response $response, array $args) use($app) {
  $outgoingcampaign = new PBXOutgoingCampaign();
  
  $id = intval($args['id']);
  $settings = $request->getParam("settings", 0);
  return $response->withJson(["result"=>$outgoingcampaign->updateSettings($id,$settings)]);
})->add('\App\Middleware\OnlyAuthUser');

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
})->add('\App\Middleware\OnlyAuthUser');

$app->post('/rules/users/{id}/save', function (Request $request, Response $response, array $args) use($app) {
  $rule = new PBXRules();
  $rules = $request->getParam("rules", "");

  $res = $rule->saveUser($rules, intval($args['id']));
  return $response->withJson(["result" => $res]);
})->add('\App\Middleware\OnlyAuthUser');
