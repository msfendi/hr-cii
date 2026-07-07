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
        Schema::create('job_vacancies', function (Blueprint $table) {
            $table->id();
            $table->string('position');                       // Posisi yang dibuka
            $table->string('department_id');                          // Department terkait
            $table->unsignedInteger('total_needed')->default(1); // Jumlah orang dibutuhkan
            $table->enum('employment_type', [
                'full_time',
                'part_time',
                'contract',
                'internship',
                'daily_worker',
            ])->default('full_time');
            $table->text('job_description')->nullable();       // Deskripsi pekerjaan
            $table->json('criteria')->nullable();               // Kriteria yang dibutuhkan (list)
            $table->json('required_documents')->nullable();     // Dokumen yang diperlukan (list)
            $table->date('open_date');                          // Kapan dibuka
            $table->date('close_date');                         // Sampai kapan dibuka
            $table->enum('status', ['draft', 'open', 'closed'])->default('draft');
            $table->foreignId('created_by')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['status', 'open_date', 'close_date']);
        });
        Schema::table('job_vacancies', function (Blueprint $table) {
            $table->unsignedBigInteger('recruitment_position_id')->nullable()->after('department_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('job_vacancies');
        Schema::table('job_vacancies', function (Blueprint $table) {
            $table->dropColumn('recruitment_position_id');
        });
    }
};
