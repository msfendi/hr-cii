<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Tambah kolom label (nama tampilan node).
 *
 * Dipakai untuk level Company: level tertinggi memakai NAMA COMPANY yang diisi
 * manual, bukan diambil dari nama department (ID_DEPT).
 *
 * Idempotent: aman dijalankan berulang.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('org_chart_settings')) {
            return;
        }

        if (!Schema::hasColumn('org_chart_settings', 'label')) {
            Schema::table('org_chart_settings', function (Blueprint $table) {
                $table->string('label', 150)->nullable()->after('id_dept');
            });
        }
    }

    public function down(): void
    {
        if (!Schema::hasTable('org_chart_settings') || !Schema::hasColumn('org_chart_settings', 'label')) {
            return;
        }

        Schema::table('org_chart_settings', function (Blueprint $table) {
            $table->dropColumn('label');
        });
    }
};