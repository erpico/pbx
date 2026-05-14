<?php

use Phoenix\Migration\AbstractMigration;

class AddDefaultLine extends AbstractMigration
{
    protected function up(): void
    {
        $this->execute("INSERT INTO `cfg_setting` (`handle`) VALUES ('default_line');");
    }

    protected function down(): void
    {
        
    }
}
