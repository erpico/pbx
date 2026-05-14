<?php

use Phoenix\Migration\AbstractMigration;

class AddSpecificPhones extends AbstractMigration
{
    protected function up(): void
    {
        $this->execute("CREATE TABLE `specific_phones` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `number` VARCHAR(16) NOT NULL,
  PRIMARY KEY (`id`));");
    }

    protected function down(): void
    {
        
    }
}
