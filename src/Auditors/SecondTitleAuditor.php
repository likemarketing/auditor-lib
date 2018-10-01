<?php

namespace Mnoskov\Auditor\Auditors;

class SecondTitleAuditor extends Auditor
{
    public function match() : bool
    {
        $campaigns = $this->manager->getCampaigns();
        $groups    = $this->manager->getAdGroups();
        $ads       = $this->manager->getAds();
        $totalAds  = $ads->count();

        $ads = $ads->filter(function($ad) {
            return $ad->Type == 'TEXT_AD';
        });

        foreach ($ads->groupBy('CampaignId') as $campaignId => $campaignAds) {
            if (!$campaigns->has($campaignId)) {
                continue;
            }

            $campaign = $campaigns->get($campaignId);

            if ($this->manager->isSearchCampaign($campaign)) {
                foreach ($campaignAds as $ad) {
                    if (empty($ad->TextAd->Title2)) {
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
                'message' => $this->totalErrors . ' ' . \Decline($this->totalErrors, ['объявление', 'объявления', 'объявлений']) . ' (' . $percent . '%) не ' . \Decline($this->totalErrors, ['имеет', 'имеют', 'имеют']) . ' второго заголовка',
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
