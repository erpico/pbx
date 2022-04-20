<?php

use Phoenix\Migration\AbstractMigration;

class AddBitrixLeadcreate1 extends AbstractMigration
{
    protected function up(): void
    {
        $this->execute("INSERT INTO cfg_setting SET updated = NOW(), handle = 'bitrix.leadcreate', val = '1'");
    }

    protected function down(): void
    {
        
    }
}
