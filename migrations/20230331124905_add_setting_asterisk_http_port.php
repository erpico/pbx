<?php

use Phoenix\Migration\AbstractMigration;

class AddSettingAsteriskHttpPort extends AbstractMigration
{
    protected function up(): void
    {
        $this->execute("INSERT INTO cfg_setting SET updated = NOW(), handle = 'asterisk_http_port';");
    }

    protected function down(): void
    {
        $this->execute("DELETE FROM cfg_setting WHERE handle = 'asterisk_http_port';");
    }
}
