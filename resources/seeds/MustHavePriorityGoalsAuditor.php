<?php


use Mnoskov\Auditor\Models\Auditor;
use Mnoskov\Auditor\Models\AuditorGroup;
use Phinx\Seed\AbstractSeed;

class MustHavePriorityGoalsAuditor extends AbstractSeed
{
    /**
     * Run Method.
     *
     * Write your database seeder using this method.
     *
     * More information on writing seeders is available here:
     * http://docs.phinx.org/en/latest/seeding.html
     */
    public function run()
    {
        $groups = AuditorGroup::all()->pluck('id', 'title');

        Auditor::updateOrCreate([
            'class' => 'MustHavePriorityGoals',
        ], [
            'group_id'    => $groups['Кампании'],
            'title'       => 'Наличие ключевых целей',
            'description' => 'Во всех кампаниях <b>должны быть заданы</b> ключевые цели',
            'explanation' => 'Ключевые цели в кампаниях Директа позволяют рекламодателю указать, какие цели из Яндекс.Метрики наиболее важны для продвижения, и назначить ценность достижения для бизнеса в денежном эквиваленте. Директ, опираясь на эти данные, стремится обеспечить как можно больше конверсий по заданной стоимости',
            'threshold'   => 0,
            'critical'    => 0,
            'sort'        => 4,
        ]);
    }
}
