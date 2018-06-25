<?php

namespace Mnoskov\Auditor\Models;

class AuditorGroup extends \Illuminate\Database\Eloquent\Model
{
    public $timestamps = false;

    public function auditors()
    {
        return $this->hasMany('\Mnoskov\Auditor\Models\Auditor', 'group_id');
    }
}
