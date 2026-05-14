<?php

use Phoenix\Migration\AbstractMigration;

class AddFltrOutgoing1 extends AbstractMigration
{
    protected function up(): void
    {
  	$this->execute("ALTER TABLE `outgouing_company` 
ADD COLUMN `lead_filters` VARCHAR(128) NULL DEFAULT NULL AFTER `dial_context`;");      
    }

    protected function down(): void
    {
        
    }
}
