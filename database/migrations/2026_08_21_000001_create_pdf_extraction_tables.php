<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Kategori data, misal: "Shipping Info", "Invoice", "Bill of Lading", dll
        Schema::create('categories', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->timestamps();
        });

        // Data PDF yang diupload
        Schema::create('pdf_documents', function (Blueprint $table) {
            $table->id();
            $table->string('original_filename');
            $table->string('file_path');           // path file asli di storage
            $table->longText('raw_text')->nullable(); // hasil extract text mentah
            $table->foreignId('category_id')->nullable()->constrained()->nullOnDelete();
            $table->enum('status', ['pending', 'processing', 'processed', 'failed'])->default('pending');
            $table->text('error_message')->nullable();
            $table->timestamps();
        });

        // Data key-value hasil ekstraksi AI, misal: "Vessel/Voy" => "KM SINAR BALI / 123"
        Schema::create('extracted_data', function (Blueprint $table) {
            $table->id();
            $table->foreignId('pdf_document_id')->constrained()->cascadeOnDelete();
            $table->foreignId('category_id')->nullable()->constrained()->nullOnDelete();
            $table->string('data_key');     // contoh: "Vessel/Voy"
            $table->text('data_value')->nullable(); // contoh: "KM SINAR BALI / 123"
            $table->timestamps();

            $table->index('data_key'); // supaya pencarian by key cepat
        });

        // Header tabel yang terdeteksi di dalam PDF (misal tabel manifest barang)
        Schema::create('extracted_tables', function (Blueprint $table) {
            $table->id();
            $table->foreignId('pdf_document_id')->constrained()->cascadeOnDelete();
            $table->string('table_name')->nullable(); // opsional, misal "Cargo Manifest"
            $table->json('headers'); // ["No", "Nama Barang", "Qty", "Berat"]
            $table->unsignedInteger('table_index')->default(0); // urutan tabel ke-berapa dalam PDF
            $table->timestamps();
        });

        // Baris-baris isi tabel, disimpan sebagai JSON supaya fleksibel untuk header apapun
        Schema::create('extracted_table_rows', function (Blueprint $table) {
            $table->id();
            $table->foreignId('extracted_table_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('row_index')->default(0);
            $table->json('row_data'); // {"No": "1", "Nama Barang": "Beras", "Qty": "100", "Berat": "5000kg"}
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('extracted_table_rows');
        Schema::dropIfExists('extracted_tables');
        Schema::dropIfExists('extracted_data');
        Schema::dropIfExists('pdf_documents');
        Schema::dropIfExists('categories');
    }
};
