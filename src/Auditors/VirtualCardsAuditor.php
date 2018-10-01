<?php

namespace Mnoskov\Auditor\Auditors;

class VirtualCardsAuditor extends Auditor
{
    public function match() : bool
    {
        $campaigns = $this->manager->getCampaigns();
        $groups    = $this->manager->getAdGroups();
        $ads       = $this->manager->getAds();
        $totalAds  = $ads->count();

        foreach ($ads->groupBy('CampaignId') as $campaignId => $campaignAds) {
            if (!$campaigns->has($campaignId)) {
                continue;
            }

            foreach ($campaignAds as $ad) {
                if (in_array($ad, ['TEXT_AD', 'DYNAMIC_TEXT_AD'])) {
                    $fields = $this->manager->getTypeFields($ad);

                    if (empty($fields->VCardId)) {
                        if (!isset($this->errors[$campaignId])) {
                            $this->errors[$campaignId] = [];
                        }

                        $this->errors[$campaignId][] = $ad;
                        $this->totalErrors++;
                    }
                }
            }
        }

        if (!empty($this->errors)) {
            $percent = ceil($this->totalErrors / $totalAds * 100);

            $this->result = [
                'message' => $this->totalErrors . ' ' . \Decline($this->totalErrors, ['объявление', 'объявления', 'объявлений']) . ' (' . $percent . '%) не ' . \Decline($this->totalErrors, ['имеет', 'имеют', 'имеют']) . ' виртуальных визиток',
                'modal'   => $this->manager->render('ads_common.twig', [
                    'errors'    => $this->errors,
                    'groups'    => $groups,
                    'campaigns' => $campaigns,
                ]),
            ];

            return $percent <= $this->model->threshold;
        }

        return true;
    }
}
