<?php

use Phoenix\Migration\AbstractMigration;

class Test extends AbstractMigration
{
    protected function up(): void
    {
      $this->execute("INSERT INTO  outgouing_company_contacts SET outgouing_company_id = 3, phone = '12', name = 'dsadsa', updated = NOW(), description = 'dsdsa'");
    }

    protected function down(): void
    {
        
    }
}
