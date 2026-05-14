<?php

use Phoenix\Migration\AbstractMigration;

class AddBtxChannelToBtx24Req extends AbstractMigration
{
    protected function up(): void
    {
        $this->execute("ALTER TABLE `bitrix24_requests` ADD INDEX `idx_channel` (`channel` ASC) VISIBLE;");
    }

    protected function down(): void
    {
        
    }
}
