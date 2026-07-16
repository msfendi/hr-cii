<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateSewingViolationsTable extends Migration
{
    public function up()
    {
        Schema::create('sewing_violations', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('id_dept');
            $table->string('pelanggaran');
            $table->date('tanggal');
            $table->timestamps();

            $table->index('id_dept');
        });
    }

    public function down()
    {
        Schema::dropIfExists('sewing_violations');
    }
}
