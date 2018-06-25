<?php

namespace Bidder\Auditors;

class RelevantPhrasesAuditor extends Auditor
{
    public function match() : bool
    {
        $campaigns = $this->manager->getCampaigns();

        foreach ($campaigns as $id => $campaign) {
            if (!empty($campaign->TextCampaign->RelevantKeywords)) {
                $this->errors[] = $campaign;
                $this->totalErrors++;
            }
        }

        if (!empty($this->errors)) {
            $percent = ceil($this->totalErrors / $campaigns->count() * 100);
            
            $this->result = [
                'message' => 'В ' . $this->totalErrors . ' ' . \Decline($this->totalErrors, ['кампании', 'кампаниях', 'кампаниях']) . ' (' . $percent . '%) включены показы по дополнительным релевантным фразам',
                'modal'   => $this->view->render('audit/campaigns_common.twig', [
                    'errors' => $this->errors,
                ]),
            ];

            return $percent <= $this->model->threshold;
        }

        return true;
    }
}
