<?php

use Phoenix\Migration\AbstractMigration;

class AddOutArchive extends AbstractMigration
{
    protected function up(): void
    {
        $this->execute("ALTER TABLE `outgouing_company` 
ADD COLUMN `archive` TINYINT(1) NOT NULL DEFAULT '0' AFTER `outgoing_filtering`;
");
    }

    protected function down(): void
    {
        
    }
}
