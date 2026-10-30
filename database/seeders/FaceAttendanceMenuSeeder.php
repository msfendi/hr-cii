<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class FaceAttendanceMenuSeeder extends Seeder
{
    public function run(): void
    {
        // Add Parent Menu Attendance & Child Menus
        $parent = DB::table('menus')->where('menu_name', 'Attendance')->first();
        if (!$parent) {
            $parentId = DB::table('menus')->insertGetId([
                'menu_name' => 'Attendance',
                'route_name' => 'face.attendance.index',
                'icon' => 'fas fa-clock',
                'order_no' => 50,
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        } else {
            $parentId = $parent->id;
        }

        // Submenu Manage Geo Map
        $mapMenu = DB::table('menus')->where('route_name', 'face.attendance.map')->first();
        if (!$mapMenu) {
            DB::table('menus')->insert([
                'parent_id' => $parentId,
                'menu_name' => 'Manage Geo Map',
                'route_name' => 'face.attendance.map',
                'icon' => 'fas fa-map-marked-alt',
                'order_no' => 1,
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        // Submenu Attendance Face
        $faceMenu = DB::table('menus')->where('route_name', 'face.attendance.login')->first();
        if (!$faceMenu) {
            DB::table('menus')->insert([
                'parent_id' => $parentId,
                'menu_name' => 'Attendance Face',
                'route_name' => 'face.attendance.login',
                'icon' => 'fas fa-camera',
                'order_no' => 2,
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }
}
