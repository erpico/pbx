#!/usr/bin/php
<?php

require __DIR__ . '/vendor/autoload.php';
session_start();

// Instantiate the app
$settings = require __DIR__ . '/src/settings.php';
$app = new \Slim\App($settings);

// Set up dependencies
require __DIR__ . '/src/dependencies.php';

require_once __DIR__."/src/Bitrix24/CMBitrix.php";
require_once __DIR__."/src/Bitrix24/EBitrix.php";

if (!$container['db'] instanceof \PDO) {
    printf("No database connection");
    exit(2);
}

$db = $container['db'];

$helper = new CMBitrix('');

function importLeads($filters, $next, $helper): array
{
    $leadsFromBitrix = [];
    $result = $helper->getLeadsByFilters($filters, $next, 1);
    if(isset($result['result']) && $result['result']) {
        foreach ($result['result'] as $lead) {
            $leadsFromBitrix[] = $lead;
        }
    }

    /*if (isset($result['next'])) {
        $result = importLeads($filters, $result['next'], $helper);
        if ($result) {
            foreach ($result as $lead) {
                $leadsFromBitrix[] = $lead;
            }
        }
    }*/

    return $leadsFromBitrix;
}

if (!isset($argv[1])) {
    print "Usage:\n cron.php [leads|calls]\n";
    exit(1);
}

$action = $argv[1];

$lockfile = __DIR__."/logs/cron_{$action}.lock";
if (file_exists($lockfile)) {
  $data = filemtime($lockfile);
  $last_touch = date('Y-m-d H:i:s', $data);
  $now = date('Y-m-d H:i:s');
  if (strtotime($now) - strtotime($last_touch) <= 3600) exit(0);
}
touch($lockfile);

if ($action == 'leads') {

//LEADS IMPORT
$sql = 'SELECT id, lead_filters, lead_status_enabled, lead_status, lead_status_user FROM outgouing_company WHERE state = 2 AND deleted != 1';
$res = $db->query($sql);

$outgoingCampaign = new PBXOutgoingCampaign();

while ($row = $res->fetch()) {
    $settings = $outgoingCampaign->getMainSettings($row['id']);
    $helper->setCampaignId($row['id']);
    if (isset($row['lead_filters']) || $row['lead_filters'] != '') {
        $row['lead_filters'] = json_decode("{".$row['lead_filters']."}", true);
        $leadsFromBitrix = importLeads($row['lead_filters'], 0, $helper);

        $leads = [];
        foreach ($leadsFromBitrix as $btxLead) {
            $exist = 0;
            if ($settings['e164']) $btxLead['PHONE'] = preg_replace("/[^+0-9]/", "",  $btxLead['PHONE']);
            if (!$settings['duplicates'] || $settings['duplicates_all']) {
                $exist = $outgoingCampaign->getContactByPhone($btxLead['PHONE'], $row['id']);
                if (isset($exist['state']) && !$settings['duplicates_all']) {
                    if (in_array($exist['state'], [3, 4, 6, 7])) $exist = 0;
                }
            }
            if (!$exist) {
                $leads[] = [
                    'id' => 0,
                    'phone' => $btxLead['PHONE'],
                    'name' => $btxLead['FIO'],
                    'description' => $btxLead['ID'],
                    'state' => 0,
                    'fromBitrix' => 1
                  ];
            }
        }

        $outgoingCampaign->addUpdate([
          'id' => $row['id'],
          'phones' => json_encode($leads),
          'settings' => 1
        ], 1);
    }
}

}

//CALL SYNC
$cdr = new PBXCdr();

$synchronizedCalls = [];
$exceptions = [];

$currentDatetime = new DateTime();
$yesterdayDatetime = new DateTime();
$yesterdayDatetime->modify('-1 day');

function sync ($crmCalls, &$synchronizedCalls, &$exceptions, $action) {
  if (count($crmCalls)) {
    var_dump('Calls count: ' . count($crmCalls));
    foreach ($crmCalls as $crmCall) {
      try {
        if (isset($crmCall['uniqid'])) $crmCall['uid'] = $crmCall['uniqid'];
        $helper = new EBitrix(0, $crmCall['uid']);
        $helper->synchronizeCall($crmCall, $synchronizedCalls, $exceptions);
      } catch (Exception | Error $e) {
          file_put_contents(
                  __DIR__."/logs/cron_" . $action . "_errors.log",
                  date("Y-m-d H:i:s") . " | " . $e->getMessage() . " | " . json_encode($crmCall) . PHP_EOL,
                  FILE_APPEND
          );
      }
    }
  }
}

if ($action == 'calls') { // все звонки
  $crmCalls = $cdr->getUnSynchronizedCdrs($yesterdayDatetime->format('Y-m-d H:i:00'), $currentDatetime->format('Y-m-d H:i:59')); //src === 11
  sync($crmCalls, $synchronizedCalls, $exceptions, $action);
  var_dump(['synchronizedCalls' => $synchronizedCalls, 'exception' => $exceptions]);
}

if ($action == 'calls_outgoing_even') { // исход_четн
  $crmCalls = $cdr->getUnSynchronizedCdrs($yesterdayDatetime->format('Y-m-d H:i:00'), $currentDatetime->format('Y-m-d H:i:59'), 'dst', 'even'); //dst === 11
  sync($crmCalls, $synchronizedCalls, $exceptions, $action);
  var_dump(['synchronizedCalls' => $synchronizedCalls, 'exception' => $exceptions]);
}

if ($action == 'calls_outgoing_odd') { // исход_нечетн
  $crmCalls = $cdr->getUnSynchronizedCdrs($yesterdayDatetime->format('Y-m-d H:i:00'), $currentDatetime->format('Y-m-d H:i:59'), 'dst', 'odd'); //dst === 11
  sync($crmCalls, $synchronizedCalls, $exceptions, $action);
  var_dump(['synchronizedCalls' => $synchronizedCalls, 'exception' => $exceptions]);
}

if ($action == 'calls_incoming') { // вход
  $crmCalls = $cdr->getUnSynchronizedCdrs($yesterdayDatetime->format('Y-m-d H:i:00'), $currentDatetime->format('Y-m-d H:i:59'), 'src'); //src === 11
  sync($crmCalls, $synchronizedCalls, $exceptions, $action);
  var_dump(['synchronizedCalls' => $synchronizedCalls, 'exception' => $exceptions]);
}

if ($action == 'attaching_records') {
  $helper = new EBitrix(0);
  $calls = $helper->getCallsWithUnAttachedRecord($yesterdayDatetime->format('Y-m-d H:i:00'), $currentDatetime->format('Y-m-d H:i:59'));

  foreach ($calls as $call) {
      $helper->attachRecord($call['u_id'], $call['bitrix_id']);
  }
}

unlink($lockfile);
