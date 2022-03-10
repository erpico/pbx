<?php

use Phoenix\Migration\AbstractMigration;

class AddLeadStatus extends AbstractMigration
{
    protected function up(): void
    {
        $this->execute("ALTER TABLE `outgouing_company` 
ADD COLUMN `lead_status_enabled` INT NULL DEFAULT NULL AFTER `lead_filters`,
ADD COLUMN `lead_status` VARCHAR(128) NULL DEFAULT NULL AFTER `lead_status_enabled`;
");
    }

    protected function down(): void
    {
        
    }
}
