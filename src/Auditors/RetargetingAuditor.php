<?php

namespace Bidder\Auditors;

class RetargetingAuditor extends Auditor
{
    public function match() : bool
    {
        $campaigns = $this->manager->getCampaigns();
        $api = $this->ci->api;

        $hasRetargeting = false;

        foreach ($campaigns->chunk(10) as $chunk) {
            $raw = $api->getAudienceTargets([
                'ClientLogin' => $this->manager->getClient()->login,
                'SelectionCriteria' => [
                    'CampaignIds' => $chunk->keys()->toArray(),
                ],
                'FieldNames' => ['Id', 'AdGroupId', 'CampaignId', 'RetargetingListId', 'InterestId', 'ContextBid', 'StrategyPriority', 'State'],
            ]);

            if (!$api->isError() && !empty($raw->AudienceTargets)) {
                $hasRetargeting = true;
                break;
            }
        }

        if (!$hasRetargeting) {
            $this->result = [
                'message' => 'Нет кампаний с настроенным ретаргетингом',
            ];

            return false;
        }

        return true;
    }
}
