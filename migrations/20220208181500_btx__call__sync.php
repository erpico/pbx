<?php

use Phoenix\Migration\AbstractMigration;

class BtxCallSync extends AbstractMigration
{
    protected function up(): void
    {
        $this->execute("
CREATE TABLE `btx_call_sync` (
`id` INT NOT NULL AUTO_INCREMENT,
`sync_time` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
`u_id` INT NOT NULL,
`src` TEXT NULL DEFAULT NULL,
`dst` TEXT NULL DEFAULT NULL,
`duration` INT NULL DEFAULT NULL,
`call_start_time` TIMESTAMP NULL DEFAULT NULL,
`reason` TEXT NULL DEFAULT NULL,
`result` TEXT NULL DEFAULT NULL,
PRIMARY KEY (`id`));
");
    }

    protected function down(): void
    {
        
    }
}
