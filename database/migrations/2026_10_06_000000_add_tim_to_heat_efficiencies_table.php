<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasColumn('heat_efficiencies', 'tim')) {
            Schema::table('heat_efficiencies', function (Blueprint $table) {
                // 1 / 2, NULL = tanpa pemisahan tim (sama seperti pad_efficiencies)
                $table->integer('tim')->nullable();
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('heat_efficiencies', 'tim')) {
            Schema::table('heat_efficiencies', function (Blueprint $table) {
                $table->dropColumn('tim');
            });
        }
    }
};
