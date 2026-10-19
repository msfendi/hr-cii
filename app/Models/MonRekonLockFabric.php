<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MonRekonLockFabric extends Model
{
    protected $table = 'mon_rekon_lock_fabrics';

    protected $guarded = ['id'];

    public function lock(): BelongsTo
    {
        return $this->belongsTo(MonRekonLock::class, 'lock_id');
    }
}
