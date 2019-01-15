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
    private $settings = [];
    private $campaigns = null;
    private $adGroups = null;
    private $ads = null;
    private $keywords = null;
    private $errorsProcessor;
    private $templatesLoader;
    
    public function __construct(Container $ci, \Twig_Environment $view)
    {
        $this->ci   = $ci;
        $this->api  = $ci->api;
        $this->view = $view;

        $this->templatesLoader = new \Twig_Loader_Filesystem();
        $this->templatesLoader->addPath(__DIR__ . '/../resources/templates');
    }

    public function getView()
    {
        return $this->view;
    }

    public function getClient()
    {
        return $this->client;
    }

    public function setClient(Model $client)
    {
        $this->client = $client;
        //$this->api->setTokenFromClient($client);
    }

    public function getContainer()
    {
        return $this->ci;
    }

    public function setSettings(array $settings = [])
    {
        $this->settings = $settings;
    }

    public function getSettings()
    {
        return $this->settings;
    }

    public function render($template, $data)
    {
        $loader = $this->view->getLoader();
        $this->view->setLoader($this->templatesLoader);
        $result = $this->view->render($template, $data);
        $this->view->setLoader($loader);
        return $result;
    }

    public function registerErrorsProcessor($processor)
    {
        $this->errorsProcessor = $processor;
    }

    public function runTests(array $classnames = [])
    {
        $this->campaigns = null;
        $this->adGroups  = null;
        $this->ads       = null;
        $this->keywords  = null;

        $results = [
            'groups'  => [],
            'results' => [],
        ];

        $errors = 0;
        $total  = 0;

        $groups = AuditorGroup::orderBy('sort')->with(['auditors' => function($query) {
            if (!empty($this->settings['classnames'])) {
                $query->whereIn('class', $this->settings['classnames']);
            }

            return $query->orderBy('sort');
        }]);

        if (!empty($this->settings['classnames'])) {
            $groups->whereHas('auditors', function($query) {
                $query->whereIn('class', $this->settings['classnames']);
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

                    if (empty($this->settings['mute']) && !empty($row['isError']) && $model->critical && $this->errorsProcessor) {
                        call_user_func($this->errorsProcessor, $this, $row);
                    }

                    $results['groups'][$group->id]['results'][] = $row;
                    $total++;
                }
            }
        }

        if (empty($this->settings['classnames'])) {
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

    public function getResults($tests = null)
    {
         if (!$tests) {
            $tests = $this->runTests();
        }

        return $this->render('summary.twig', [
            'results' => $tests,
        ]);
    }

    public function getCampaigns()
    {
        if (is_null($this->campaigns)) {
            $this->campaigns = new Collection([]);

            $raw = $this->api->getCampaigns([
                'ClientLogin' => $this->client->login,
                'SelectionCriteria' => [
                    'States' => !empty($this->settings['activeonly']) ? ['ON'] : ['ON', 'OFF', 'ENDED', 'SUSPENDED'],
                ],
                'FieldNames' => ['Id', 'Name', 'NegativeKeywords', 'State', 'Status', 'Type'],
                'TextCampaignFieldNames' => ['CounterIds', 'RelevantKeywords', 'Settings', 'BiddingStrategy'],
                'DynamicTextCampaignFieldNames' => ['CounterIds', 'Settings', 'BiddingStrategy'],
                'CpmBannerCampaignFieldNames' => ['CounterIds', 'Settings', 'BiddingStrategy'],
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
                        'States' => !empty($this->settings['activeonly']) ? ['ON'] : ['OFF_BY_MONITORING', 'ON', 'OFF', 'SUSPENDED'],
                    ],
                    'FieldNames' => ['Id', 'CampaignId', 'AdGroupId', 'State', 'Status', 'Type'],
                    'TextAdFieldNames' => ['Title', 'Title2', 'Text', 'Href', 'Mobile', 'VCardId', 'SitelinkSetId', 'AdImageHash', 'AdExtensions', 'DisplayUrlPath'],
                    'DynamicTextAdFieldNames' => ['Text', 'VCardId', 'VCardModeration', 'SitelinkSetId', 'SitelinksModeration', 'AdImageHash', 'AdImageModeration', 'AdExtensions'],
                    'TextImageAdFieldNames' => ['AdImageHash', 'Href'],
                    'CpmBannerAdBuilderAdFieldNames' => ['Href'],
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

    public function getTypeFields($resource)
    {
        switch ($resource->Type) {
            case 'DYNAMIC_TEXT_AD_GROUP': $type = 'DynamicText_' . $resource->Subtype . 'AdGroup'; break;
            case 'CPM_BANNER_AD_GROUP':   $type = 'CpmBanner_' . $resource->Subtype . 'AdGroup'; break;
            case 'CPM_BANNER_AD':         $type = 'CpmBannerAdBuilderAd'; break;
            case 'IMAGE_AD':              $type = strtolower($resource->Subtype); break;
            default:                      $type = strtolower($resource->Type);
        }

        $type = ucwords(str_replace('_', ' ', $type));
        $type = str_replace(' ', '', $type);

        if (isset($resource->{$type})) {
            return $resource->{$type};
        }

        return null;
    }

    public function isSearchCampaign($campaign)
    {
        $fields = $this->getTypeFields($campaign);

        if (!isset($fields->BiddingStrategy->Search->BiddingStrategyType)) {
            return false;
        }

        if ($fields->BiddingStrategy->Search->BiddingStrategyType == 'SERVING_OFF') {
            return false;
        }

        return true;
    }

    public function isNetworkCampaign($campaign)
    {
        $fields = $this->getTypeFields($campaign);

        if (!isset($fields->BiddingStrategy->Network->BiddingStrategyType)) {
            return false;
        }

        if ($fields->BiddingStrategy->Network->BiddingStrategyType == 'SERVING_OFF') {
            return false;
        }

        return true;
    }
}