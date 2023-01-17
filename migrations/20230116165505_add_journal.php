<?php

use Phoenix\Migration\AbstractMigration;

class AddJournal extends AbstractMigration
{
    protected function up(): void
    {
        $this->execute("
CREATE TABLE `journal` (
  `id` int NOT NULL AUTO_INCREMENT,
  `user_id` int DEFAULT NULL,
  `time` datetime DEFAULT NULL,
  `action` varchar(64) DEFAULT NULL,
  `data` json DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `action_indx` (`action`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
");
    }

    protected function down(): void
    {
        
    }
}
