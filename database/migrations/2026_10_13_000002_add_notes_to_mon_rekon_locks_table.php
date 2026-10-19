<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Kolom "REKOMENDASI & NOTES" (diisi manual lewat import Excel di halaman
 * Summary Rekonsiliasi). Disimpan di baris lock OCF yang bersangkutan.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('mon_rekon_locks', function (Blueprint $table) {
            $table->unsignedSmallInteger('note_prioritas')->nullable();   // urutan tampil (1 = paling atas)
            $table->string('note_status', 20)->nullable();                // Investigasi | Monitor | Follow-up
            $table->text('note_masalah')->nullable();
            $table->text('note_tindakan')->nullable();
            $table->string('note_pic', 150)->nullable();
            $table->string('note_target', 100)->nullable();               // mis. "7 hari"
            $table->decimal('note_estimasi', 18, 2)->nullable();          // estimasi potensi penghematan (Rp)
            $table->timestamp('note_imported_at')->nullable();
            $table->string('note_imported_by', 100)->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('mon_rekon_locks', function (Blueprint $table) {
            $table->dropColumn([
                'note_prioritas', 'note_status', 'note_masalah', 'note_tindakan',
                'note_pic', 'note_target', 'note_estimasi', 'note_imported_at', 'note_imported_by',
            ]);
        });
    }
};
