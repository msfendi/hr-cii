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

    /**
     * PERBAIKAN V32 (2026-11-09) -- format jam HH:MM tanpa .0000000.
     *
     * Kolom time_start/time_end di break_masters bertipe `time`
     * (SQL Server membaca sebagai "12:15:00.0000000"). Cast 'string'
     * memberikan nilai mentah itu apa adanya, sehingga di
     * /canteen-transaction muncul "Break 3 (12:15:00.0000000 - 17:15:00.0000000)".
     *
     * Accessor dipusatkan di model supaya SEMUA pemakai (view canteen,
     * admin, json) dapat format yang sama: "12:15".
     */
    public static function formatTime($value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        $time = is_string($value) ? trim($value) : (string) $value;

        // time SQL Server: "HH:MM:SS.fffffff" -> "HH:MM"
        if (preg_match('/^(\d{1,2}):(\d{2})(?::\d{2}(?:\.\d+)?)?$/', $time, $m)) {
            return sprintf('%02d:%s', (int) $m[1], $m[2]);
        }

        // Objek Carbon / DateTime: format HH:MM
        if ($value instanceof \DateTimeInterface) {
            return \Carbon\Carbon::instance($value)->format('H:i');
        }

        return $time;
    }

    public function getTimeStartAttribute($value)
    {
        return self::formatTime($value);
    }

    public function getTimeEndAttribute($value)
    {
        return self::formatTime($value);
    }

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
        // PERBAIKAN V34 HOTFIX (2026-11-09) -- jangan 500 kalau DB
        // timeout / tabel break_masters tidak bisa dibaca. Kembalikan
        // null (dianggap di luar window) supaya controller canteen
        // menampilkan fallback yang sudah ada (daftar kantin kosong +
        // alert di luar jam istirahat) dan tidak crash.
        try {
            $now = now()->format('H:i:s');
            return static::query()
                ->where('time_start', '<=', $now)
                ->where('time_end', '>=', $now)
                ->orderBy('id')
                ->first();
        } catch (\Throwable $e) {
            \Log::warning('CanteenBreakWindow::currentWindow() gagal: ' . $e->getMessage());
            return null;
        }
    }
}
