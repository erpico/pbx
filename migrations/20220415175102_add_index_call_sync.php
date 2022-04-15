<?php

use Phoenix\Migration\AbstractMigration;

class AddIndexCallSync extends AbstractMigration
{
    protected function up(): void
    {
        $this->execute("ALTER TABLE `btx_call_sync` ADD INDEX `idx_u_id` (`u_id` ASC) VISIBLE;");
    }

    protected function down(): void
    {
        
    }
}
