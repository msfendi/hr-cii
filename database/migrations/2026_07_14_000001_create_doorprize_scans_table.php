<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('doorprize_scans', function (Blueprint $table) {
            $table->id();
            $table->string('npk', 20)->unique(); // format: C-00001
            $table->unsignedBigInteger('scanned_by')->nullable();
            $table->timestamp('scanned_at')->useCurrent();
            $table->boolean('is_winner')->default(false); // true jika sudah pernah menang undian
            $table->timestamps();

            $table->foreign('scanned_by')->references('id')->on('users')->onDelete('set null');
            $table->index('npk');
            $table->index('is_winner');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('doorprize_scans');
    }
};
