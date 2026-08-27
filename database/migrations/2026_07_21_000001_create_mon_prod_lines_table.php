<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('mon_prod_lines', function (Blueprint $table) {
            $table->id();

            // Kunci unik untuk upsert dari smartit (line_hd.line_id)
            $table->string('line_id')->unique();

            $table->string('code_prod')->nullable()->index();
            $table->string('department_id')->nullable()->index();
            $table->date('tgl_produksi')->nullable()->index();
            $table->string('barang_code')->nullable()->index();
            $table->string('barang_name')->nullable();
            $table->string('barang_category')->nullable();
            $table->decimal('jumlah', 18, 4)->nullable();
            $table->string('destination')->nullable();
            $table->string('no_surat_jalan')->nullable();
            $table->string('create_by')->nullable();
            $table->string('create_date')->nullable(); // sudah diformat 'yyyy-MM-dd HH:mm' dari smartit
            $table->unsignedTinyInteger('digit')->default(2);
            $table->decimal('total_nilai', 18, 4)->nullable();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('mon_prod_lines');
    }
};
