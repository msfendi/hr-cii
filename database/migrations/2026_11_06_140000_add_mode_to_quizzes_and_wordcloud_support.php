<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * V22 — Add quiz "mode" + word-cloud support.
 *
 * Modes:
 *   - quiz        : existing behaviour (single/multiple choice, scored).
 *   - polling     : closed-option live vote (Mentimeter-style bar chart), no scoring.
 *   - word_cloud  : free-text answer aggregated into a frequency-weighted cloud.
 *
 * Changes:
 *   1. quizzes.mode          VARCHAR(20) DEFAULT 'quiz'
 *   2. quiz_questions.type   extended enum values: 'open_text' (word_cloud) and
 *                            'poll_single' (polling single-choice, no correct flag).
 *                            Stored as varchar so we don't have to rebuild the enum.
 *   3. quiz_responses.answer_text NVARCHAR(500) NULL — for open_text submissions.
 *
 * Idempotent per project convention (sqlserver-migrations.md): every DDL is guarded
 * by hasColumn()/hasTable(). No cascade changes here.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('quizzes')) return;

        // 1) quizzes.mode
        if (!Schema::hasColumn('quizzes', 'mode')) {
            Schema::table('quizzes', function (Blueprint $t) {
                $t->string('mode', 20)->default('quiz')->after('status');
            });
        }

        // Backfill any legacy rows to 'quiz'.
        DB::table('quizzes')->whereNull('mode')->update(['mode' => 'quiz']);

        // 2) quiz_questions.type widened to varchar so new types can be added
        //    without touching the SQL Server enum constraint again.
        if (Schema::hasTable('quiz_questions') && Schema::hasColumn('quiz_questions', 'type')) {
            $col = collect(DB::select("
                SELECT c.DATA_TYPE, c.CHARACTER_MAXIMUM_LENGTH
                FROM INFORMATION_SCHEMA.COLUMNS c
                WHERE c.TABLE_NAME = 'quiz_questions' AND c.COLUMN_NAME = 'type'
                  AND c.TABLE_SCHEMA = SCHEMA_NAME()
            "))->first();

            if ($col && strtolower($col->DATA_TYPE) !== 'varchar') {
                // Drop default constraint bound to the old column before altering.
                DB::statement("
                    DECLARE @dc NVARCHAR(256);
                    SELECT @dc = dc.name
                      FROM sys.default_constraints dc
                      JOIN sys.columns c ON dc.parent_object_id = c.object_id
                                         AND dc.parent_column_id = c.column_id
                     WHERE dc.parent_object_id = OBJECT_ID('quiz_questions')
                       AND c.name = 'type';
                    IF @dc IS NOT NULL EXEC('ALTER TABLE quiz_questions DROP CONSTRAINT [' + @dc + ']');
                ");
                Schema::table('quiz_questions', function (Blueprint $t) {
                    $t->string('type', 30)->default('single_choice')->change();
                });
            }
        }

        // 3) quiz_responses.answer_text (for word_cloud / open_text submissions).
        if (Schema::hasTable('quiz_responses') && !Schema::hasColumn('quiz_responses', 'answer_text')) {
            Schema::table('quiz_responses', function (Blueprint $t) {
                $t->text('answer_text')->nullable()->after('option_id');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('quiz_responses') && Schema::hasColumn('quiz_responses', 'answer_text')) {
            Schema::table('quiz_responses', function (Blueprint $t) {
                $t->dropColumn('answer_text');
            });
        }
        if (Schema::hasTable('quizzes') && Schema::hasColumn('quizzes', 'mode')) {
            Schema::table('quizzes', function (Blueprint $t) {
                $t->dropColumn('mode');
            });
        }
    }
};
