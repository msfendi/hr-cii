<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Tabel att_log untuk absensi wajah GPS (testing SQLite).
     * Secara produksi tabel ini berasal dari fingerspot (SQL Server),
     * kolom source='FACE' dan att_id='FACE...' memisahkan absensi wajah dari finger.
     */
    public function up(): void
    {
        Schema::create('att_log', function (Blueprint $table) {
            $table->integer('sn')->nullable();
            $table->dateTime('scan_date')->nullable();
            $table->string('pin', 50)->nullable()->index();
            $table->string('verifymode', 50)->nullable();
            $table->string('inoutmode', 10)->nullable();
            $table->string('reserved', 50)->nullable();
            $table->string('work_code', 50)->nullable();
            $table->string('att_id', 100)->nullable()->index();
            $table->string('source', 20)->nullable()->index();
            $table->decimal('latitude', 10, 7)->nullable();
            $table->decimal('longitude', 10, 7)->nullable();
            $table->decimal('accuracy', 10, 2)->nullable();
            $table->decimal('confidence', 5, 2)->nullable();
            $table->tinyInteger('liveness_passed')->nullable();
            $table->string('status', 20)->nullable();
            $table->text('rejection_reason')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('att_log');
    }
};
