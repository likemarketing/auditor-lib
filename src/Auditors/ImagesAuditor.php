<?php

namespace Mnoskov\Auditor\Auditors;

class ImagesAuditor extends Auditor
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
                if (!$this->hasHash($ad)) {
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
                'message' => $this->totalErrors . ' ' . \Decline($this->totalErrors, ['объявление', 'объявления', 'объявлений']) . ' (' . $percent . '%) не ' . \Decline($this->totalErrors, ['имеет', 'имеют', 'имеют']) . ' изображений',
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

    private function hasHash($ad)
    {
        if (!in_array($ad->Type, ['TEXT_AD', 'MOBILE_APP_AD', 'DYNAMIC_TEXT_AD', 'TEXT_IMAGE_AD', 'MOBILE_APP_IMAGE_AD'])) {
            return true;
        }

        $fields = $this->manager->getTypeFields($ad);
        return !empty($fields->AdImageHash);
    }
}
