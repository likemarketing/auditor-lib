<?php

namespace Bidder\Auditors;

class SearchNetworkSeparatedAuditor extends Auditor
{
    public function match() : bool
    {
        $campaigns = $this->manager->getCampaigns();

        foreach ($campaigns as $id => $campaign) {
            $strategy = $campaign->TextCampaign->BiddingStrategy;

            $isSearch  = !empty($strategy->Search->BiddingStrategyType) && $strategy->Search->BiddingStrategyType != 'SERVING_OFF';
            $isNetwork = !empty($strategy->Network->BiddingStrategyType) && $strategy->Network->BiddingStrategyType != 'SERVING_OFF';

            if ($isSearch && $isNetwork) {
                $this->errors[] = $campaign;
                $this->totalErrors++;
            }
        }

        if (!empty($this->errors)) {
            $percent = ceil($this->totalErrors / $campaigns->count() * 100);
            
            $this->result = [
                'message' => $this->totalErrors . ' ' . \Decline($this->totalErrors, ['кампания', 'кампании', 'кампаний']) . ' (' . $percent . '%) ' . \Decline($this->totalErrors, ['запущена', 'запущены', 'запущены']) . ' одновременно на поиск и в РСЯ',
                'modal'   => $this->view->render('audit/campaigns_common.twig', [
                    'errors' => $this->errors,
                ]),
            ];

            return $percent <= $this->model->threshold;
        }

        return true;
    }
}
