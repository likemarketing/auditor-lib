<?php

namespace Mnoskov\Auditor\Auditors;

class MetrikaCounterAuditor extends Auditor
{
    public function match() : bool
    {
        $campaigns = $this->manager->getCampaigns();

        foreach ($campaigns as $id => $campaign) {
            $fields = $this->manager->getTypeFields($campaign);

            if (empty($fields->CounterIds->Items)) {
                $this->errors[] = $campaign;
                $this->totalErrors++;
            }
        }

        if (!empty($this->errors)) {
            $percent = ceil($this->totalErrors / $campaigns->count() * 100);
            
            $this->result = [
                'message' => 'В ' . $this->totalErrors . ' ' . \Decline($this->totalErrors, ['кампании', 'кампаниях', 'кампаниях']) . ' (' . $percent . '%) не указан счетчик Метрики',
                'modal'   => $this->manager->render('campaigns_common.twig', [
                    'errors' => $this->errors,
                ]),
            ];

            return $percent <= $this->model->threshold;
        }

        return true;
    }
}
