<?php
use Migrations\AbstractMigration;

class AddLeadsToAdGroupStatistics extends AbstractMigration
{
    /**
     * Change Method.
     *
     * More information on this method is available here:
     * http://docs.phinx.org/en/latest/migrations.html#the-change-method
     * @return void
     */
    public function change()
    {
        $table = $this->table('ad_group_statistics_daily');
        $table->addColumn('leads', 'integer', [
            'default' => null,
            'limit' => 11,
            'null' => false,
			'after' => 'emails',
        ]);
        $table->update();
    }
}
