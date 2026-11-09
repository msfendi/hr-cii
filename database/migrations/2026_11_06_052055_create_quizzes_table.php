<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * PERBAIKAN V15 (2026-11-06)
     * --------------------------
     * 1) SQL Server (dan MSSQLEngine pada MariaDB) menolak sebuah foreign key
     *    dengan ON DELETE CASCADE bila tabel tersebut sudah punya jalur cascade
     *    lain yang bisa sampai ke tabel yang sama. Error yang muncul:
     *
     *      Introducing FOREIGN KEY constraint 'quiz_responses_question_id_foreign'
     *      on table 'quiz_responses' may cause cycles or multiple cascade paths.
     *
     *    Jalur yang bertabrakan pada skema ini:
     *
     *      quizzes -> quiz_questions -> quiz_options
     *      quizzes -> quiz_sessions -> quiz_responses -> quiz_options
     *      quizzes -> quiz_questions
     *      quizzes -> quiz_sessions -> quiz_responses -> quiz_questions
     *
     *    Perbaikan: pada tabel quiz_responses, foreign key ke quiz_questions dan
     *    quiz_options memakai "NO ACTION" (tanpa cascade). Jalur cascade tetap
     *    ada untuk quiz_id dan session_id, sehingga menghapus sebuah kuis
     *    tetap ikut menghapus semua sesi dan jawaban di bawahnya.
     *
     *    Karena quiz_responses.question_id sekarang tidak cascade, menghapus
     *    satu pertanyaan harus menghapus jawaban yang merujuk ke pertanyaan
     *    itu lebih dulu. Itu ditangani di QuizController::deleteQuestion()
     *    (QuizResponse::where('question_id', ...)->delete()).
     *
     * 2) Migration ini dibuat IDEMPOTEN. Percobaan pertama di SQL Server gagal
     *    saat membuat foreign key, sehingga tabel quizzes / quiz_questions /
     *    quiz_options / quiz_sessions / quiz_responses sudah TIDAK LENGKAP
     *    Tertinggal di database dan tidak tercatat di tabel "migrations".
     *    Kalau migration biasa dijalankan ulang, Laravel akan gagal dengan
     *    "Table already exists" sebelum sempat memperbaiki foreign key.
     *
     *    Solusi: bila salah satu tabel sudah ada, hulu-hulu dulu (urutan anak
     *    ke induk) lalu buat ulang dengan skema yang benar. Data quiz yang
     *    sudah terlanjur tersimpan akan hilang, jadi jalankan migrasi ini
     *    SEGERA setelah tabel dikosongkan/dibuat ulang dari awal.
     */
    public function up(): void
    {
        // Bersihkan sisa percobaan sebelumnya (anak dulu, lalu induk) agar
        // migration ini bisa dijalankan ulang dengan aman.
        if (Schema::hasTable('quiz_responses')) {
            Schema::drop('quiz_responses');
        }
        if (Schema::hasTable('quiz_sessions')) {
            Schema::drop('quiz_sessions');
        }
        if (Schema::hasTable('quiz_options')) {
            Schema::drop('quiz_options');
        }
        if (Schema::hasTable('quiz_questions')) {
            Schema::drop('quiz_questions');
        }
        if (Schema::hasTable('quizzes')) {
            Schema::drop('quizzes');
        }

        Schema::create('quizzes', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->text('description')->nullable();
            $table->string('qr_code')->unique()->index(); // kode unik untuk peserta scan
            $table->enum('status', ['draft','active','closed'])->default('draft');
            $table->timestamp('started_at')->nullable();
            $table->timestamp('closed_at')->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->timestamps();
        });

        Schema::create('quiz_questions', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('quiz_id')->index();
            $table->string('question_text');
            $table->enum('type', ['single_choice','multiple_choice'])->default('single_choice');
            $table->integer('sort_order')->default(0);
            $table->timestamps();
            $table->foreign('quiz_id')->references('id')->on('quizzes')->onDelete('cascade');
        });

        Schema::create('quiz_options', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('question_id')->index();
            $table->string('option_text');
            $table->boolean('is_correct')->default(false);
            $table->integer('sort_order')->default(0);
            $table->timestamps();
            $table->foreign('question_id')->references('id')->on('quiz_questions')->onDelete('cascade');
        });

        Schema::create('quiz_sessions', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('quiz_id')->index();
            $table->string('participant_name');
            $table->string('participant_ip')->nullable();
            $table->timestamp('joined_at');
            $table->timestamps();
            $table->foreign('quiz_id')->references('id')->on('quizzes')->onDelete('cascade');
        });

        Schema::create('quiz_responses', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('session_id')->index();
            $table->unsignedBigInteger('question_id')->nullable()->index();
            $table->unsignedBigInteger('option_id')->nullable();
            $table->timestamp('answered_at');
            $table->timestamps();

            // Sesi milik kuis: cascade aman (tidak ada jalur cascade lain ke sesi).
            $table->foreign('session_id')->references('id')->on('quiz_sessions')->onDelete('cascade');

            // PERBAIKAN V15: NO ACTION untuk question_id dan option_id.
            // Inilah penyebab error "multiple cascade paths" di SQL Server.
            $table->foreign('question_id')->references('id')->on('quiz_questions')->onDelete('no action');
            $table->foreign('option_id')->references('id')->on('quiz_options')->onDelete('no action');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Urutan penting: anak dulu, lalu induk.
        Schema::dropIfExists('quiz_responses');
        Schema::dropIfExists('quiz_sessions');
        Schema::dropIfExists('quiz_options');
        Schema::dropIfExists('quiz_questions');
        Schema::dropIfExists('quizzes');
    }
};
