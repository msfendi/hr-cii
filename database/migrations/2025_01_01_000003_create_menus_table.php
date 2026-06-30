<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('menus', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('parent_id')->nullable(); // null = parent menu, ada isi = sub menu
            $table->string('name');                      // label yang tampil di sidebar
            $table->string('route_name')->nullable();    // null untuk parent yang cuma dropdown
            $table->string('icon')->nullable();           // contoh: fas fa-users-cog
            // permission_id menentukan menu ini hanya tampil kalau role punya permission ini
            $table->foreignId('permission_id')->nullable()
                ->constrained('permissions')->nullOnDelete();
            $table->integer('order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        // FK self-reference dibuat terpisah dengan NO ACTION supaya tidak ditolak SQL Server
        // (SQL Server melarang cascade path ganda pada FK self-referencing).
        Schema::table('menus', function (Blueprint $table) {
            $table->foreign('parent_id')
                ->references('id')->on('menus')
                ->onDelete('no action')
                ->onUpdate('no action');
        });
    }

    public function down(): void
    {
        Schema::table('menus', function (Blueprint $table) {
            $table->dropForeign(['parent_id']);
        });
        Schema::dropIfExists('menus');
    }
};
