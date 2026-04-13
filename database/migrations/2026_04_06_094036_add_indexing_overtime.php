<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Add index for faster queries
        Schema::table('overtimes', function (Blueprint $table) {
            $table->index(['OVERTIME_DATE', 'NPK']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Remove indexes
        Schema::table('overtimes', function (Blueprint $table) {
            $table->dropIndex(['OVERTIME_DATE', 'NPK']);
        });
    }
};
