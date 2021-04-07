<?php

namespace Mnoskov\Auditor\Auditors;

class MustHavePriorityGoalsAuditor extends Auditor
{
    public function match() : bool
    {
        $campaigns = $this->manager->getCampaigns();
        $totalCampaigns = 0;

        foreach ($campaigns as $id => $campaign) {
            if (!in_array($campaign->Type, ['TEXT_CAMPAIGN', 'DYNAMIC_TEXT_CAMPAIGN', 'SMART_CAMPAIGN'])) {
                continue;
            }

            $totalCampaigns++;

            $fields = $this->manager->getTypeFields($campaign);

            if (empty($fields->PriorityGoals->Items)) {
                $this->errors[] = $campaign;
                $this->totalErrors++;
            }
        }

        if (!empty($this->errors)) {
            $percent = ceil($this->totalErrors / $totalCampaigns * 100);

            $this->result = [
                'message' => 'В ' . $this->totalErrors . ' ' . \Decline($this->totalErrors, ['кампании', 'кампаниях', 'кампаниях']) . ' (' . $percent . '%) не заданы ключевые цели',
                'modal'   => $this->manager->render('campaigns_common.twig', [
                    'errors' => $this->errors,
                ]),
            ];

            return $percent <= $this->model->threshold;
        }

        return true;
    }
}
