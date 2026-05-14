<?php

use Phoenix\Migration\AbstractMigration;

class DefaultUser extends AbstractMigration
{
    protected function up(): void
    {
        $this->execute("INSERT INTO cfg_setting SET handle = 'default_user', val =''");
    }

    protected function down(): void
    {
        
    }
}
