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

$sql = 'SELECT id, lead_filters FROM outgouing_company';
$res = $db->query($sql);

$outgoingCompany = new PBXOutgoingCampaign();

while ($row = $res->fetch()) {
    if (isset($row['lead_filters']) || $row['lead_filters'] != '') {
        $row['lead_filters'] = json_decode("{".$row['lead_filters']."}", true);
        $leadsFromBitrix = importLeads($row['lead_filters'], 0, $helper);

        foreach ($leadsFromBitrix as $btxLead) {
            $exist = $outgoingCompany->getContactByPhone($btxLead['PHONE']);
            if (!$exist) {
                $addSql = "INSERT INTO outgouing_company_contacts 
                           SET `updated` = NOW(), 
                           `outgouing_company_id` = '".$row['id']."', 
                           `phone` = '".$btxLead['PHONE']."', 
                           `name` = '".$btxLead['FIO']."', 
                           `description` = '".$btxLead['ID']."'";
                $db->exec($addSql);
            }
        }
    }
}
