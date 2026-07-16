<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * PAYROLL_ROLE VALID VALUES:
     * - Payroll_STAFF
     * - Payroll_NONSTAFF
     * - Payroll_SEWING
     * - Payroll_NONSEWING
     * - Payroll_ALL      (tidak difilter / lihat semua karyawan)
     *
     * Satu user hanya boleh punya SATU payroll_role aktif (unique per user_id).
     * Kalau butuh kondisi campuran (misal Compliance kadang STAFF kadang NONSTAFF),
     * cukup ubah/replace baris untuk user tsb -- bukan tambah baris baru.
     */
    public function up(): void
    {
        Schema::create('role_payrolls', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id');
            $table->enum('payroll_role', [
                'Payroll_STAFF',
                'Payroll_NONSTAFF',
                'Payroll_SEWING',
                'Payroll_NONSEWING',
                'Payroll_ALL',
            ]);
            $table->unsignedBigInteger('created_by');
            $table->timestamps();

            $table->unique('user_id');
            $table->foreign('user_id')->references('id')->on('users')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('role_payrolls');
    }
};
