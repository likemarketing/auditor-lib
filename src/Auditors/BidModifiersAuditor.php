<?php

namespace Mnoskov\Auditor\Auditors;

use Illuminate\Support\Collection;

class BidModifiersAuditor extends Auditor
{
    public function match() : bool
    {
        $campaigns = $this->manager->getCampaigns();
        $api = $this->ci->api;

        $modifiers = [];

        foreach ($campaigns->chunk(10) as $chunk) {
            $raw = $api->getBidModifiers([
                'ClientLogin' => $this->manager->getClient()->login,
                'SelectionCriteria' => [
                    'CampaignIds' => $chunk->keys()->toArray(),
                    'Levels' => ['CAMPAIGN', 'AD_GROUP'],
                ],
                'FieldNames' => ['CampaignId'],
            ]);

            if (!$api->isError() && isset($raw->BidModifiers)) {
                $modifiers = array_merge($modifiers, $raw->BidModifiers);
            }
        }
        
        if (!$api->isError() && isset($raw->BidModifiers)) {
            $modifiers = array_flip((new Collection($modifiers))->pluck('CampaignId')->toArray());

            foreach ($campaigns as $campaign) {
                if (!array_key_exists($campaign->Id, $modifiers)) {
                    $this->errors[] = $campaign;
                    $this->totalErrors++;
                }
            }
        }

        if (!empty($this->errors)) {
            $percent = ceil($this->totalErrors / $campaigns->count() * 100);

            $this->result = [
                'message' => sprintf('%s %s (%s%) не %s корректировки ставок', [
                    $this->totalErrors,
                    \Decline($this->totalErrors, ['кампания', 'кампании', 'кампаний']),
                    $percent,
                    \Decline($this->totalErrors, ['использует', 'используют', 'используют']),
                ]),
                'modal' => $this->view->render('audit/campaigns_common.twig', [
                    'errors' => $this->errors,
                ]),
            ];

            return $percent <= $this->model->threshold;
        }

        return true;
    }
}
