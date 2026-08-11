<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('salary_approve', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('id_pelamar')->index(); // ref PELAMAR.ID (koneksi cii, SQL Server)
            $table->decimal('expected_salary', 15, 2);
            $table->decimal('approved_salary', 15, 2)->nullable();
            $table->json('progress');           // pola sama dengan payroll_approves
            $table->json('approved_at')->nullable();
            $table->string('status')->default('pending'); // pending | finish | rejected
            $table->string('requested_by')->nullable();   // npk HR yang input
            $table->timestamps();
        });
    }

    public function down()
    {
        Schema::dropIfExists('salary_approve');
    }
};