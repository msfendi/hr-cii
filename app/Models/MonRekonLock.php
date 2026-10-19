<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class MonRekonLock extends Model
{
    public const TYPE_PARTIAL   = 'partial';
    public const TYPE_SHORTSHIP = 'shortship';
    public const TYPE_COMPLETE  = 'complete';

    /** Mode lock: per OCF atau per CPO (uraian). `ocf_no` menyimpan kunci sesuai mode. */
    public const MODE_OCF = 'ocf';
    public const MODE_CPO = 'cpo';

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

    public static function modes(): array
    {
        return [self::MODE_OCF, self::MODE_CPO];
    }

    /** Normalisasi input mode; default 'ocf'. */
    public static function normalizeMode(?string $mode): string
    {
        $mode = strtolower(trim((string) $mode));

        return in_array($mode, self::modes(), true) ? $mode : self::MODE_OCF;
    }

    public static function modeLabel(string $mode): string
    {
        return $mode === self::MODE_CPO ? 'CPO' : 'OCF';
    }

    /** Kunci lock: kode CPO (mode cpo) atau nomor OCF (mode ocf). */
    public function getLockKeyAttribute(): ?string
    {
        return $this->lock_mode === self::MODE_CPO ? $this->cpo_no : $this->ocf_no;
    }

    public function scopeMode(Builder $query, string $mode): Builder
    {
        return $query->where('lock_mode', self::normalizeMode($mode));
    }
}