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
        Schema::create('rag_knowledge_bases', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->string('category')->default('general');
            $table->string('source_type')->default('document'); // document, policy, sop, manual, text
            $table->string('file_path')->nullable();
            $table->string('original_filename')->nullable();
            $table->integer('total_chunks')->default(0);
            $table->unsignedBigInteger('created_by')->nullable();
            $table->timestamps();
        });

        Schema::create('rag_knowledge_chunks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('knowledge_base_id')->constrained('rag_knowledge_bases')->onDelete('cascade');
            $table->integer('chunk_index');
            $table->text('content');
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index(['knowledge_base_id', 'chunk_index']);
        });

        Schema::create('rag_chat_sessions', function (Blueprint $table) {
            $table->id();
            $table->string('session_id')->unique();
            $table->string('title')->nullable();
            $table->unsignedBigInteger('user_id')->nullable();
            $table->timestamps();
        });

        Schema::create('rag_chat_messages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('chat_session_id')->constrained('rag_chat_sessions')->onDelete('cascade');
            $table->enum('role', ['user', 'assistant', 'system']);
            $table->text('content');
            $table->string('intent')->nullable(); // rag, sql_query, document_generation, general
            $table->text('executed_sql')->nullable();
            $table->json('sql_data')->nullable();
            $table->string('generated_document_type')->nullable(); // pdf, excel
            $table->string('generated_document_url')->nullable();
            $table->string('generated_document_filename')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('rag_chat_messages');
        Schema::dropIfExists('rag_chat_sessions');
        Schema::dropIfExists('rag_knowledge_chunks');
        Schema::dropIfExists('rag_knowledge_bases');
    }
};
