<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Daftar durasi (maksimal hari per pengajuan) untuk jenis cuti non-saldo.
     * Kunci = code jenis cuti.
     */
    private const DURATIONS = [
        'menikah'                => 3,
        'melahirkan'             => 90,
        'keguguran'              => 45,
        'menikahkan_anak'        => 2,
        'istri_melahirkan'       => 2,
        'istri_keguguran'        => 2,
        'suami_istri_meninggal'  => 2,
        'keluarga_meninggal'     => 2,
        'pribadi'                => 365,
        'menghitankan_babtis_anak' => 2,
        'anak_meninggal'         => 2,
        'menantu_meninggal'      => 2,
        'orang_tua_meninggal'    => 2,
    ];

    public function up(): void
    {
        // 1) Set durasi per jenis cuti (idempotent, berbasis code)
        foreach (self::DURATIONS as $code => $days) {
            DB::table('leave_types')
                ->where('code', $code)
                ->update(['default_days' => $days]);
        }

        // 2) Simpan daftar tanggal kustom (JSON) untuk pengajuan cuti yang
        //    tanggalnya dipilih bebas (tidak harus berurutan / berurutan hari).
        if (!Schema::hasColumn('leave_requests', 'custom_dates')) {
            Schema::table('leave_requests', function (Blueprint $table) {
                $table->text('custom_dates')->nullable();
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('leave_requests', 'custom_dates')) {
            Schema::table('leave_requests', function (Blueprint $table) {
                $table->dropColumn('custom_dates');
            });
        }

        // Kembalikan durasi sebelumnya
        DB::table('leave_types')->where('code', 'keluarga_meninggal')->update(['default_days' => 1]);
    }
};
