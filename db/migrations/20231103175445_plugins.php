<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

final class Plugins extends AbstractMigration
{
    public function change(): void
    {
        $table = $this->table('glpi_plugin');
        $table
            ->renameColumn('pkey', 'name')
            ->rename('plugins')
            ->update();

        $table = $this->table('telemetry_glpi_plugin');
        $table
            ->renameColumn('glpi_plugin_id', 'plugin_id')
            ->rename('plugins_telemetry')
            ->update();
    }
}
