<?php

use Phoenix\Migration\AbstractMigration;

class AddOutgoingE162Duplicates extends AbstractMigration
{
    protected function up(): void
    {
        $this->execute("ALTER TABLE `outgouing_company` 
ADD COLUMN `e164` TINYINT(1) NULL DEFAULT '0' AFTER `lead_status_tries_end`,
ADD COLUMN `duplicates` TINYINT(1) NULL DEFAULT '0' AFTER `e164`;");
    }

    protected function down(): void
    {
        
    }
}
