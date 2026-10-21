<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Batas jumlah pengajuan per jenis cuti (SEUMUR HIDUP).
     *
     * Key = code jenis cuti, value = maksimal jumlah pengajuan.
     * Jenis cuti yang tidak ada di daftar ini => belum ada batas
     * (menunggu penetapan HR).
     */
    private const MAX_REQUESTS = [
        'menikah'             => 1,
        'melahirkan'          => 2,
        'keguguran'           => 2,
        'menikahkan_anak'     => 3,
        'istri_melahirkan'    => 2,
        'istri_keguguran'     => 2,
        'orang_tua_meninggal' => 4,
    ];

    public function up(): void
    {
        // 1) Kolom batas jumlah pengajuan (seumur hidup)
        if (!Schema::hasColumn('leave_types', 'max_requests')) {
            Schema::table('leave_types', function (Blueprint $table) {
                $table->integer('max_requests')->nullable()->default(null);
            });
        }

        // 2) Isi batas pengajuan sesuai ketentuan HR
        foreach (self::MAX_REQUESTS as $code => $max) {
            DB::table('leave_types')
                ->where('code', $code)
                ->update(['max_requests' => $max]);
        }

        // 3) Cuti Pribadi (Unpaid): unlimited — tidak ada batas hari per
        //    pengajuan, tidak ada batas jumlah pengajuan, tidak ada batas
        //    rentang tanggal. default_days = 0 = penanda "tanpa batas".
        DB::table('leave_types')
            ->where('code', 'pribadi')
            ->update(['default_days' => 0]);
    }

    public function down(): void
    {
        if (Schema::hasColumn('leave_types', 'max_requests')) {
            Schema::table('leave_types', function (Blueprint $table) {
                $table->dropColumn('max_requests');
            });
        }

        DB::table('leave_types')
            ->where('code', 'pribadi')
            ->update(['default_days' => 365]);
    }
};
