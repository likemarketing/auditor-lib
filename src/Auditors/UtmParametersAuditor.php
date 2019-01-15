<?php

namespace Mnoskov\Auditor\Auditors;

class UtmParametersAuditor extends Auditor
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

            foreach ($campaignAds as $ad) {
                $query = parse_url($ad->TextAd->Href, PHP_URL_QUERY);

                if (is_null($query) || !is_null($query) && strpos($query, 'utm_') === false) {
                    if (!isset($this->errors[$campaignId])) {
                        $this->errors[$campaignId] = [];
                    }

                    $this->errors[$campaignId][] = $ad;
                    $this->totalErrors++;
                }
            }
        }

        if (!empty($this->errors)) {
            $percent = ceil($this->totalErrors / $totalAds * 100);

            $this->result = [
                'message' => $this->totalErrors . ' ' . \Decline($this->totalErrors, ['объявление', 'объявления', 'объявлений']) . ' (' . $percent . '%) не ' . \Decline($this->totalErrors, ['имеет', 'имеют', 'имеют']) . ' UTM-меток',
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
