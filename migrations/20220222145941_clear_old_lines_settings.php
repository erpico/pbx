<?php

use Phoenix\Migration\AbstractMigration;

class ClearOldLinesSettings extends AbstractMigration
{
    protected function up(): void
    {
        $this->execute("
DELETE FROM cfg_setting WHERE `handle` = 'line.0110747';
DELETE FROM cfg_setting WHERE `handle` = 'line.0111535';
DELETE FROM cfg_setting WHERE `handle` = 'line.4499999';
DELETE FROM cfg_setting WHERE `handle` = 'line.0110748';
DELETE FROM cfg_setting WHERE `handle` = 'line.0111712';
DELETE FROM cfg_setting WHERE `handle` = 'line.74957750440';
");
    }

    protected function down(): void
    {
        
    }
}
