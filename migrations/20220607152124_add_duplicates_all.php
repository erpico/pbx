<?php

use Phoenix\Migration\AbstractMigration;

class AddDuplicatesAll extends AbstractMigration
{
    protected function up(): void
    {
      $this->execute("ALTER TABLE `outgouing_company` 
    ADD COLUMN `duplicates_all` TINYINT(1) NULL DEFAULT '1' AFTER `duplicates`;");
    }

    protected function down(): void
    {
        
    }
}
