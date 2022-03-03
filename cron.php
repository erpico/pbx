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
    if($result['result']) {
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
//LEADS IMPORT
$sql = 'SELECT id, lead_filters FROM outgouing_company';
$res = $db->query($sql);

$outgoingCompany = new PBXOutgoingCampaign();

while ($row = $res->fetch()) {
    if (isset($row['lead_filters']) || $row['lead_filters'] != '') {
        $row['lead_filters'] = json_decode("{".$row['lead_filters']."}", true);
        $leadsFromBitrix = importLeads($row['lead_filters'], 0, $helper);

        foreach ($leadsFromBitrix as $btxLead) {
            $exist = $outgoingCompany->getContactByPhone($btxLead['PHONE'], $row['id']);
            if (!$exist) {
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

//CALL SYNC
if (isset($argv[1])) {
  $token = $argv[1];
  $_COOKIE['token'] = $token;
}

$helper = new EBitrix(0);

$cdr = new PBXCdr();

$synchronizedCalls = [];
$exceptions = [];

$currentDatetime = new DateTime();
$yesterdayDatetime = new DateTime();
$yesterdayDatetime->modify('-1 day');

$filter['time'] = '{"start":"' . $yesterdayDatetime->format('Y-m-d H:i:00') . '","end":"' . $currentDatetime->format('Y-m-d H:i:59') . '"}';
//$filter['time'] = '{"start":"2021-12-23 09:40:00","end":"2021-12-23 09:50:00"}'; // test for small range
$crmCalls = $cdr->getReport($filter, 0, 1000000);

if (count($crmCalls)) {
  foreach ($crmCalls as $crmCall) {
    if (isset($crmCall['uniqid'])) $crmCall['uid'] = $crmCall['uniqid'];
    if (!is_numeric($crmCall['dst'])) $crmCall['dst'] = preg_replace('/[^0-9]/', '', $crmCall['dst']);
    if (!is_numeric($crmCall['src'])) $crmCall['src'] = preg_replace('/[^0-9]/', '', $crmCall['src']);
    $int_num = mb_strlen($crmCall['dst']) == 11 ? $crmCall['src'] : $crmCall['dst'];
    $status_code = $helper->getStatusCodeByReason($crmCall['reason']);
    if ($helper->getSynchronizedCalls($crmCall['uid'], $crmCall['talk'], $int_num, $crmCall['reason'])) continue;
    $crmCall['status_code'] = $status_code;
    $result = $helper->addCall($crmCall);
    isset($result['exception']) ? ($exceptions[] = $result) : ($synchronizedCalls[] = $result);
  }
}

var_dump(['synchronizedCalls' => $synchronizedCalls, 'exception' => $exceptions]);
