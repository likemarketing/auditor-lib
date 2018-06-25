<?php

namespace Bidder\Auditors;

class MoreThanOneAdvertsAuditor extends Auditor
{
    public function match() : bool
    {
        $campaigns = $this->manager->getCampaigns();
        $groups    = $this->manager->getAdGroups();
        $ads       = $this->manager->getAds();

        $totalGroups = 0;

        foreach ($ads->groupBy('AdGroupId') as $groupId => $groupAds) {
            if (!$groups->has($groupId)) {
                continue;
            }

            $group = $groups->get($groupId);

            if (!$campaigns->has($group->CampaignId)) {
                continue;
            }

            if ($groupAds->count() == 1) {
                $campaignId = $campaigns->get($group->CampaignId)->Id;

                if (!isset($this->errors[$campaignId])) {
                    $this->errors[$campaignId] = [];
                }

                $this->errors[$campaignId][] = $group;
                $this->totalErrors++;
            }

            $totalGroups++;
        }

        if (!empty($this->errors)) {
            $percent = ceil($this->totalErrors / $totalGroups * 100);
            
            $this->result = [
                'message' => $this->totalErrors . ' ' . \Decline($this->totalErrors, ['группа', 'группы', 'групп']) . ' объявлений (' . $percent . '%) ' . \Decline($this->totalErrors, ['имеет', 'имеют', 'имеют']) . '  меньше двух объявлений',
                'modal'   => $this->view->render('audit/groups_common.twig', [
                    'errors'    => $this->errors,
                    'campaigns' => $campaigns,
                ]),
            ];

            return $percent <= $this->model->threshold;
        }

        return true;
    }
}
