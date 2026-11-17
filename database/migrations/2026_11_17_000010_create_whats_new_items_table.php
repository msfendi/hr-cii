<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * TASK 2 — What's New (slider banner) untuk HRIS v1 dan v2.
 *
 * Satu tabel dipakai kedua tampilan: admin mengisi judul, penjelasan
 * singkat, media (gambar/video/URL), dan tombol opsional yang mengarah
 * ke route atau URL.
 *
 * Idempotent: aman dijalankan berulang / pada instalasi yang sudah punya
 * tabel dengan nama sama.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('whats_new_items')) {
            return;
        }

        Schema::create('whats_new_items', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('title', 150);
            $table->text('summary')->nullable();

            // gambar | video | none
            $table->string('media_type', 10)->default('image');
            $table->string('media_path', 255)->nullable();
            $table->string('media_url', 500)->nullable();

            $table->string('button_label', 60)->nullable();
            $table->string('button_route', 150)->nullable();
            $table->string('button_url', 500)->nullable();

            $table->integer('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamp('starts_at')->nullable();
            $table->timestamp('ends_at')->nullable();

            $table->unsignedBigInteger('created_by')->nullable();
            $table->timestamps();

            $table->index(['is_active', 'sort_order'], 'whats_new_aktif_urutan_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('whats_new_items');
    }
};