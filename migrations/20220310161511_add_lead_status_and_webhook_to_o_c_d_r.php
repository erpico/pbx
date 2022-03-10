<?php

use Phoenix\Migration\AbstractMigration;

class AddLeadStatusAndWebhookToOCDR extends AbstractMigration
{
    protected function up(): void
    {
        $this->execute("ALTER TABLE `outgoing_campaign_dial_result_setting` 
ADD COLUMN `lead_status_result` INT NULL DEFAULT NULL AFTER `postpone_to`,
ADD COLUMN `webhook` VARCHAR(128) NULL DEFAULT NULL AFTER `lead_status_result`;
");
    }

    protected function down(): void
    {
        
    }
}
