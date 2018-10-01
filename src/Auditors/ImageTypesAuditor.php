<?php

namespace Mnoskov\Auditor\Auditors;

class ImageTypesAuditor extends Auditor
{
    public function match() : bool
    {
        $campaigns = $this->manager->getCampaigns();
        $groups    = $this->manager->getAdGroups();
        $ads       = $this->manager->getAds();
        $client    = $this->manager->getClient();
        
        $groupedAds  = $ads->groupBy('CampaignId');
        $totalGroups = $groups->count();
        $adsHashes   = [];
        $hashes      = [];

        $api = $this->ci->api;

        foreach ($groupedAds as $campaignId => $campaignAds) {
            if (!$campaigns->has($campaignId)) {
                $groupedAds->forget($campaignId);
                continue;
            }

            $campaign = $campaigns->get($campaignId);

            if ($this->manager->isSearchCampaign($campaign)) {
                foreach ($campaignAds as $ad) {
                    $hash = $this->getHash($ad);
                    if ($hash) {
                        $adsHashes[$ad->Id] = $hash;
                    }
                }
            }
        }

        foreach (array_chunk(array_values(array_unique($adsHashes)), 10000, true) as $hashGroup) {
            $data = $api->getAdImages([
                'ClientLogin' => $client->login,
                'SelectionCriteria' => [
                    'AdImageHashes' => $hashGroup,
                ],
                'FieldNames' => ['AdImageHash', 'Type'],
            ]);

            if (!$api->isError() && !empty($data->AdImages)) {
                foreach ($data->AdImages as $row) {
                    if (in_array($row->Type, ['REGULAR', 'WIDE'])) {
                        $hashes[$row->AdImageHash] = $row->Type;
                    }
                }
            }
        }

        foreach ($groupedAds as $campaignId => $campaignAds) {
            foreach ($campaignAds->groupBy('AdGroupId') as $groupId => $groupAds) {
                if ($groups->has($groupId)) {
                    $group = $groups->get($groupId);
                    $hasWide = $hasRegular = false;

                    foreach ($groupAds as $ad) {
                        $hash = $this->getHash($ad);

                        if ($hash && isset($hashes[$hash])) {
                            if ($hashes[$hash] == 'REGULAR') {
                                $hasRegular = true;
                            }

                            if ($hashes[$hash] == 'WIDE') {
                                $hasWide = true;
                            }
                        }
                    }

                    if (!$hasWide || !$hasRegular) {
                        if (!isset($this->errors[$campaignId])) {
                            $this->errors[$campaignId] = [];
                        }

                        $this->errors[$campaignId][] = $group;
                        $this->totalErrors++;
                    }
                }
            }
        }

        if (!empty($this->errors)) {
            $percent = ceil($this->totalErrors / $totalGroups * 100);

            $this->result = [
                'message' => $this->totalErrors . ' ' . \Decline($this->totalErrors, ['группа', 'группы', 'групп']) . ' объявлений (' . $percent . '%) не ' . \Decline($this->totalErrors, ['имеет', 'имеют', 'имеют']) . ' стандартного или широкоформатого изображения',
                'modal'   => $this->manager->render('groups_common.twig', [
                    'errors'    => $this->errors,
                    'campaigns' => $campaigns,
                ]),
            ];

            return $percent <= $this->model->threshold;
        }

        return true;
    }

    private function getHash($ad)
    {
        $hash = null;

        if (in_array($ad->Type, ['TEXT_AD', 'MOBILE_APP_AD', 'DYNAMIC_TEXT_AD', 'TEXT_IMAGE_AD', 'MOBILE_APP_IMAGE_AD'])) {
            $fields = $this->manager->getTypeFields($ad);

            if (!empty($fields->AdImageHash)) {
                return $fields->AdImageHash;
            }
        }

        return $hash;
    }
}
