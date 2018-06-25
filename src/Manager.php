<?php

namespace Mnoskov\Auditor;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Mnoskov\Auditor\Models\AuditorGroup;
use Mnoskov\Auditor\Models\Auditor;
use Slim\Container;

class Manager
{
    private $api;
    private $ci;
    private $view;
    private $client;
    private $campaigns = null;
    private $adGroups = null;
    private $ads = null;
    private $keywords = null;
    
    public function __construct(Container $ci, \Twig_Environment $view)
    {
        $this->ci   = $ci;
        $this->api  = $ci->api;
        $this->view = $view;
    }

    public function getView()
    {
        return $this->view;
    }

    public function getClient()
    {
        return $this->client;
    }

    public function getContainer()
    {
        return $this->ci;
    }

    public function runTests(Model $client, array $classnames = [])
    {
        $this->client = $client;

        $results = [
            'groups'  => [],
            'results' => [],
        ];

        $errors = 0;
        $total  = 0;

        $mute = !is_null($this->ci->get('request')->getParam('mute'));

        $groups = AuditorGroup::orderBy('sort')->with(['auditors' => function($query) use ($classnames) {
            if (!empty($classnames)) {
                $query->whereIn('class', $classnames);
            }

            return $query->orderBy('sort');
        }]);

        if (!empty($classnames)) {
            $groups->whereHas('auditors', function($query) use ($classnames) {
                $query->whereIn('class', $classnames);
            });
        }

        foreach ($groups->get() as $group) {
            $results['groups'][$group->id] = [
                'model'   => $group,
                'results' => [],
            ];

            foreach ($group->auditors as $model) {
                $classname = '\\Mnoskov\\Auditor\\Auditors\\' . $model->class . 'Auditor';

                if (class_exists($classname)) {
                    $auditor = new $classname($this, $model);

                    $row = [
                        'model' => $model,
                    ];

                    if (!$auditor->match()) {
                        $row['isError'] = true;
                        $errors++;
                    }

                    $row['errors'] = $auditor->getResult();

                    if (!$mute && !empty($row['isError']) && $model->critical && $this->errorsProcessor) {
                        call_user_func($this->errorsProcessor, $this, $row);
                    }

                    $results['groups'][$group->id]['results'][] = $row;
                    $total++;
                }
            }
        }

        if (empty($classnames)) {
            $percent = $total ? ceil($errors / $total * 100) : 0;

            $this->client->auditor_errors = $percent;
            $this->client->tested_at = (new \DateTime())->format('Y-m-d H:i:s');
        } else {
            $this->client->fast_tested_at = (new \DateTime())->format('Y-m-d H:i:s');
        }

        $this->client->save();

        $results['total']  = $total;
        $results['errors'] = $errors;

        return $results;
    }

    public function getResults()
    {
        return $this->view->render('audit/summary.twig', [
            'results' => $this->runTests(),
        ]);
    }

    public function getCampaigns()
    {
        if (is_null($this->campaigns)) {
            $this->campaigns = new Collection([]);

            $raw = $this->api->getCampaigns([
                'ClientLogin' => $this->client->login,
                'SelectionCriteria' => [
                    'States' => ['ON', 'OFF', 'ENDED', 'SUSPENDED'],
                ],
                'FieldNames' => ['Id', 'Name', 'NegativeKeywords', 'State', 'Status', 'Type'],
                'TextCampaignFieldNames' => ['CounterIds', 'RelevantKeywords', 'Settings', 'BiddingStrategy'],    
            ]);

            if (!$this->api->isError() && !empty($raw->Campaigns)) {
                $this->campaigns = $this->campaigns->merge($raw->Campaigns)->keyBy('Id');
            }
        }

        return $this->campaigns;
    }

    public function getAds()
    {
        if (is_null($this->ads)) {
            $campaigns = $this->getCampaigns();
            $all = new Collection([]);

            foreach ($campaigns->chunk(10) as $chunk) {
                $raw = $this->api->getAds([
                    'ClientLogin' => $this->client->login,
                    'SelectionCriteria' => [
                        'CampaignIds' => $chunk->keys(),
                        'Types' => ['TEXT_AD'],
                        'States' => ['OFF_BY_MONITORING', 'ON', 'OFF', 'SUSPENDED'],
                    ],
                    'FieldNames' => ['Id', 'CampaignId', 'AdGroupId', 'State', 'Status', 'Type'],
                    'TextAdFieldNames' => ['Title', 'Title2', 'Text', 'Href', 'Mobile', 'VCardId', 'SitelinkSetId', 'AdImageHash', 'AdExtensions'],
                    'TextImageAdFieldNames' => ['AdImageHash', 'Href'],
                ]);

                if (!$this->api->isError() && !empty($raw->Ads)) {
                    $all = $all->merge($raw->Ads);
                }
            }
            $this->ads = $all->keyBy('Id');
        }

        return $this->ads;
    }

    public function getAdGroups()
    {
        if (is_null($this->adGroups)) {
            $campaigns = $this->getCampaigns();
            $all = new Collection([]);

            foreach ($campaigns->chunk(10) as $chunk) {
                $raw = $this->api->getAdGroups([
                    'ClientLogin' => $this->client->login,
                    'SelectionCriteria' => [
                        'CampaignIds' => $chunk->keys(),
                        'Types' => ['TEXT_AD_GROUP'],
                    ],
                    'FieldNames' => ['Id', 'CampaignId', 'Name', 'NegativeKeywords', 'Type', 'Status', 'RegionIds', 'RestrictedRegionIds', 'ServingStatus'],
                ]);

                if (!$this->api->isError() && !empty($raw->AdGroups)) {
                    $all = $all->merge($raw->AdGroups);
                }
            }
            $this->adGroups = $all->keyBy('Id');
        }

        return $this->adGroups;
    }
}