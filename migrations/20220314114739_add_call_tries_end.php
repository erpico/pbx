<?php

use Phoenix\Migration\AbstractMigration;

class AddCallTriesEnd extends AbstractMigration
{
    protected function up(): void
    {
        $this->execute("ALTER TABLE `outgouing_company` 
ADD COLUMN `lead_status_tries_end` VARCHAR(128) NULL DEFAULT NULL AFTER `lead_status_user`;
");
    }

    protected function down(): void
    {
        
    }
}
