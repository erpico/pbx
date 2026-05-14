<?php

use Phoenix\Migration\AbstractMigration;

class AddActionsCountOutgoing extends AbstractMigration
{
    protected function up(): void
    {
        $this->execute("ALTER TABLE `outgouing_company` 
ADD COLUMN `actions_count_enabled` TINYINT(1) NULL DEFAULT '0' AFTER `stop_after`,
ADD COLUMN `actions_count` INT NULL DEFAULT NULL AFTER `actions_count_enabled`;
");
    }

    protected function down(): void
    {
        
    }
}
