<?php

namespace Bidder\Auditors;

class FewImpressionsAuditor extends Auditor
{
    public function match() : bool
    {
        $campaigns = $this->manager->getCampaigns();
        $groups    = $this->manager->getAdGroups();
        $ads       = $this->manager->getAds();

        $totalGroups = $groups->count();

        foreach ($ads->groupBy('AdGroupId') as $groupId => $groupAds) {
            if (!$groups->has($groupId)) {
                continue;
            }

            // т.к. группы объявлений не имеют своего статуса,
            // перебираем объявления группы, чтобы определить, в архиве группа или нет
            $isArchive = true;

            foreach ($groupAds as $ad) {
                if ($ad->State != 'ARCHIVED') {
                    $isArchive = false;
                    break;
                }
            }

            if (!$isArchive) {
                $group = $groups->get($groupId);

                if (!$campaigns->has($group->CampaignId)) {
                    continue;
                }

                if ($group->ServingStatus == 'RARELY_SERVED') {
                    if (!isset($this->errors[$group->CampaignId])) {
                        $this->errors[$group->CampaignId] = [];
                    }

                    $this->errors[$group->CampaignId][] = $group;
                    $this->totalErrors++;
                }
            }
        }

        if (!empty($this->errors)) {
            $percent = ceil($this->totalErrors / $totalGroups * 100);
            
            $this->result = [
                'message' => $this->totalErrors . ' ' . \Decline($this->totalErrors, ['группа', 'группы', 'групп']) . ' объявлений (' . $percent . '%) ' . \Decline($this->totalErrors, ['имеет', 'имеют', 'имеют']) . '  статус "Мало показов"',
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
