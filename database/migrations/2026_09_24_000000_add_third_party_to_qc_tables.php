<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        foreach (['employee_qc_assignments', 'qc_efficiencies'] as $table) {
            if (!Schema::hasColumn($table, 'third_party')) {
                Schema::table($table, function (Blueprint $t) {
                    $t->string('third_party', 100)->nullable();
                });
            }
        }
    }

    public function down(): void
    {
        foreach (['employee_qc_assignments', 'qc_efficiencies'] as $table) {
            if (Schema::hasColumn($table, 'third_party')) {
                Schema::table($table, function (Blueprint $t) {
                    $t->dropColumn('third_party');
                });
            }
        }
    }
};
