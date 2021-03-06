<?php

use Illuminate\Database\Capsule\Manager as Capsule;
use Illuminate\Database\Schema\Blueprint;
use Phinx\Migration\AbstractMigration;

class Fixes extends AbstractMigration
{
    protected $schema;

    public function init()
    {
        $this->schema = Capsule::schema();
    }
    
    public function up()
    {
        $this->schema->getConnection()->table('auditors')->where('id', 13)->update([
            'explanation' => 'Ретаргетинг - это возможность привлекать аудиторию, уже посещавшую ваш сайт или собранную в Яндекс.Аудиториях. Клиенты из ретаргетинга часто обходятся дешевле, чем привлечённые при первичной коммуникации.',
        ]);

        $this->schema->getConnection()->table('auditors')->where('id', 15)->update([
            'explanation' => 'Корректировки ставок позволяют показывать рекламу наиболее предпочтительной аудитории. Корректировать ставки можно по типу устройства, полу, возрасту и сегментам из Яндекс.Метрики и Яндекс.Аудиторий.',
        ]);

        $this->schema->getConnection()->table('auditors')->where('id', 10)->update([
            'explanation' => 'Доля мобильного трафика постоянно растёт, и это необходимо учитывать в своих рекламных объявлениях. Их нужно адаптировать под особенности аудитории.',
        ]);

        $this->schema->getConnection()->table('auditors')->where('id', 2)->update([
            'explanation' => 'Использование шаблонов в рекламных объявлениях позволяет показывать пользователям более релевантные объявления и, как результат, позволяет снизить ставку за клик до 40%, избегая при этом опасности получить статус "Мало показов".',
        ]);

        $this->schema->getConnection()->table('auditors')->where('id', 8)->update([
            'explanation' => 'Второй заголовок может конкретизировать информацию, которую вы доносите в заголовке рекламного объявления. Данный атрибут оказывает значительное влияние на результаты рекламной кампании.',
        ]);
    }

    public function down()
    {
        $this->schema->getConnection()->table('auditors')->where('id', 13)->update([
            'explanation' => 'Ретаргетинг - это возможность привлекать аудиторию, которая была на ваших сайтах, или собранную в Яндекс.Аудиториях. Клиенты из ретаргетинга часто обходятся дешевле, чем привлечённые при первичной коммуникации.',
        ]);

        $this->schema->getConnection()->table('auditors')->where('id', 15)->update([
            'explanation' => 'Корректировки ставок позволяют показывать рекламу наиболее предпочтительной аудитории. Корректировать ставки можно по типу устройства, полу, возрасту и сегментам из метрики и Яндекс.Аудиторий.',
        ]);

        $this->schema->getConnection()->table('auditors')->where('id', 10)->update([
            'explanation' => 'Доля мобильно трафика постоянно растёт и это необходимо учитывать в своих рекламных объявлениях. Их нужно адаптировать под особенности аудитории.',
        ]);

        $this->schema->getConnection()->table('auditors')->where('id', 2)->update([
            'explanation' => 'Использование шаблонов в рекламных объявлениях позволяет показывать пользователям более релевантные объявления и, как результат, позволяет снизить ставку за клик до 40%, избегая при этом опастности получить статус "Мало показов".',
        ]);

        $this->schema->getConnection()->table('auditors')->where('id', 8)->update([
            'explanation' => 'Второй заголовок может конкретизировать информацию, которую вы доносите в заголовке рекламного объявления. Данный аттрибут оказывает значительное влияние на результаты рекламной кампании.',
        ]);
    }
}
