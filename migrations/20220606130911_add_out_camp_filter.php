<?php

use Phoenix\Migration\AbstractMigration;

class AddOutCampFilter extends AbstractMigration
{
    protected function up(): void
    {
        $this->execute("ALTER TABLE `outgouing_company` 
ADD COLUMN `outgoing_filtering` TINYINT(1) NULL DEFAULT '0' AFTER `actions_count`;");
    }

    protected function down(): void
    {
        
    }
}
