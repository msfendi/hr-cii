<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MonStageRemark extends Model
{
    protected $table = 'mon_stage_remarks';

    protected $fillable = [
        'ocf_no',
        'department_id',
        'remark',
    ];
}
