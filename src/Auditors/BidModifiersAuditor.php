<?php

namespace Mnoskov\Auditor\Auditors;

use Illuminate\Support\Collection;

class BidModifiersAuditor extends Auditor
{
    public function match() : bool
    {
        $campaigns = $this->manager->getCampaigns()->filter(function($campaign) {
            return $campaign->Type != 'CPM_BANNER_CAMPAIGN';
        });

        $modifiers = [];

        foreach ($campaigns->chunk(10) as $chunk) {
            $raw = $this->manager->getCachedRequest('getBidModifiers', [
                'ClientLogin' => $this->manager->getClient()->login,
                'SelectionCriteria' => [
                    'CampaignIds' => $chunk->keys()->toArray(),
                    'Levels' => ['CAMPAIGN', 'AD_GROUP'],
                ],
                'FieldNames' => ['CampaignId'],
            ]);

            if (isset($raw->BidModifiers)) {
                $modifiers = array_merge($modifiers, $raw->BidModifiers);
            }
        }
        
        $modifiers = array_flip((new Collection($modifiers))->pluck('CampaignId')->toArray());

        foreach ($campaigns as $campaign) {
            if (!array_key_exists($campaign->Id, $modifiers)) {
                $this->errors[] = $campaign;
                $this->totalErrors++;
            }
        }

        if (!empty($this->errors)) {
            $percent = ceil($this->totalErrors / $campaigns->count() * 100);

            $this->result = [
                'message' => $this->totalErrors . ' ' . \Decline($this->totalErrors, ['кампания', 'кампании', 'кампаний']) . ' (' . $percent . '%) не ' . \Decline($this->totalErrors, ['использует', 'используют', 'используют']) . ' корректировки ставок',
                'modal' => $this->manager->render('campaigns_common.twig', [
                    'errors' => $this->errors,
                ]),
            ];

            return $percent <= $this->model->threshold;
        }

        return true;
    }
}
