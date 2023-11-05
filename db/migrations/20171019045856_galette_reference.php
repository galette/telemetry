<?php


use Phinx\Migration\AbstractMigration;

class GaletteReference extends AbstractMigration
{
    /**
     * Change Method.
     *
     * Write your reversible migrations using this method.
     *
     * More information on writing migrations is available here:
     * http://docs.phinx.org/en/latest/migrations.html#the-abstractmigration-class
     *
     * The following commands can be used in this method and Phinx will
     * automatically reverse them when rolling back:
     *
     *    createTable
     *    renameTable
     *    addColumn
     *    renameColumn
     *    addIndex
     *    addForeignKey
     *
     * Remember to call "create()" or "update()" and NOT "save()" when working
     * with the Table class.
     *
     * @return void
     */
    public function change()
    {
        $table = $this->table('galette_reference');
        $table
            ->addColumn('reference_id', 'integer')
            ->addForeignKey(
                'reference_id',
                'reference',
                'id',
                ['constraint' => 'telemetry_galette_reference_reference_id_fkey']
            )
            ->addColumn('num_members', 'integer', ['null' => true])
            ->addTimestamps()
            ->addIndex(['reference_id'], ['unique' => true])
            ->create()
        ;
    }
}
