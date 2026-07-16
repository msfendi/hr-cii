<?php
// database/migrations/xxxx_xx_xx_create_qr_authorized_devices_table.php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('qr_authorized_devices', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('device_uuid', 64)->unique();
            $table->string('device_name');
            $table->enum('device_type', ['desktop', 'laptop', 'mobile', 'tablet', 'unknown'])->default('unknown');
            $table->string('platform')->nullable();
            $table->string('browser')->nullable();
            $table->integer('assigned_by')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamp('last_used_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('qr_authorized_devices');
    }
};
