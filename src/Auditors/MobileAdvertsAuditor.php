<?php

namespace Mnoskov\Auditor\Auditors;

class MobileAdvertsAuditor extends Auditor
{
    public function match() : bool
    {
        $campaigns = $this->manager->getCampaigns();
        $groups    = $this->manager->getAdGroups();
        $ads       = $this->manager->getAds();

        $ads = $ads->filter(function($ad) {
            return $ad->Type == 'TEXT_AD';
        });

        $totalGroups = 0;

        foreach ($ads->groupBy('AdGroupId') as $groupId => $groupAds) {
            if (!$groups->has($groupId)) {
                continue;
            }

            $group = $groups->get($groupId);

            if (!$campaigns->has($group->CampaignId)) {
                continue;
            }

            $hasMobile = false;

            foreach ($groupAds as $ad) {
                if ($ad->TextAd->Mobile == 'YES') {
                    $hasMobile = true;
                    break;
                }
            }

            if (!$hasMobile) {
                $campaignId = $campaigns->get($group->CampaignId)->Id;

                if (!isset($this->errors[$campaignId])) {
                    $this->errors[$campaignId] = [];
                }

                $this->errors[$campaignId][$groupId] = $group;
                $this->totalErrors++;
            }

            $totalGroups++;
        }

        if (!empty($this->errors)) {
            $percent = ceil($this->totalErrors / $totalGroups * 100);
            
            $this->result = [
                'message' => $this->totalErrors . ' ' . \Decline($this->totalErrors, ['группа', 'группы', 'групп']) . ' объявлений (' . $percent . '%) не ' . \Decline($this->totalErrors, ['имеет', 'имеют', 'имеют']) . ' мобильных объявлений',
                'modal'   => $this->manager->render('groups_common.twig', [
                    'errors'    => $this->errors,
                    'campaigns' => $campaigns,
                ]),
            ];

            return $percent <= $this->model->threshold;
        }

        return true;
    }
}
