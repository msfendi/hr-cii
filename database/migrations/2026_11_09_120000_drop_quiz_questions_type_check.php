<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('quiz_questions')) {
            return;
        }

        if (!Schema::hasColumn('quiz_questions', 'type')) {
            return;
        }

        $this->dropCheckConstraintsOnType();

        $col = collect(DB::select(
            "SELECT c.DATA_TYPE, c.CHARACTER_MAXIMUM_LENGTH
               FROM INFORMATION_SCHEMA.COLUMNS c
              WHERE c.TABLE_NAME = 'quiz_questions'
                AND c.COLUMN_NAME = 'type'
                AND c.TABLE_SCHEMA = SCHEMA_NAME()"
        ))->first();

        if ($col) {
            $isVarchar = strtolower((string) $col->DATA_TYPE) === 'varchar'
                && (int) $col->CHARACTER_MAXIMUM_LENGTH >= 30;

            if (!$isVarchar) {
                $this->dropDefaultConstraintsOnType();

                Schema::table('quiz_questions', function (Blueprint $t) {
                    $t->string('type', 30)
                        ->default('single_choice')
                        ->change();
                });
            }
        }
    }

    public function down(): void
    {
        // Intentionally leave type as varchar(30). The old CHECK only
        // allowed single_choice | multiple_choice and is what broke
        // open_text inserts. Rolling back the column type would bring
        // that CHECK back, so we do not restore it here.
    }

    private function dropCheckConstraintsOnType(): void
    {
        $names = DB::select(
            "SELECT cc.name AS name
               FROM sys.check_constraints cc
              WHERE cc.parent_object_id = OBJECT_ID('quiz_questions')"
        );

        foreach ($names as $row) {
            $definition = strtolower((string) DB::selectOne(
                "SELECT cc.definition AS definition
                   FROM sys.check_constraints cc
                  WHERE cc.parent_object_id = OBJECT_ID('quiz_questions')
                    AND cc.name = ?",
                [$row->name]
            )->definition ?? '');

            if (
                str_contains($definition, 'type') ||
                str_contains($definition, 'single_choice') ||
                str_contains($definition, 'multiple_choice') ||
                str_contains($definition, 'open_text')
            ) {
                DB::statement(
                    'ALTER TABLE quiz_questions DROP CONSTRAINT [' . $row->name . ']'
                );
            }
        }
    }

    private function dropDefaultConstraintsOnType(): void
    {
        $rows = DB::select(
            "SELECT dc.name AS name
               FROM sys.default_constraints dc
               JOIN sys.columns c
                 ON c.object_id = dc.parent_object_id
                AND c.column_id = dc.parent_column_id
              WHERE dc.parent_object_id = OBJECT_ID('quiz_questions')
                AND c.name = 'type'"
        );

        foreach ($rows as $row) {
            DB::statement(
                'ALTER TABLE quiz_questions DROP CONSTRAINT [' . $row->name . ']'
            );
        }
    }
};
