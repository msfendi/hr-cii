<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Tambah kolom "group" (sewing / cutting) ke heat_efficiencies.
     * Nullable: data lama tetap null = dihitung tanpa filter group (perilaku lama).
     */
    public function up(): void
    {
        if (!Schema::hasColumn('heat_efficiencies', 'group')) {
            Schema::table('heat_efficiencies', function (Blueprint $table) {
                $table->string('group', 20)->nullable()->after('tim');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('heat_efficiencies', 'group')) {
            Schema::table('heat_efficiencies', function (Blueprint $table) {
                $table->dropColumn('group');
            });
        }
    }
};
