<?php

use Phoenix\Migration\AbstractMigration;

class AddUsersPhoto extends AbstractMigration
{
    protected function up(): void
    {
        $this->execute("ALTER TABLE `acl_user` 
ADD COLUMN `photo` VARCHAR(255) NULL DEFAULT NULL AFTER `fullname`;
");
    }

    protected function down(): void
    {
        
    }
}
