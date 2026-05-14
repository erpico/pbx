<?php

use Phoenix\Migration\AbstractMigration;

class AddDefUsSpb extends AbstractMigration
{
    protected function up(): void
    {
        $this->execute("
INSERT INTO cfg_setting SET handle = 'default_user_msk', val = (SELECT a.val FROM (SELECT val FROM cfg_setting WHERE handle = 'default_user') AS a);
DELETE FROM cfg_setting WHERE handle = 'default_user';
INSERT INTO cfg_setting (handle) VALUES ('default_user_spb');
");
    }

    protected function down(): void
    {
        
    }
}
