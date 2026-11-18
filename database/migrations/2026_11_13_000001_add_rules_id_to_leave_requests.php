<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * `rules_id` pada leave_requests = approval group (approval_depts.id) yang
 * dipakai saat pengajuan dibuat.
 *
 * Gunanya: saat master approval di Manage Approval berubah (tambah/hapus/ubah
 * level), pengajuan yang masih PENDING bisa disinkronkan ulang ke chain yang
 * baru. Pengajuan yang sudah diputuskan (approved/rejected) tidak disentuh.
 *
 * Nama & arti konsisten dengan approval_rules.rules_id (FK ke approval_depts.id).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasColumn('leave_requests', 'rules_id')) {
            Schema::table('leave_requests', function (Blueprint $table) {
                $table->unsignedBigInteger('rules_id')->nullable();
                $table->index('rules_id', 'leave_requests_rules_id_index');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('leave_requests', 'rules_id')) {
            Schema::table('leave_requests', function (Blueprint $table) {
                $table->dropIndex('leave_requests_rules_id_index');
                $table->dropColumn('rules_id');
            });
        }
    }
};
