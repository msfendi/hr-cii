<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * Menambahkan kolom resign khusus ke tabel PKWT:
     *   - file_penalty_letter  : path file penalty / surat penalti resign
     *   - is_agreement_signed  : 0/1 apakah karyawan sudah menandatangani agreement
     */
    public function up(): void
    {
        Schema::connection('cii')->table('PKWT', function (Blueprint $table) {
            $table->string('is_agreement_signed')->nullable()->default(0)->after('leave_reasons');
            $table->string('file_penalty_letter')->nullable()->after('file_penalty_letter');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::connection('cii')->table('PKWT', function (Blueprint $table) {
            $table->dropColumn(['file_penalty_letter', 'is_agreement_signed']);
        });
    }
};
