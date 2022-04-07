<?php

use Phoenix\Migration\AbstractMigration;

class AddIdxQueueLog extends AbstractMigration
{
    protected function up(): void
    {
        $this->execute("ALTER TABLE `queue_log` ADD INDEX `idx_time` (`time` ASC) VISIBLE;");
    }

    protected function down(): void
    {
        
    }
}
