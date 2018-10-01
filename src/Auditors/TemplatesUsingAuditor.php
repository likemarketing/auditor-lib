<?php

namespace Mnoskov\Auditor\Auditors;

class TemplatesUsingAuditor extends Auditor
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

            $campaign = $campaigns->get($campaignId);

            if ($this->manager->isSearchCampaign($campaign)) {
                foreach ($campaignAds as $ad) {
                    if (substr_count($ad->TextAd->Title, '#') < 2) {
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
                'message' => $this->totalErrors . ' ' . \Decline($this->totalErrors, ['объявление', 'объявления', 'объявлений']) . ' (' . $percent . '%) не ' . \Decline($this->totalErrors, ['использует', 'используют', 'используют']) . ' шаблоны',
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
