<?php

use Phoenix\Migration\AbstractMigration;

class AddConfigIpAddressesPhone extends AbstractMigration
{
    protected function up(): void
    {
      $this->execute("
ALTER TABLE `acl_group_phone` ADD COLUMN `remote_config_phone_addresses` VARCHAR(1024) NULL DEFAULT NULL AFTER `outgoing_phone`;
ALTER TABLE `acl_user_phone` ADD COLUMN `remote_config_phone_addresses` VARCHAR(1024) NULL DEFAULT NULL AFTER `channel_driver`;
");
    }

    protected function down(): void
    {
        
    }
}
