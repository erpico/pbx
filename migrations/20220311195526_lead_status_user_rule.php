<?php

use Phoenix\Migration\AbstractMigration;

class LeadStatusUserRule extends AbstractMigration
{
    protected function up(): void
    {
        $this->execute("ALTER TABLE `outgoing_campaign_dial_result_setting` 
ADD COLUMN `lead_status_user_rules` INT NULL DEFAULT NULL AFTER `webhook`;
");
    }

    protected function down(): void
    {
        
    }
}
