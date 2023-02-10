<?php

use Phoenix\Migration\AbstractMigration;

class AddFcmToken extends AbstractMigration
{
    protected function up(): void
    {
        $this->execute("ALTER TABLE `acl_user` ADD COLUMN `fcm_token` VARCHAR(256) NULL DEFAULT NULL AFTER `last_request`;");
    }

    protected function down(): void
    {
        
    }
}
