<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Tambah kolom `cpo` (kode CPO / mon_orders.uraian) ke mon_stage_remarks
     * dan mon_prod_qc. Diisi dari kolom `cpo` di file import Excel.
     *
     * Nullable supaya baris lama (yang diimport sebelum kolom ini ada)
     * tetap valid -- baris tsb tetap ketemu lewat filter OCF.
     */
    public function up(): void
    {
        Schema::table('mon_stage_remarks', function (Blueprint $table) {
            $table->string('cpo', 100)->nullable()->after('ocf_no')->index();
        });

        Schema::table('mon_prod_qc', function (Blueprint $table) {
            $table->string('cpo', 100)->nullable()->after('code_prod')->index();
        });
    }

    public function down(): void
    {
        Schema::table('mon_stage_remarks', function (Blueprint $table) {
            $table->dropIndex(['cpo']);
            $table->dropColumn('cpo');
        });

        Schema::table('mon_prod_qc', function (Blueprint $table) {
            $table->dropIndex(['cpo']);
            $table->dropColumn('cpo');
        });
    }
};
