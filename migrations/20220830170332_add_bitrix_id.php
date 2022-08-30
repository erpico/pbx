<?php

use Phoenix\Migration\AbstractMigration;

class AddBitrixId extends AbstractMigration
{
    protected function up(): void
    {
	$this->execute("
CREATE TABLE `acl_user_bitrix_id` (
  `user_id` INT UNSIGNED NULL DEFAULT NULL,
  `bitrix_user_id` INT UNSIGNED NULL DEFAULT NULL,
  UNIQUE INDEX `user_id_UNIQUE` (`user_id` ASC) VISIBLE,
  UNIQUE INDEX `bitrix_user_id_UNIQUE` (`bitrix_user_id` ASC) VISIBLE);
");
  }

    protected function down(): void
    {
        
    }
}
