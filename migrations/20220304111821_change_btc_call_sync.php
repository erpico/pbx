<?php

use Phoenix\Migration\AbstractMigration;

class ChangeBtcCallSync extends AbstractMigration
{
    protected function up(): void
    {
        $this->execute("ALTER TABLE `btx_call_sync` 
DROP COLUMN `status_code`,
DROP COLUMN `int_number`,
DROP COLUMN `duration`,
ADD COLUMN `status` INT NULL DEFAULT NULL AFTER `u_id`;
");
    }

    protected function down(): void
    {
        
    }
}
