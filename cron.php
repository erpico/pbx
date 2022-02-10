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

$sql = 'SELECT id, lead_filters FROM outgouing_company';
$res = $db->query($sql);
while ($row = $res->fetch()) {
  if ($row['lead_filters'] != null || $row['lead_filters'] != '') {
    $row['lead_filters'] = json_decode("{".$row['lead_filters']."}", true);
    $leadsFromBitrix = $helper->getLeadsByFilters($row['lead_filters']);
    $leadsFromDB = [];

    $ocsql =  'SELECT id, phone FROM outgouing_company_contacts';
    $ocres = $db->query($ocsql);
    while ($ocrow = $ocres->fetch()) {
      $leadsFromDB[] =  $ocrow;
    }

    foreach ($leadsFromBitrix as $btxLead) {
        $exist = 0;
        foreach ($leadsFromDB as $dbLead) {
            if ($dbLead['phone'] == $btxLead['PHONE']) $exist = 1;
        }
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
