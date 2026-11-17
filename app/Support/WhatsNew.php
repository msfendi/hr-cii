<?php

namespace App\Support;

use App\Models\WhatsNewItem;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Schema;

/**
 * Sumber data tunggal untuk slider What's New (v1 + v2).
 *
 * Kenapa tidak query langsung di Blade: layout v1 dan v2 dipakai hampir
 * semua halaman, jadi query harus (a) di-cache per request supaya tidak
 * berulang, dan (b) tidak boleh menjatuhkan halaman bila DB/instance
 * sedang bermasalah — banner adalah pelengkap, bukan syarat halaman hidup.
 */
class WhatsNew
{
    /** @var Collection<int, WhatsNewItem>|null */
    private static ?Collection $cache = null;

    /** Jumlah banner maksimum yang ditampilkan pada slider. */
    public const MAKS = 6;

    /** @return Collection<int, WhatsNewItem> */
    public static function aktif(): Collection
    {
        if (self::$cache !== null) {
            return self::$cache;
        }

        try {
            if (! Schema::hasTable('whats_new_items')) {
                return self::$cache = collect();
            }

            return self::$cache = WhatsNewItem::query()->aktif()->limit(self::MAKS)->get();
        } catch (\Throwable $e) {
            return self::$cache = collect();
        }
    }

    /**
     * Sidik jari isi banner. Dipakai untuk tombol tutup: begitu admin
     * mengubah/menambah banner, sidik berubah sehingga slider muncul
     * kembali meski sebelumnya ditutup pengguna.
     */
    public static function sidik(Collection $items): string
    {
        if ($items->isEmpty()) {
            return 'kosong';
        }

        $potongan = $items->map(function (WhatsNewItem $item) {
            return $item->id . ':' . optional($item->updated_at)->timestamp;
        })->implode(',');

        return substr(sha1($potongan), 0, 16);
    }

    /** Reset cache — dipakai unit test antar skenario. */
    public static function lupakan(): void
    {
        self::$cache = null;
    }
}