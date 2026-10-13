<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class MonRekonLock extends Model
{
    public const TYPE_PARTIAL   = 'partial';
    public const TYPE_SHORTSHIP = 'shortship';
    public const TYPE_COMPLETE  = 'complete';

    protected $table = 'mon_rekon_locks';

    protected $guarded = ['id'];

    protected $casts = [
        'lock_date'         => 'date',
        'locked_at'         => 'datetime',
        'grade_imported_at' => 'datetime',
    ];

    public function fabrics(): HasMany
    {
        return $this->hasMany(MonRekonLockFabric::class, 'lock_id');
    }

    public static function types(): array
    {
        return [self::TYPE_PARTIAL, self::TYPE_SHORTSHIP, self::TYPE_COMPLETE];
    }
}
