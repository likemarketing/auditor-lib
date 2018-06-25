<?php

namespace Bidder\Auditors;

class SiteLinksDescriptionsAuditor extends Auditor
{
    public function match() : bool
    {
        $campaigns = $this->manager->getCampaigns();
        $groups    = $this->manager->getAdGroups();
        $ads       = $this->manager->getAds();
        $client    = $this->manager->getClient();
        $totalAds  = $ads->count();

        $groupedAds = $ads->groupBy('CampaignId');

        $api = $this->ci->api;
        $ids = [];
        $sets = [];

        foreach ($groupedAds as $campaignId => $campaignAds) {
            if (!$campaigns->has($campaignId)) {
                $groupedAds->forget($campaignId);
                continue;
            }

            $campaign = $campaigns->get($campaignId);

            if (isset($campaign->TextCampaign->BiddingStrategy->Search->BiddingStrategyType)) {
                if ($campaign->TextCampaign->BiddingStrategy->Search->BiddingStrategyType == 'SERVING_OFF') {
                    $groupedAds->forget($campaignId);
                    continue;
                }

                foreach ($campaignAds as $ad) {
                    if (!empty($ad->TextAd->SitelinkSetId)) {
                        $ids[$ad->Id] = $ad->TextAd->SitelinkSetId;
                    }
                }
            }
        }

        foreach (array_chunk(array_values(array_unique($ids)), 10000, true) as $chunk) {
            $data = $api->getSitelinks([
                'ClientLogin' => $client->login,
                'SelectionCriteria' => [
                    'Ids' => $chunk,
                ],
                'FieldNames' => ['Id', 'Sitelinks'],
            ]);

            if (!$api->isError()) {
                foreach ($data->SitelinksSets as $row) {
                    $sets[$row->Id] = $row->Sitelinks;
                }
            }
        }

        foreach ($groupedAds as $campaignId => $campaignAds) {
            foreach ($campaignAds as $ad) {
                if (!empty($ids[$ad->Id])) {
                    if (!empty($sets[$ids[$ad->Id]])) {
                        $hasDescriptions = true;

                        foreach ($sets[$ids[$ad->Id]] as $set) {
                            if (empty($set->Description)) {
                                $hasDescriptions = false;
                                break;
                            }
                        }

                        if ($hasDescriptions) {
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
                'message' => $this->totalErrors . ' ' . \Decline($this->totalErrors, ['объявление', 'объявления', 'объявлений']) . ' (' . $percent . '%) не ' . \Decline($this->totalErrors, ['имеет', 'имеют', 'имеют']) . ' описаний быстрых ссылок',
                'modal'   => $this->view->render('audit/ads_common.twig', [
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
