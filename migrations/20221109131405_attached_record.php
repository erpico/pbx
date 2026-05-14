<?php

use Phoenix\Migration\AbstractMigration;

class AttachedRecord extends AbstractMigration
{
    protected function up(): void
    {
        $this->execute("
ALTER TABLE `btx_call_sync` ADD COLUMN `attached_record` TINYINT(1) NULL DEFAULT 0 AFTER `result`;
");
    }

    protected function down(): void
    {
        
    }
}
