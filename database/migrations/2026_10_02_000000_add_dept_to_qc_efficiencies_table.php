<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('qc_efficiencies', function (Blueprint $table) {
            // 'qc' / 'qa'. Data lama otomatis berisi 'qc'.
            $table->string('dept', 10)->default('qc')->after('third_party');
        });
    }

    public function down(): void
    {
        Schema::table('qc_efficiencies', function (Blueprint $table) {
            $table->dropColumn('dept');
        });
    }
};
