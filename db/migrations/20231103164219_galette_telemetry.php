<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

final class GaletteTelemetry extends AbstractMigration
{
    public function change(): void
    {
        $table = $this->table('telemetry');
        $table
            ->renameColumn('glpi_uuid', 'instance_uuid')
            ->renameColumn('glpi_version', 'galette_version')
            ->renameColumn('glpi_default_language', 'instance_default_language')
            ->renameColumn('glpi_avg_entities', 'avg_members')
            ->renameColumn('glpi_avg_computers', 'avg_contributions')
            ->renameColumn('glpi_avg_networkequipments', 'avg_transactions')
            ->removeColumn('glpi_avg_tickets')
            ->removeColumn('glpi_avg_problems')
            ->removeColumn('glpi_avg_changes')
            ->removeColumn('glpi_avg_projects')
            ->removeColumn('glpi_avg_users')
            ->removeColumn('glpi_avg_groups')
            ->removeColumn('glpi_ldap_enabled')
            ->removeColumn('glpi_mailcollector_enabled')
            ->removeColumn('glpi_notifications')
            ->removeColumn('install_mode')
            ->update();

    }
}
