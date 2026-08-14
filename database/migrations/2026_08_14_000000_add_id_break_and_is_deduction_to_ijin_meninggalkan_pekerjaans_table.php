<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('ijin_meninggalkan_pekerjaans', function (Blueprint $table) {
            $table->unsignedBigInteger('id_break')->nullable()->after('jam_kembali');
            $table->boolean('is_deduction')->default(false)->after('id_break');

            $table->index('id_break');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('ijin_meninggalkan_pekerjaans', function (Blueprint $table) {
            $table->dropIndex(['id_break']);
            $table->dropColumn(['id_break', 'is_deduction']);
        });
    }
};
