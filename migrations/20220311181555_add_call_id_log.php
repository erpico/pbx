<?php

use Phoenix\Migration\AbstractMigration;

class AddCallIdLog extends AbstractMigration
{
    protected function up(): void
    {
     $this->execute("ALTER TABLE `btx_call_sync` 
ADD COLUMN `call_id` VARCHAR(128) NULL DEFAULT NULL AFTER `status`;
");
    }

    protected function down(): void
    {
        
    }
}
