<?php
// DIC configuration

$container = $app->getContainer();

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
$container['db'] = function ($c) {
    $db = $c->get('settings')['db'];
    $filename = "/etc/erpico.conf";
    if (file_exists($filename)) {
        $fcfg = fopen($filename, "r");
        $config = [];
		while ($s = fgets($fcfg)) {
			list($key,$value) = explode("=", $s, 2);
			$key = trim($key);
			$value = trim($value, " \"\t\n\r\0\x0B");
			$config[$key] = $value;
		};
  fclose($fcfg);
      $db['host'] = $config['db_host'];
      $db['user'] = $config['db_user'];
      $db['password'] = $config['db_password'];
      $db['schema'] = $config['db_schema'];
  }
    $pdo = new PDO("mysql:host=" . $db['host'] . ";dbname=" . $db['schema'].";charset=UTF8",
        $db['user'], $db['password']);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
    $pdo->query("SET sql_mode=(SELECT REPLACE(@@sql_mode,'ONLY_FULL_GROUP_BY,',''))");
    return $pdo;
};

$container['auth'] = function ($c) use ($app){
    return new \Erpico\User($app->getContainer()['db']);
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
require_once( __DIR__ . "/pbx_settings.php");

if (!isset($PBXUser)){
  $PBXUser = new \Erpico\User();
}