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
        Schema::create('guest_masters', function (Blueprint $table) {
            $table->id();

            $table->unsignedBigInteger('foreign_guest_id');

            $table->string('gender')->nullable();
            $table->string('place')->nullable();
            $table->date('date_of_birth')->nullable();
            $table->string('nationality')->nullable();

            $table->string('passport_no')->nullable();
            $table->text('remark')->nullable();

            $table->date('issue_date')->nullable();
            $table->date('must_used_date')->nullable();
            $table->date('arrival_date')->nullable();
            $table->date('visa_expiry')->nullable();
            $table->date('status')->nullable();

            $table->timestamps();

            // Jika foreign_guest_id relasi ke tabel lain
            // $table->foreign('foreign_guest_id')
            //     ->references('id')
            //     ->on('foreign_guests')
            //     ->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('guest_masters');
    }
};
