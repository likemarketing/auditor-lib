<?php

namespace Mnoskov\Auditor\Auditors;

class MoreThanOneCampaignsAuditor extends Auditor
{
    public function match() : bool
    {
        $campaigns = $this->manager->getCampaigns();

        if ($campaigns->count() < 2) {
            $this->result = [
                'message' => $campaigns->count() ? 'Кампания только одна' : 'Кампаний совсем нет',
            ];

            return false;
        }

        return true;
    }
}
