<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('food_menus', function (Blueprint $table) {
            $table->text('available_dates')->nullable()->after('available_end');
        });

        Schema::table('food_menus', function (Blueprint $table) {
            $table->dropColumn(['available_days', 'available_weeks']);
        });
    }

    public function down(): void
    {
        Schema::table('food_menus', function (Blueprint $table) {
            $table->string('available_days')->nullable();
            $table->string('available_weeks')->nullable();
        });

        Schema::table('food_menus', function (Blueprint $table) {
            $table->dropColumn('available_dates');
        });
    }
};
