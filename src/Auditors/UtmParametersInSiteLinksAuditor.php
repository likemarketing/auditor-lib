<?php

namespace Mnoskov\Auditor\Auditors;

class UtmParametersInSiteLinksAuditor extends Auditor
{
    public function match() : bool
    {
        $campaigns = $this->manager->getCampaigns();
        $groups    = $this->manager->getAdGroups();
        $ads       = $this->manager->getAds();
        $client    = $this->manager->getClient();
        $totalAds  = $ads->count();

        $ads = $ads->filter(function($ad) {
            return in_array($ad->Type, ['TEXT_AD', 'DYNAMIC_TEXT_AD']);
        });

        $groupedAds = $ads->groupBy('CampaignId');

        $ids = [];
        $sets = [];

        foreach ($groupedAds as $campaignId => $campaignAds) {
            if (!$campaigns->has($campaignId)) {
                $groupedAds->forget($campaignId);
                continue;
            }

            $campaign = $campaigns->get($campaignId);

            foreach ($campaignAds as $ad) {
                $fields = $this->manager->getTypeFields($ad);

                if (!empty($fields->SitelinkSetId)) {
                    $ids[$ad->Id] = $fields->SitelinkSetId;
                }
            }
        }

        foreach (array_chunk(array_values(array_unique($ids)), 10000, true) as $chunk) {
            $data = $this->manager->getCachedRequest('getSitelinks', [
                'ClientLogin' => $client->login,
                'SelectionCriteria' => [
                    'Ids' => $chunk,
                ],
                'FieldNames' => ['Id', 'Sitelinks'],
            ]);

            if (!empty($data->SitelinksSets)) {
                foreach ($data->SitelinksSets as $row) {
                    $sets[$row->Id] = $row->Sitelinks;
                }
            }
        }

        foreach ($groupedAds as $campaignId => $campaignAds) {
            foreach ($campaignAds as $ad) {
                if (!empty($ids[$ad->Id])) {
                    if (!empty($sets[$ids[$ad->Id]])) {
                        $hasUtm = true;

                        foreach ($sets[$ids[$ad->Id]] as $set) {
                            $query = parse_url($set->Href, PHP_URL_QUERY);

                            if (is_null($query) || !is_null($query) && strpos($query, 'utm_') === false) {
                                $hasUtm = false;
                                break;
                            }
                        }

                        if ($hasUtm) {
                            continue;
                        }
                    }
                }

                if (!isset($this->errors[$campaignId])) {
                    $this->errors[$campaignId] = [];
                }

                $this->errors[$campaignId][] = $ad;
                $this->totalErrors++;
            }
        }

        if (!empty($this->errors)) {
            $percent = ceil($this->totalErrors / $totalAds * 100);

            $this->result = [
                'message' => $this->totalErrors . ' ' . \Decline($this->totalErrors, ['объявление', 'объявления', 'объявлений']) . ' (' . $percent . '%) не ' . \Decline($this->totalErrors, ['имеет', 'имеют', 'имеют']) . ' UTM-меток в быстрых ссылках',
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
