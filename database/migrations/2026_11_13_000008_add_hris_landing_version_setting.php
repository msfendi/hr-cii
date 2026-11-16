<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Menambah setelan `landing_version` di tabel hris_v2_settings.
 *
 * Dipakai halaman /pengaturan/card-menu-hris untuk memilih tampilan route '/':
 *   v1 (default) -> resources/views/hris.blade.php      (tampilan lama)
 *   v2           -> resources/views/hris_v2/portal.blade.php (Portal HRIS v2)
 *
 * Idempoten: aman dijalankan berulang dan tidak menimpa pilihan admin
 * yang sudah tersimpan.
 */
return new class extends Migration
{
    public function up(): void
    {
        // Tabel dibuat oleh migration 2026_11_12_000003_create_hris_v2_menus_table.
        if (! Schema::hasTable('hris_v2_settings')) {
            return;
        }

        if (! DB::table('hris_v2_settings')->where('key', 'landing_version')->exists()) {
            DB::table('hris_v2_settings')->insert([
                'key' => 'landing_version',
                'value' => 'v1',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('hris_v2_settings')) {
            DB::table('hris_v2_settings')->where('key', 'landing_version')->delete();
        }
    }
};
