<?php

use Phoenix\Migration\AbstractMigration;

class AddSettingReportsDefaultPeriod extends AbstractMigration
{
    protected function up(): void
    {
        $this->execute("INSERT INTO cfg_setting SET updated = NOW(), handle = 'reports.default_period_days', val = '1';");
    }

    protected function down(): void
    {
        $this->execute("DELETE FROM cfg_setting WHERE handle = 'reports.default_period_days';");
    }
}
