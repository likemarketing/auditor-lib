<?php

namespace Mnoskov\Auditor\Auditors;

class RetargetingAuditor extends Auditor
{
    public function match() : bool
    {
        $campaigns = $this->manager->getCampaigns();

        $hasRetargeting = false;

        foreach ($campaigns->chunk(10) as $chunk) {
            $raw = $this->manager->getCachedRequest('getAudienceTargets', [
                'ClientLogin' => $this->manager->getClient()->login,
                'SelectionCriteria' => [
                    'CampaignIds' => $chunk->keys()->toArray(),
                ],
                'FieldNames' => ['Id', 'AdGroupId', 'CampaignId', 'RetargetingListId', 'InterestId', 'ContextBid', 'StrategyPriority', 'State'],
            ]);

            if (!empty($raw->AudienceTargets)) {
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
