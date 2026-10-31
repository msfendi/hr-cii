<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateFaceAttendanceSettingsTable extends Migration
{
    public function up()
    {
        if (Schema::hasTable('face_attendance_settings')) {
            return;
        }

        Schema::create('face_attendance_settings', function (Blueprint $table) {
            $table->id();
            $table->unsignedInteger('clock_in_before_minutes')->default(15);
            $table->unsignedInteger('clock_out_after_minutes')->default(15);
            $table->boolean('is_saturday_enabled')->default(false);
            $table->boolean('is_sunday_enabled')->default(false);
            $table->boolean('is_holiday_enabled')->default(false);
            $table->timestamps();
        });
    }

    public function down()
    {
        Schema::dropIfExists('face_attendance_settings');
    }
}
