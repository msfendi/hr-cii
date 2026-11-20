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

            return self::$cache = self::urutkan(
                WhatsNewItem::query()->aktif()->limit(self::MAKS)->get()
            );
        } catch (\Throwable $e) {
            return self::$cache = collect();
        }
    }

    /**
     * Pastikan urutan modal selalu "terbaru dulu": nilai urut terbesar lebih
     * dulu, lalu id terbesar, lalu updated_at terbaru.
     *
     * Query di scopeAktif() sudah mengurutkan di sisi SQL. Pengurutan ulang di
     * PHP ini menjaga aturan yang sama tetap berlaku untuk koleksi yang datang
     * dari cache atau dari pemanggil lain, dan supaya aturannya bisa diuji
     * tanpa koneksi database.
     *
     * @param  Collection<int, WhatsNewItem>  $items
     * @return Collection<int, WhatsNewItem>
     */
    public static function urutkan(Collection $items): Collection
    {
        return $items
            ->sortByDesc(function (WhatsNewItem $item) {
                return sprintf(
                    '%012d:%012d:%012d',
                    (int) $item->sort_order,
                    (int) $item->getKey(),
                    (int) optional($item->updated_at)->timestamp
                );
            })
            ->values();
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