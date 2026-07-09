<?php
// database/migrations/xxxx_xx_xx_create_qr_scan_logs_table.php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('qr_scan_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('npk_scanned', 20);
            $table->string('device_uuid', 64)->nullable()->index();
            $table->string('device_name')->nullable();
            $table->enum('device_type', ['desktop', 'laptop', 'mobile', 'tablet', 'unknown'])->default('unknown');
            $table->string('platform')->nullable();   // OS: Windows, Android, iOS, macOS, dst
            $table->string('browser')->nullable();    // Chrome, Safari, Firefox, dst
            $table->string('ip_address', 45);
            $table->text('user_agent')->nullable();
            $table->enum('status', [
                'success',
                'failed_invalid_format',
                'failed_user_not_found',
                'failed_device_not_registered',
                'failed_device_inactive',
            ]);
            $table->timestamps();

            $table->index(['npk_scanned', 'created_at']);
            $table->index(['ip_address', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('qr_scan_logs');
    }
};
