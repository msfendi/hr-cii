<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Ganti Schema::create(...) menjadi Schema::connection('cii')->create(...)
     * jika tabel ini ingin disimpan di database SQL Server 'cii' (bukan default MySQL).
     */
    public function up(): void
    {
        Schema::create('audit_trails', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id')->nullable()->index();
            $table->string('user_name')->nullable(); // snapshot nama, jaga2 kalau user dihapus

            $table->string('event', 20)->index(); // created | updated | deleted

            $table->string('auditable_type')->index(); // App\Models\Employee, dst
            $table->unsignedBigInteger('auditable_id')->nullable()->index();

            $table->longText('old_values')->nullable();
            $table->longText('new_values')->nullable();

            $table->string('url', 1000)->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->string('user_agent', 500)->nullable();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('audit_trails');
    }
};
