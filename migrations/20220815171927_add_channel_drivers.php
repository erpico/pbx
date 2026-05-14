<?php

use Phoenix\Migration\AbstractMigration;

class AddChannelDrivers extends AbstractMigration
{
    protected function up(): void
    {
        $this->execute("
ALTER TABLE `acl_user_phone` 
ADD COLUMN `channel_driver` VARCHAR(45) NULL DEFAULT NULL AFTER `default_phone`;
ALTER TABLE `peers` 
ADD COLUMN `channel_driver` VARCHAR(45) NULL DEFAULT NULL AFTER `port`;
");
    }

    protected function down(): void
    {
        
    }
}
