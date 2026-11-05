<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    // PERBAIKAN V3 (2026-11-05): gunakan connection 'cii' (default app),
    // bukan 'sqlsrv' yang sudah di-comment-out pada config/database.php.
    // Tabel signatures berada di database CII, sama seperti tabel users.
    protected $connection = 'cii';

    public function up(): void
    {
        // Tabel sudah ada di CII (struktur dari Dimas):
        // id bigint PK, user_id nvarchar(255), signature_img nvarchar(255),
        // created_at datetime, updated_at datetime
        // Migration ini hanya membuat tabel jika belum ada.
        if (!Schema::connection('cii')->hasTable('signatures')) {
            Schema::connection('cii')->create('signatures', function (Blueprint $table) {
                $table->bigIncrements('id');
                $table->string('user_id', 255);
                $table->string('signature_img', 255);
                $table->timestamps();
            });
        }
    }

    public function down(): void
    {
        Schema::connection('cii')->dropIfExists('signatures');
    }
};
