<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

final class References extends AbstractMigration
{
    /**
     * Change Method.
     *
     * Write your reversible migrations using this method.
     *
     * More information on writing migrations is available here:
     * https://book.cakephp.org/phinx/0/en/migrations.html#the-change-method
     *
     * Remember to call "create()" or "update()" and NOT "save()" when working
     * with the Table class.
     */
    public function change(): void
    {
        $this->table('glpi_reference')->drop()->update();

        $table = $this->table('reference');
        $table
            ->addColumn('num_members', 'integer', ['null' => true])
            ->update();

        //migrate existing data...
        $rows = $this->fetchAll('SELECT reference_id, num_members FROM galette_reference');

        foreach ($rows as $row) {
            $builder = $this->getQueryBuilder();
            $builder->update('reference')
                ->set('num_members', $row['num_members'])
                ->where(['id' => $row['reference_id']])
                ->execute();
        }

        $this->table('galette_reference')->drop()->update();
    }
}
