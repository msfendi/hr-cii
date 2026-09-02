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
        Schema::create('expat_documents', function (Blueprint $table) {
            $table->id();

            // NPK expat (BIODATA.NPK) - bukan foreign key karena BIODATA
            // berada di koneksi/DB yang berbeda (cii / SQL Server legacy).
            $table->string('npk', 20)->index();

            // Jenis dokumen, contoh: Paspor, KITAS, RPTKA, MERP, Kontrak Kerja, Visa, Lainnya
            $table->string('document_type', 50);

            // Nama file asli saat diupload user
            $table->string('file_name');

            // Path file di storage disk (default: 'public')
            $table->string('file_path');

            // Ukuran file dalam bytes
            $table->unsignedBigInteger('file_size')->nullable();

            // Catatan tambahan (opsional)
            $table->text('notes')->nullable();

            // User yang mengupload (opsional, sesuaikan dengan auth guard yang dipakai)
            $table->unsignedBigInteger('uploaded_by')->nullable();

            $table->timestamps();

            $table->index(['npk', 'document_type']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('expat_documents');
    }
};
