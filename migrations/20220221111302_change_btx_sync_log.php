<?php

use Phoenix\Migration\AbstractMigration;

class ChangeBtxSyncLog extends AbstractMigration
{
    protected function up(): void
    {
     $this->execute("ALTER TABLE `btx_call_sync` 
    DROP COLUMN `call_start_time`,
    DROP COLUMN `dst`,
    DROP COLUMN `src`,
    ADD COLUMN `int_number` INT NULL DEFAULT NULL AFTER `duration`,
    CHANGE COLUMN `reason` `status_code` TEXT NULL DEFAULT NULL ;
    ALTER TABLE `btx_call_sync` 
    CHANGE COLUMN `u_id` `u_id` VARCHAR(64) NOT NULL ;
    ");
    }

    protected function down(): void
    {
        
    }
}
