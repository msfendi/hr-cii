<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('satisfaction_responses') && !Schema::hasColumn('satisfaction_responses', 'submission_id')) {
            Schema::table('satisfaction_responses', function (Blueprint $table) {
                $table->uuid('submission_id')->nullable()->index();
            });
        }

        if (!Schema::hasTable('satisfaction_survey_ai_analyses')) {
            Schema::create('satisfaction_survey_ai_analyses', function (Blueprint $table) {
                $table->id();
                $table->string('scope_key', 80)->default('all');
                $table->unsignedInteger('source_count')->default(0);
                $table->string('provider', 40);
                $table->string('model', 120)->nullable();
                $table->longText('analysis_json');
                $table->timestamp('generated_at');
                $table->timestamps();
                $table->unique('scope_key', 'survey_ai_analysis_scope_unique');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('satisfaction_survey_ai_analyses');

        if (Schema::hasTable('satisfaction_responses') && Schema::hasColumn('satisfaction_responses', 'submission_id')) {
            Schema::table('satisfaction_responses', function (Blueprint $table) {
                $table->dropIndex(['submission_id']);
                $table->dropColumn('submission_id');
            });
        }
    }
};
