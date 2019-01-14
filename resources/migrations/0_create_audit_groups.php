<?php

use Illuminate\Database\Capsule\Manager as Capsule;
use Illuminate\Database\Schema\Blueprint;
use Phinx\Migration\AbstractMigration;

class CreateAuditGroups extends AbstractMigration
{
    protected $schema;

    public function init()
    {
        $this->schema = Capsule::schema();
    }

    public function up()
    {
        $this->schema->create('auditor_groups', function (Blueprint $table) {
            $table->engine = 'InnoDB';
            $table->increments('id');
            $table->string('title');
            $table->unsignedTinyInteger('sort')->default(0);
        });

        $db = $this->schema->getConnection();

        $db->table('auditor_groups')->insert([
            [
                'id'    => '1',
                'title' => 'Аккаунт',
                'sort'  => '0',
            ], [
                'id'    => '2',
                'title' => 'Кампании',
                'sort'  => '1',
            ], [
                'id'    => '3',
                'title' => 'Группы объявлений',
                'sort'  => '2',
            ], [
                'id'    => '4',
                'title' => 'Объявления',
                'sort'  => '3',
            ]
        ]);
    }

    public function down()
    {
        $this->schema->drop('auditor_groups');
    }
}
