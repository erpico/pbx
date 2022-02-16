<?php

use Phoenix\Migration\AbstractMigration;

class AddMaxDayCallsLimitOutgoing extends AbstractMigration
{
    protected function up(): void
    {
        $this->execute("ALTER TABLE `outgouing_company` 
    ADD COLUMN `max_day_calls_limit` INT NULL DEFAULT NULL AFTER `call_duration_limit`;");

    }

    protected function down(): void
    {
        
    }
}
