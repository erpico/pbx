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

function importLeads($filters, $next, $helper) {
    $leadsFromBitrix = [];
    $result = $helper->getLeadsByFilters($filters, $next);
    if(isset($result['result']) && $result['result']) {
        foreach ($result['result'] as $lead) {
            $leadsFromBitrix[] = $lead;
        }
    }

    if (isset($result['next'])) {
        $result = importLeads($filters, $result['next'], $helper);
        if ($result) {
            foreach ($result as $lead) {
                $leadsFromBitrix[] = $lead;
            }
        }
    }

    return $leadsFromBitrix;
}

if (!isset($argv[1])) {
    print "Usage:\n cron.php [leads|calls]\n";
    exit(1);
}

$action = $argv[1];

if ($action == 'leads') {

//LEADS IMPORT
$sql = 'SELECT id, lead_filters, lead_status_enabled, lead_status, lead_status_user FROM outgouing_company';
$res = $db->query($sql);

$outgoingCompany = new PBXOutgoingCampaign();

while ($row = $res->fetch()) {
    $settings = $outgoingCompany->getMainSettings($row['id']);
    if (isset($row['lead_filters']) || $row['lead_filters'] != '') {
        $row['lead_filters'] = json_decode("{".$row['lead_filters']."}", true);
        $leadsFromBitrix = importLeads($row['lead_filters'], 0, $helper);

        foreach ($leadsFromBitrix as $btxLead) {
            $exist = 0;
            if (!$settings['duplicates']) $exist = $outgoingCompany->getContactByPhone($btxLead['PHONE'], $row['id']);
            if (!$exist) {
                if ($settings['e164']) $btxLead['PHONE'] = preg_replace("/[^+0-9]/", "",  $btxLead['PHONE']);
                $outgoingCompany->addUpdate([
                    'id' => $row['id'],
                    'phones' => json_encode([
                            [
                                'id' => 0,
                                'phone' => $btxLead['PHONE'],
                                'name' => $btxLead['FIO'],
                                'description' => $btxLead['ID'],
                                'state' => 0
                            ]
                        ]),
                    'settings' => 1
                ]);
            }
        }
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
        if ($callsSync = $helper->getSynchronizedCalls($crmCall['uid'])) {
          $needSync = 1;
          foreach($callsSync as $callSync) {
            if ($callSync['status'] == 1) {
              $result = $helper->addCall($crmCall, $callSync['call_id'], 0);
              isset($result['exception']) ? ($exceptions[] = $result) : ($synchronizedCalls[] = $result);
              break;
            }
            if (
              $callSync['status'] == 2 && //synchronized
              ($callSync['call_time'] === $crmCall['time'] || // synchronized by call/sync route
                $callSync['call_time'] === $datetimePlusTalk || // synchronized by ats
                $callSync['call_time'] === $datetimePlusSec) // scripts delay
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