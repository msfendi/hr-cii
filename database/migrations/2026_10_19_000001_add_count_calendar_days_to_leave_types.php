<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Menambahkan penanda apakah sebuah jenis cuti menghitung hari kalender
     * (termasuk akhir pekan & hari libur) atau hanya hari kerja.
     *
     * - Cuti Melahirkan & Cuti Keguguran: menghitung hari kalender.
     * - Jenis cuti lain: hanya hari kerja (akhir pekan & hari libur dilewati).
     *
     * Selain itu menyesuaikan jatah hari beberapa jenis cuti:
     * - Cuti Keluarga 1 Rumah Meninggal: 1 hari.
     */
    public function up(): void
    {
        if (!Schema::hasColumn('leave_types', 'count_calendar_days')) {
            Schema::table('leave_types', function (Blueprint $table) {
                $table->boolean('count_calendar_days')->default(false);
            });
        }

        // Cuti melahirkan & keguguran menghitung hari kalender (weekend & libur dihitung)
        DB::table('leave_types')
            ->whereIn('code', ['melahirkan', 'keguguran'])
            ->update(['count_calendar_days' => 1]);

        // Cuti Keluarga 1 Rumah Meninggal = 1 hari
        DB::table('leave_types')
            ->where('code', 'keluarga_meninggal')
            ->update(['default_days' => '1']);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::table('leave_types')
            ->where('code', 'keluarga_meninggal')
            ->update(['default_days' => '2']);

        if (Schema::hasColumn('leave_types', 'count_calendar_days')) {
            Schema::table('leave_types', function (Blueprint $table) {
                $table->dropColumn('count_calendar_days');
            });
        }
    }
};
