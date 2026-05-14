<?php

use Phoenix\Migration\AbstractMigration;

class AddCallsIvr extends AbstractMigration
{
    protected function up(): void
    {
        $this->execute("
CREATE TABLE `ivr_actions` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `time` DATETIME NULL DEFAULT NULL,
  `call_id` VARCHAR(32) NULL DEFAULT NULL,
  `channel` VARCHAR(128) NULL DEFAULT NULL,
  `clid` VARCHAR(80) NULL DEFAULT NULL,
  `queue` VARCHAR(32) NULL DEFAULT NULL,
  `ivr` VARCHAR(45) NULL DEFAULT NULL,
  `action` VARCHAR(45) NULL DEFAULT NULL,
  PRIMARY KEY (`id`));
");
    }

    protected function down(): void
    {

    }
}
