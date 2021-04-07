<?php

namespace Mnoskov\Auditor\Models;

class Auditor extends \Illuminate\Database\Eloquent\Model
{
    public $timestamps = false;

    public $fillable = [
        'class', 'group_id', 'title', 'description', 'explanation', 'threshold', 'critical', 'sort',
    ];

    public function group()
    {
        return $this->belongsTo('\Mnoskov\Auditor\Models\AuditorGroup', 'group_id');
    }
}
