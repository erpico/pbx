<?php

use Phoenix\Migration\AbstractMigration;

class AddOutgoingSettings extends AbstractMigration
{
    protected function up(): void
    {
        $this->execute("ALTER TABLE `outgouing_company` 
ADD COLUMN `waiting_connection_time` INT NULL DEFAULT NULL AFTER `lead_status_tries_end`,
ADD COLUMN `answering_machine_beat` TINYINT(1) NULL DEFAULT '0' AFTER `waiting_connection_time`;
");
    }

    protected function down(): void
    {
        
    }
}
