<?php

use Phoenix\Migration\AbstractMigration;

class AddCampaignBitrixReq extends AbstractMigration
{
    protected function up(): void
    {
        $this->execute("ALTER TABLE `bitrix24_requests` ADD COLUMN `campaign_id` INT NULL DEFAULT NULL AFTER `response`;");
    }

    protected function down(): void
    {
        
    }
}
