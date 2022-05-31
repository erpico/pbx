<?php

use Phoenix\Migration\AbstractMigration;

class AddApiKeys extends AbstractMigration
{
    protected function up(): void
    {
        $this->execute("DROP TABLE IF EXISTS `api_keys`");
        $this->execute("CREATE TABLE `api_keys` (
  `id` int NOT NULL AUTO_INCREMENT,
  `value` varchar(65) CHARACTER SET utf8 COLLATE utf8_bin NOT NULL,
  `client_id` int NOT NULL,
  `last_request` datetime DEFAULT NULL,
  `deleted` tinyint(1) DEFAULT '0',
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=18 DEFAULT CHARSET=utf8 COLLATE=utf8_bin;
");
    }

    protected function down(): void
    {
        
    }
}
