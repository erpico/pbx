<?php

use Phoenix\Migration\AbstractMigration;

class AddCallTimeBtxCallSync extends AbstractMigration
{
    protected function up(): void
    {
        $this->execute("ALTER TABLE `btx_call_sync` 
ADD COLUMN `call_time` TIMESTAMP NULL DEFAULT NULL AFTER `call_id`;
");
    }

    protected function down(): void
    {
        
    }
}
