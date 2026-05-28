<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    protected $connection = 'cii';

    public function up(): void
    {
        Schema::connection('cii')->create('parent_dept', function (Blueprint $table) {
            $table->id();
            $table->string('parent_dept_name', 100);
            $table->timestamps();
        });

    }

    public function down(): void
    {
        Schema::connection('cii')->dropIfExists('parent_dept');
    }
};
