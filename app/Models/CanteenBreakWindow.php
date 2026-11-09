<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Window istirahat kantin — SUMBERNYA tabel break_masters (existing).
 * Kolom: sesi (nama window), time_start/TIME, time_end/TIME.
 * Model ini TIDAK punya tabel sendiri; hanya adapter agar controller
 * canteen bisa pakai API lama (name/time_start/time_end/is_active/currentWindow)
 * tanpa mengubah logic transaksi.
 */
class CanteenBreakWindow extends Model
{
    protected $table = 'break_masters';

    protected $fillable = ['sesi', 'time_start', 'time_end'];

    protected $casts = [
        'time_start' => 'string',
        'time_end'   => 'string',
    ];

    // Alias agar kode lama yang membaca $w->name tetap jalan
    public function getNameAttribute(): ?string
    {
        return $this->sesi ?? null;
    }

    public function setNameAttribute($value): void
    {
        $this->attributes['sesi'] = $value;
    }

    // Di break_masters tidak ada is_active/sort_order — selalu aktif, urut by id
    public function scopeActive($query)
    {
        return $query;
    }

    public static function currentWindow(): ?self
    {
        $now = now()->format('H:i:s');
        return static::query()
            ->where('time_start', '<=', $now)
            ->where('time_end', '>=', $now)
            ->orderBy('id')
            ->first();
    }
}
