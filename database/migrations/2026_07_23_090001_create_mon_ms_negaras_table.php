<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Master data negara, sync dari smartit.ms_negara (lihat perintah
     * `monitoring:sync-ms-negara`). Dipakai untuk mapping negara_id di
     * mon_ms_suppliers -> nama negara, dan sebagai filter "per negara"
     * di dashboard Rekonsiliasi (Shipment Qty, Pivot Shipment, Shipment Date).
     */
    public function up(): void
    {
        Schema::create('mon_ms_negaras', function (Blueprint $table) {
            $table->id();
            $table->string('negara_code', 10)->unique();
            $table->string('negara_name', 150)->nullable();
            $table->string('create_by', 100)->nullable();
            $table->dateTime('create_date')->nullable();
            $table->string('modify_by', 100)->nullable();
            $table->dateTime('modify_date')->nullable();
            $table->timestamps();

            $table->index('negara_name');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('mon_ms_negaras');
    }
};
