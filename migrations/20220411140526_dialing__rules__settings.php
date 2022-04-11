<?php

use Phoenix\Migration\AbstractMigration;

class Dialing_rules_settings extends AbstractMigration
{
    protected function up(): void
    {
        $this->execute("CREATE TABLE `dialing_rules_settings` (
  `dialing_rule` varchar(128) NOT NULL,
  `channel` int DEFAULT NULL,
  `caller_id` int unsigned DEFAULT NULL,
  `updated` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`dialing_rule`),
  UNIQUE KEY `dialing_rule_UNIQUE` (`dialing_rule`),
  KEY `idx_channel_fk` (`channel`),
  CONSTRAINT `idx_channel` FOREIGN KEY (`channel`) REFERENCES `peers` (`id`) ON DELETE RESTRICT ON UPDATE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8;
");
    }

    protected function down(): void
    {
        
    }
}
