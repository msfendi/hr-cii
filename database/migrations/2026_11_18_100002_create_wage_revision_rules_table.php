<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('wage_revision_rules', function (Blueprint $table) {
            $table->id();
            $table->foreignId('batch_id')->constrained('wage_revision_batches')->cascadeOnDelete();
            // non_staff   -> IS_STAFF = 0 AND IS_EXPAT = 0 (uniform percent)
            // staff_expat -> IS_STAFF = 1 OR  IS_EXPAT = 1 (nominal per employee)
            $table->string('segment');
            $table->string('mode');           // percent | nominal
            $table->decimal('percent', 6, 3)->nullable();
            $table->integer('round_to')->default(100);
            $table->boolean('warn_below_umr')->default(true);
            $table->timestamps();

            $table->unique(['batch_id', 'segment']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('wage_revision_rules');
    }
};
