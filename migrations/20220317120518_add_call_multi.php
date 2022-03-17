<?php

use Phoenix\Migration\AbstractMigration;

class AddCallMulti extends AbstractMigration
{
    protected function up(): void
    {
        $this->execute("ALTER TABLE `outgouing_company` 
ADD COLUMN `calls_multiplier` DECIMAL(4,2) NULL DEFAULT NULL AFTER `max_day_calls_limit`;
");
    }

    protected function down(): void
    {
        
    }
}
