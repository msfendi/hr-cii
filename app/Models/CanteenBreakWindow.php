<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CanteenBreakWindow extends Model
{
    protected $table = 'canteen_break_windows';

    protected $fillable = [
        'name',
        'time_start',
        'time_end',
        'sort_order',
        'is_active',
    ];

    protected $casts = [
        'time_start' => 'datetime:H:i',
        'time_end'   => 'datetime:H:i',
        'sort_order' => 'integer',
        'is_active'  => 'boolean',
    ];

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public static function currentWindow(): ?self
    {
        $now = now()->format('H:i:s');
        return static::active()
            ->where('time_start', '<=', $now)
            ->where('time_end', '>=', $now)
            ->orderBy('sort_order')
            ->first();
    }
}
