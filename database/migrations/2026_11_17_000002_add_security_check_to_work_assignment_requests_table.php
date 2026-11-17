<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * WORK ASSIGNMENT REQUEST — kolom security_check.
 *
 * Menyimpan dua catatan gate check oleh petugas security, keduanya masuk
 * lewat scan QR di halaman Security:
 *
 *   {
 *     "out": {
 *       "npk": "C-00857",
 *       "status": "approved",
 *       "at": "2026-11-17 08:05:00"
 *     },
 *     "in": {
 *       "npk": "C-00857",
 *       "status": "approved",
 *       "at": "2026-11-17 17:02:00"
 *     }
 *   }
 *
 * `out`  = karyawan keluar (gate out)
 * `in`   = karyawan kembali (gate in) — sekaligus mengisi jam_kembali
 *
 * Sekali diisi tidak bisa diubah lagi (dikunci di service).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('work_assignment_requests')) {
            return;
        }

        if (Schema::hasColumn('work_assignment_requests', 'security_check')) {
            return;
        }

        Schema::table('work_assignment_requests', function (Blueprint $table) {
            $table->json('security_check')->nullable();
        });
    }

    public function down(): void
    {
        if (!Schema::hasTable('work_assignment_requests')) {
            return;
        }

        if (!Schema::hasColumn('work_assignment_requests', 'security_check')) {
            return;
        }

        Schema::table('work_assignment_requests', function (Blueprint $table) {
            $table->dropColumn('security_check');
        });
    }
};