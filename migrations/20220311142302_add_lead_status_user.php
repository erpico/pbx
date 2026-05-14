<?php

use Phoenix\Migration\AbstractMigration;

class AddLeadStatusUser extends AbstractMigration
{
    protected function up(): void
    {
        $this->execute("ALTER TABLE `outgouing_company` 
ADD COLUMN `lead_status_user` VARCHAR(128) NULL DEFAULT NULL AFTER `lead_status`;
");
    }

    protected function down(): void
    {
        
    }
}
