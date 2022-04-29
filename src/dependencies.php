<?php
// DIC configuration
  
use App\Middleware\OnlyAdmin;
use App\Services\RequestTypeService;
use PAMI\Client\Impl\ClientImpl;

$container = $app->getContainer();

// Replace to localhost on $_SERVER
if (in_array(getenv('env'), ['dev','test'])) {
  set_error_handler(function ($severity, $message, $file, $line) {
    if (error_reporting() & $severity) {
      throw new \ErrorException($message, 0, $severity, $file, $line);
    }
  });
}

// view renderer
$container['renderer'] = function ($c) {
    $settings = $c->get('settings')['renderer'];
    return new Slim\Views\PhpRenderer($settings['template_path']);
};

// monolog
$container['logger'] = function ($c) {
    $settings = $c->get('settings')['logger'];
    $logger = new Monolog\Logger($settings['name']);
    $logger->pushProcessor(new Monolog\Processor\UidProcessor());
    $logger->pushHandler(new Monolog\Handler\StreamHandler($settings['path'], $settings['level']));
    return $logger;
};

// pdo
$container['server_host'] = function ($c) {
    return $c->get('settings')['server_host'];
};
$container['errorHandler'] = function ($c) {
  return function ($request, $response, $exception) use ($c) {
    $c['logger']->error('Exception: ' . $exception->getMessage() . " ON " . $exception->getFile() . ":" . $exception->getLine() . " Trace: " . $exception->getTraceAsString());
    return $response->withStatus(500)->withJson([
     'result' => false, 'message' => $exception->getMessage()
   ]);
  };
};
$container['phpErrorHandler'] = function ($c) {
  return function ($request, $response, $exception) use ($c) {
    $c['logger']->error('Exception: ' . $exception->getMessage() . " ON " . $exception->getFile() . ":" . $exception->getLine() . " Trace: " . $exception->getTraceAsString());
    return $response->withStatus(500)->withJson([
     'result' => false, 'message' => $exception->getMessage()
     ]);
  };
};
$container['notAllowedHandler'] = function ($c) {
  return function ($request, $response) use ($c) {
    return $response->withStatus(500)->withJson([
      'result' => false, 'message' => 'Неверный тип запроса '.$request->getMethod()
    ]);
  };
};

$container['dbConfig'] = function ($c) {
  $filename = "/etc/erpico.conf";
  if (file_exists($filename)) {
    $fcfg = fopen($filename, "r");
    $config = [];
    while ($s = fgets($fcfg)) {
      if ($s == "\n") continue;
      list($key, $value) = explode("=", $s, 2);
      $key = trim($key);
      $value = trim($value, " \"\t\n\r\0\x0B");
      $config[$key] = $value;
    };
    fclose($fcfg);
  }
  return $config;
};

$container['db'] = function ($c)  use ($container, $app) {
  $db = $c->get('settings')['db'];
  if ($container['dbConfig']) {
    $db['host'] = $container['dbConfig']['db_host'];
    $db['user'] = $container['dbConfig']['db_user'];
    $db['password'] = $container['dbConfig']['db_password'];
    $db['schema'] = $container['dbConfig']['db_schema'];
    $container['instance_id'] = $container['dbConfig']['vpn_name'];
  }
  try {
    $pdo = new PDO("mysql:host=" . $db['host'] . ";dbname=" . $db['schema'].";charset=UTF8", $db['user'], $db['password']);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
    $pdo->query("SET sql_mode=(SELECT REPLACE(@@sql_mode,'ONLY_FULL_GROUP_BY,',''))");    
    return $pdo;
  } catch (PDOException $exception) {
    return 0;
  }  
};

$container['ami'] = function ($c)  use ($container, $app) {
  $options = array(    
    'scheme' => 'tcp://',    
    'connect_timeout' => 10,
    'read_timeout' => 100
  );
  $filename = "/etc/erpico.conf";
  if (file_exists($filename)) {
    $fcfg = fopen($filename, "r");
    $config = [];
    while ($s = fgets($fcfg)) {
      if ($s == "\n") continue;
      list($key,$value) = explode("=", $s, 2);
      $key = trim($key);
      $value = trim($value, " \"\t\n\r\0\x0B");
      $config[$key] = $value;
    };
    fclose($fcfg);
    $options['username'] = $config['ami_user'];
    $options['secret'] = $config['ami_secret'];
    $options['port'] = $config['ami_port'];
    $options['host'] = $config['ami_server'];
  } else {
    return 0;
  }
  $ami = new \PAMI\Client\Impl\ClientImpl($options);  
  return $ami;
};

$container['auth'] = function ($c) use ($app){
    return new \Erpico\User($app->getContainer()['db']);
};
$container['roleProvider'] = function ($container) {
  $myService = new RoleProvider($container);
  
  return $myService;
};
$container['onlyAdmin'] = function ($container) {
    $myService = new OnlyAdmin($container, $container->get('roleProvider'));
    
    return $myService;
};
$container[RequestTypeService::class] = function ($container) {
  $requestTypeService = new RequestTypeService();
  
  return $requestTypeService;
};
$container[\App\Chat\ChatMessageRepository::class] = function ($container) {
    return new \App\Chat\MySQLChatMessageRepository($container['db']);
};

require_once( __DIR__ . "/legacy/utils.php");
require_once( __DIR__ . "/user.php");
require_once( __DIR__ . "/legacy/phones.php");
require_once( __DIR__ . "/legacy/cdr_report.php");
//require_once( __DIR__ . "/legacy/call_recording_2.php");
require_once( __DIR__ . "/legacy/call_recording_3.php");
require_once( __DIR__ . "/legacy/contact_cdr_report.php");
require_once( __DIR__ . "/legacy/record_contact_center.php");
require_once( __DIR__ . "/legacy/grouped_reports.php");
require_once( __DIR__ . "/legacy/lost_calls.php");
require_once( __DIR__ . "/legacy/analysis_outgoing_calls.php");
require_once( __DIR__ . "/legacy/operators_work_report.php");
require_once( __DIR__ . "/legacy/interval_reports.php");
require_once( __DIR__ . "/legacy/daily_report.php");
require_once( __DIR__ . "/legacy/month_traffic.php");
require_once( __DIR__ . "/legacy/hourly_load.php");
require_once( __DIR__ . "/legacy/compare_calls.php");
require_once( __DIR__ . "/legacy/ext_incoming_external.php");
require_once( __DIR__ . "/legacy/ext_incoming_internal.php");
require_once( __DIR__ . "/legacy/ext_outgoing.php");
require_once( __DIR__ . "/legacy/ext_dashboard.php");
require_once( __DIR__ . "/legacy/ext_checklist.php");
require_once( __DIR__ . "/legacy/ext_scripts.php");
require_once( __DIR__ . "/legacy/users.php");
require_once( __DIR__ . "/legacy/nps.php");
require_once( __DIR__ . "/legacy/script.php");
require_once( __DIR__ . "/cdr.php");
require_once( __DIR__ . "/phone.php");
require_once( __DIR__ . "/queue.php");
require_once( __DIR__ . "/channels.php");
require_once( __DIR__ . "/outgoingcampaign.php");
require_once( __DIR__ . "/contact_groups.php");
require_once( __DIR__ . "/rules.php");
require_once( __DIR__ . "/helpers/config_helper.php");
require_once( __DIR__ . "/Translator.php");
require_once( __DIR__ . "/pbx_settings.php");
require_once( __DIR__ . "/old_cdr.php");
require_once( __DIR__ . "/old_contact_cdr.php");
require_once( __DIR__ . "/Providers/RoleProvider.php");
require_once( __DIR__ . "/aliases.php");
require_once( __DIR__ . "/blacklist.php");