<?php

use Phoenix\Migration\AbstractMigration;

class AddLineNumberUser extends AbstractMigration
{
    protected function up(): void
    {
        $this->execute("
INSERT INTO cfg_setting SET handle = 'line.0110747', val = '812', updated = now();
INSERT INTO cfg_setting SET handle = 'line.0111535', val = '812', updated = now();
INSERT INTO cfg_setting SET handle = 'line.4499999', val = '812', updated = now();
INSERT INTO cfg_setting SET handle = 'line.0110748', val = '4121', updated = now();
INSERT INTO cfg_setting SET handle = 'line.0111712', val = '4121', updated = now();
INSERT INTO cfg_setting SET handle = 'line.74957750440', val = '4121', updated = now();
");
    }
    protected function down(): void
    {

    }
}
