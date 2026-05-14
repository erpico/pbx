<?php

use Phoenix\Migration\AbstractMigration;

class AddCallsIvr extends AbstractMigration
{
    protected function up(): void
    {
        $this->execute("
CREATE TABLE `calls_ivr` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `queue` VARCHAR(32) NULL DEFAULT NULL,
  `ivr` VARCHAR(45) NULL DEFAULT NULL,
  `action` VARCHAR(45) NULL DEFAULT NULL,
  `call_id` VARCHAR(32) NULL DEFAULT NULL,
  `datetime` DATETIME NULL DEFAULT NULL,
  `clid` VARCHAR(80) NULL DEFAULT NULL,
  `channelid` VARCHAR(128) NULL DEFAULT NULL,
  PRIMARY KEY (`id`));
");
    }

    protected function down(): void
    {
        
    }
}
