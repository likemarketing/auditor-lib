<?php

namespace Mnoskov\Auditor\Models;

class Auditor extends \Illuminate\Database\Eloquent\Model
{
    public $timestamps = false;

    public function group()
    {
        return $this->belongsTo('\Mnoskov\Auditor\Models\AuditorGroup', 'group_id');
    }
}
