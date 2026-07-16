<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateBpjsExceptionsTable extends Migration
{
    public function up()
    {
        Schema::create('bpjs_exceptions', function (Blueprint $table) {
            $table->id();
            $table->string('npk', 20);
            $table->string('component', 100);
            $table->decimal('percentage', 8, 2)->default(0);
            $table->timestamps();

            $table->index('npk');
            $table->index('component');
        });
    }

    public function down()
    {
        Schema::dropIfExists('bpjs_exceptions');
    }
}
