<?php

use Phoenix\Migration\AbstractMigration;

class AddBtx24Req extends AbstractMigration
{
    protected function up(): void
    {
        $this->execute("CREATE TABLE IF NOT EXISTS `bitrix24_requests` (
  `id` int NOT NULL AUTO_INCREMENT,
  `channel` varchar(128) DEFAULT NULL,
  `datetime` datetime DEFAULT NULL,
  `url` varchar(1024) DEFAULT NULL,
  `query` text,
  `response` json DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=129067 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
");
    }

    protected function down(): void
    {
        
    }
}
