<?php

use Illuminate\Database\Capsule\Manager as Capsule;
use Illuminate\Database\Schema\Blueprint;
use Phinx\Migration\AbstractMigration;

class AddAuditors extends AbstractMigration
{
    protected $schema;

    public function init()
    {
        $this->schema = Capsule::schema();
    }
    
    public function up()
    {
        $this->schema->getConnection()->table('auditors')->insert([
            [
                'id'          => '20',
                'group_id'    => '4',
                'title'       => 'Наличие отображаемых ссылок',
                'description' => 'Во всех объявлениях на поиске <b>должна быть</b> отображаемая ссылка',
                'explanation' => 'Отображаемая ссылка — это адрес страницы сайта, который будет показан в объявлении. Правильная настройка данного элемента повышает кликабельность объявления и снижает стоимость переходов.',
                'class'       => 'DisplayHrefs',
                'threshold'   => '0',
                'critical'    => '0',
                'sort'        => '3',
            ], [
                'id'          => '21',
                'group_id'    => '4',
                'title'       => 'Наличие utm-меток',
                'description' => 'У всех основных ссылок в объявлении <b>должны быть</b> UTM-метки',
                'explanation' => 'Данные метки помогают анализировать рекламную кампанию в любых системах аналитики. Например, если вы хотите посмотреть результаты вашей рекламной кампании при помощи Google Analytics.',
                'class'       => 'UtmParameters',
                'threshold'   => '0',
                'critical'    => '0',
                'sort'        => '20',
            ], [
                'id'          => '22',
                'group_id'    => '4',
                'title'       => 'Наличие utm-меток в быстрых ссылках',
                'description' => 'У всех быстрых ссылок в объявлении <b>должны быть</b> UTM-метки',
                'explanation' => 'Если пользователь перейдёт не по основной, а по одной из быстрых ссылок без метки, вы потеряете аналитические данные, что в дальнейшем затруднит анализ результатов рекламной кампании.',
                'class'       => 'UtmParametersInSiteLinks',
                'threshold'   => '0',
                'critical'    => '0',
                'sort'        => '30',
            ],
        ]);
    }

    public function down()
    {
        $this->schema->getConnection()->table('auditors')->whereIn('id', [20, 21, 22])->delete();
    }
}
