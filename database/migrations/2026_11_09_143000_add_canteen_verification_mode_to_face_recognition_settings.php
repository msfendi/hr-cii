<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * MODE CANTEEN: LIVENESS ATAU RANDOM CHALLENGE (BUKAN KEDUANYA)
 * =============================================================
 *
 * Permintaan user (2026-11-09):
 *   "mode tersebut bisa memilih mau pakai liveness atau pakai random
 *    challenge bukan keduanya"
 *   "liveness dan random challenge untuk canteen transaction ada di
 *    table face_recognition_settings bukan canteen_settings"
 *
 * PERUBAHAN:
 *   1. Tambah kolom canteen_verification_mode (varchar 30, default
 *      'liveness') di face_recognition_settings.
 *      Nilai:
 *        'liveness'         -> kedip / menoleh
 *        'random_challenge' -> tantangan acak (kiri/kanan/buka mulut)
 *        'none'             -> tanpa verifikasi gerakan
 *
 *   2. Backfill nilai mode dari kolom boolean lama
 *      (liveness_canteen_enabled / random_challenge_canteen_enabled)
 *      supaya pengaturan admin yang sudah ada tidak hilang:
 *        liveness=true,  random=false -> 'liveness'
 *        random=true,    liveness=false -> 'random_challenge'
 *        kedua true -> 'liveness' (fail-safe: instruksi lama
 *                     "Lanjut ke verifikasi liveness" tetap benar)
 *        kedua false -> 'none'
 *
 *   3. Hapus kolom verifikasi dari canteen_settings:
 *        liveness_canteen_enabled
 *        is_random_challenge_enabled
 *        random_challenge_canteen_enabled
 *      sesuai permintaan "bukan canteen_settings". Kolom geomap
 *      (require_geomap, allowed_work_location_ids) TIDAK disentuh.
 *
 * Idempotent: semua operasi dicek hasTable/hasColumn dulu.
 */
return new class extends Migration
{
    public function up(): void
    {
        // ---------- 1. Tambah kolom mode di face_recognition_settings ----------
        if (Schema::hasTable('face_recognition_settings')
            && !Schema::hasColumn('face_recognition_settings', 'canteen_verification_mode')) {
            Schema::table('face_recognition_settings', function (Blueprint $table) {
                $table->string('canteen_verification_mode', 30)->default('liveness');
            });
        }

        // ---------- 2. Backfill dari kolom boolean lama ----------
        if (Schema::hasTable('face_recognition_settings')
            && Schema::hasColumn('face_recognition_settings', 'canteen_verification_mode')) {
            try {
                // Hanya backfill baris yang modenya masih null/kosong,
                // supaya nilai yang sudah dipilih admin tidak tertimpa.
                $rows = DB::table('face_recognition_settings')->get();

                foreach ($rows as $row) {
                    $mode = $row->canteen_verification_mode ?? null;
                    if ($mode !== null && $mode !== '') {
                        continue;
                    }

                    $liveness = $row->liveness_canteen_enabled ?? null;
                    $random = $row->random_challenge_canteen_enabled ?? null;

                    if ((bool) $random && !(bool) $liveness) {
                        $backfill = 'random_challenge';
                    } elseif (!(bool) $liveness && !(bool) $random && $liveness !== null && $random !== null) {
                        $backfill = 'none';
                    } else {
                        // Termasuk kasus kedua true (fail-safe liveness)
                        // dan kasus kolom lama null (default liveness).
                        $backfill = 'liveness';
                    }

                    DB::table('face_recognition_settings')
                        ->where('id', $row->id)
                        ->update(['canteen_verification_mode' => $backfill]);
                }
            } catch (\Throwable $e) {
                \Log::warning('Backfill canteen_verification_mode gagal: ' . $e->getMessage());
            }
        }

        // ---------- 3. Hapus kolom verifikasi dari canteen_settings ----------
        // canteen_settings hanya menyimpan geomap + fitur aktif sekarang.
        if (Schema::hasTable('canteen_settings')) {
            $deprecated = [
                'liveness_canteen_enabled',
                'is_random_challenge_enabled',
                'random_challenge_canteen_enabled',
            ];

            foreach ($deprecated as $column) {
                if (Schema::hasColumn('canteen_settings', $column)) {
                    try {
                        Schema::table('canteen_settings', function (Blueprint $table) use ($column) {
                            $table->dropColumn($column);
                        });
                    } catch (\Throwable $e) {
                        \Log::warning('Gagal drop kolom canteen_settings.' . $column . ': ' . $e->getMessage());
                    }
                }
            }
        }
    }

    public function down(): void
    {
        // Kembalikan kolom canteen_settings (nilai default).
        if (Schema::hasTable('canteen_settings')) {
            if (!Schema::hasColumn('canteen_settings', 'liveness_canteen_enabled')) {
                Schema::table('canteen_settings', function (Blueprint $table) {
                    $table->boolean('liveness_canteen_enabled')->default(true);
                });
            }
            if (!Schema::hasColumn('canteen_settings', 'is_random_challenge_enabled')) {
                Schema::table('canteen_settings', function (Blueprint $table) {
                    $table->boolean('is_random_challenge_enabled')->default(false);
                });
            }
            if (!Schema::hasColumn('canteen_settings', 'random_challenge_canteen_enabled')) {
                Schema::table('canteen_settings', function (Blueprint $table) {
                    $table->boolean('random_challenge_canteen_enabled')->default(false);
                });
            }
        }

        if (Schema::hasTable('face_recognition_settings')
            && Schema::hasColumn('face_recognition_settings', 'canteen_verification_mode')) {
            Schema::table('face_recognition_settings', function (Blueprint $table) {
                $table->dropColumn('canteen_verification_mode');
            });
        }
    }
};
