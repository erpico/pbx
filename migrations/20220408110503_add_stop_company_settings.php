<?php

use Phoenix\Migration\AbstractMigration;

class AddStopCompanySettings extends AbstractMigration
{
    protected function up(): void
    {
        $this->execute("
ALTER TABLE `outgouing_company` 
ADD COLUMN `choice_of_numbers_enabled` TINYINT(1) NULL DEFAULT '0' AFTER `answering_machine_beat`,
ADD COLUMN `choice_of_numbers` INT NULL DEFAULT NULL AFTER `choice_of_numbers_enabled`,
ADD COLUMN `transfer_to_operator_enabled` TINYINT(1) NULL DEFAULT '0' AFTER `choice_of_numbers`,
ADD COLUMN `transfer_to_operator` INT NULL DEFAULT NULL AFTER `transfer_to_operator_enabled`,
ADD COLUMN `voice_message_enabled` TINYINT(1) NULL DEFAULT '0' AFTER `transfer_to_operator`,
ADD COLUMN `voice_message` INT NULL DEFAULT NULL AFTER `voice_message_enabled`,
ADD COLUMN `stop_after_enabled` TINYINT(1) NULL DEFAULT '0' AFTER `voice_message`,
ADD COLUMN `stop_after` INT NULL DEFAULT NULL AFTER `stop_after_enabled`;
");
    }

    protected function down(): void
    {
        
    }
}
