<?php

use Phoenix\Migration\AbstractMigration;

class ChangeLeadsFiltersToText extends AbstractMigration
{
    protected function up(): void
    {
        $this->execute("ALTER TABLE `outgouing_company` CHANGE COLUMN `lead_filters` `lead_filters` TEXT NULL DEFAULT NULL ;");
    }

    protected function down(): void
    {
        
    }
}
