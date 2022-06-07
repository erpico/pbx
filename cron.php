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
            if (!$settings['duplicates']) {
                $exist = $outgoingCampaign->getContactByPhone($btxLead['PHONE'], $row['id']);
                if (isset($exist['state'])) {
                    if (in_array($exist['state'], [3, 4, 6, 7])) $exist = 0;
                }
            }
            if (!$exist) {
                if ($settings['e164']) $btxLead['PHONE'] = preg_replace("/[^+0-9]/", "",  $btxLead['PHONE']);
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
        ]);
    }
}

}

if ($action == 'calls') {

//CALL SYNC
$cdr = new PBXCdr();

$synchronizedCalls = [];
$exceptions = [];

$currentDatetime = new DateTime();
$yesterdayDatetime = new DateTime();
$yesterdayDatetime->modify('-1 day');

$filter['time'] = '{"start":"' . $yesterdayDatetime->format('Y-m-d H:i:00') . '","end":"' . $currentDatetime->format('Y-m-d H:i:59') . '"}';
$crmCalls = $cdr->getReport($filter, 0, 1000000);

if (count($crmCalls)) {
    foreach ($crmCalls as $crmCall) {
        if (isset($crmCall['uniqid'])) $crmCall['uid'] = $crmCall['uniqid'];
        $helper = new EBitrix(0, $crmCall['uid']);
        $datetimePlusTalk = DateTime::createFromFormat('Y-m-d H:i:s', $crmCall['time'])->modify('+'.$crmCall['talk'].' sec')->format('Y-m-d H:i:s');
        $datetimePlusSec = DateTime::createFromFormat('Y-m-d H:i:s', $crmCall['time'])->modify('+1 sec')->format('Y-m-d H:i:s');
        $datetimePlus2Sec = DateTime::createFromFormat('Y-m-d H:i:s', $crmCall['time'])->modify('+2 sec')->format('Y-m-d H:i:s');
        if ($callsSync = $helper->getSynchronizedCalls($crmCall['uid'])) {
          $needSync = 1;
          foreach($callsSync as $callSync) {
            $synchronizedDatetimeMinus10Sec = DateTime::createFromFormat('Y-m-d H:i:s', $callSync['call_time'])->modify('-10 sec')->format('Y-m-d H:i:s');
            $synchronizedDatetimePlus10Sec = DateTime::createFromFormat('Y-m-d H:i:s', $callSync['call_time'])->modify('+10 sec')->format('Y-m-d H:i:s');

            if ($callSync['status'] == 1) {
              $result = $helper->addCall($crmCall, $callSync['call_id'], 0);
              isset($result['exception']) ? ($exceptions[] = $result) : ($synchronizedCalls[] = $result);
              break;
            }
            if (
              $callSync['status'] == 2 && //synchronized
              ($callSync['call_time'] === $crmCall['time'] || // synchronized by call/sync route
                $callSync['call_time'] === $datetimePlusTalk || // synchronized by ats
                ($synchronizedDatetimeMinus10Sec <=  $datetimePlusTalk && $datetimePlusTalk <= $synchronizedDatetimePlus10Sec) ||
                $callSync['call_time'] === $datetimePlusSec ||
                $callSync['call_time'] === $datetimePlus2Sec) // scripts delay
            ) {
              $needSync = 0;
            }
          }
          if ($needSync) {
            $result = $helper->addCall($crmCall, 0, 0);
            isset($result['exception']) ? ($exceptions[] = $result) : ($synchronizedCalls[] = $result);
          }
        } else {
            $result = $helper->addCall($crmCall);
            isset($result['exception']) ? ($exceptions[] = $result) : ($synchronizedCalls[] = $result);
        }
    }
}

var_dump(['synchronizedCalls' => $synchronizedCalls, 'exception' => $exceptions]);

}

unlink($lockfile);
