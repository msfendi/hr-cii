<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * WORK ASSIGNMENT REQUEST — kolom lampiran.
 *
 * `attach_files` = JSON array path relatif di disk `public`, contoh:
 *   ["work-assignment/C-00827/abc123.pdf", ...]
 *
 * Path disimpan relatif (bukan absolut) supaya aman dipindah server dan
 * bisa di-resolve ulang lewat Storage::disk('public').
 */
return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('work_assignment_requests')) {
            return;
        }

        if (Schema::hasColumn('work_assignment_requests', 'attach_files')) {
            return;
        }

        Schema::table('work_assignment_requests', function (Blueprint $table) {
            $table->json('attach_files')->nullable();
        });
    }

    public function down(): void
    {
        if (!Schema::hasTable('work_assignment_requests')) {
            return;
        }

        if (!Schema::hasColumn('work_assignment_requests', 'attach_files')) {
            return;
        }

        Schema::table('work_assignment_requests', function (Blueprint $table) {
            $table->dropColumn('attach_files');
        });
    }
};
