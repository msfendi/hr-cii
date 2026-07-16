<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('doorprize_winners', function (Blueprint $table) {
            $table->boolean('is_void')->default(false)->after('won_at');
            $table->string('void_reason')->nullable()->after('is_void');
            $table->timestamp('voided_at')->nullable()->after('void_reason');
            $table->unsignedBigInteger('voided_by')->nullable()->after('voided_at');
        });
    }

    public function down(): void
    {
        Schema::table('doorprize_winners', function (Blueprint $table) {
            $table->dropForeign(['voided_by']);
            $table->dropColumn(['is_void', 'void_reason', 'voided_at', 'voided_by']);
        });
    }
};
