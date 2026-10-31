<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddFaceAttendanceAccessToEmployeeFacesTable extends Migration
{
    public function up()
    {
        if (!Schema::hasTable('employee_faces')) {
            return;
        }

        Schema::table('employee_faces', function (Blueprint $table) {
            if (!Schema::hasColumn('employee_faces', 'is_attendance_enabled')) {
                $table->boolean('is_attendance_enabled')->default(true)->after('is_active');
            }

            if (!Schema::hasColumn('employee_faces', 'location_scope')) {
                $table->string('location_scope', 20)->default('all')->after('is_attendance_enabled');
            }

            if (!Schema::hasColumn('employee_faces', 'allowed_location_ids')) {
                $table->text('allowed_location_ids')->nullable()->after('location_scope');
            }
        });
    }

    public function down()
    {
        if (!Schema::hasTable('employee_faces')) {
            return;
        }

        Schema::table('employee_faces', function (Blueprint $table) {
            if (Schema::hasColumn('employee_faces', 'allowed_location_ids')) {
                $table->dropColumn('allowed_location_ids');
            }
            if (Schema::hasColumn('employee_faces', 'location_scope')) {
                $table->dropColumn('location_scope');
            }
            if (Schema::hasColumn('employee_faces', 'is_attendance_enabled')) {
                $table->dropColumn('is_attendance_enabled');
            }
        });
    }
}
