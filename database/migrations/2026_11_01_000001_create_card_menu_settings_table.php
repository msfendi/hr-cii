<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Tabel pengaturan "card menu" pada halaman HRIS.
     * Admin dapat show/hide tiap card (Login, Slip, Career, dst.)
     * tanpa mengubah template blade.
     */
    public function up(): void
    {
        if (!Schema::hasTable('card_menu_settings')) {
            Schema::create('card_menu_settings', function (Blueprint $table) {
                $table->id();
                $table->string('key')->unique();      // identifier card, mis. 'login', 'slip', 'face-attendance'
                $table->string('name');              // label yang tampil di halaman pengaturan
                $table->string('route')->nullable(); // route tujuan card
                $table->string('icon')->nullable();  // class ikon, mis. 'fas fa-users'
                $table->string('color')->nullable(); // 'primary' | 'success' | 'warning' | 'info' | 'danger'
                $table->boolean('is_visible')->default(true);
                $table->integer('order')->default(0);
                $table->timestamps();
            });
        }

        // Seed default card menu sesuai urutan di hris.blade.php
        $defaults = [
            ['key' => 'login',              'name' => 'Login',                        'route' => '/login',                      'icon' => 'fas fa-users',              'color' => 'primary', 'order' => 1],
            ['key' => 'slip',               'name' => 'Lihat Slip',                   'route' => '/employee-payroll',          'icon' => 'fas fa-file-invoice-dollar', 'color' => 'primary', 'order' => 2],
            ['key' => 'career',             'name' => 'Career',                       'route' => '/lowongan',                  'icon' => 'fas fa-briefcase',          'color' => 'success', 'order' => 3],
            ['key' => 'recruitment',        'name' => 'Recruitment Announcement',     'route' => '/portal-recruitment-status', 'icon' => 'fa fa-bullhorn',            'color' => 'success', 'order' => 4],
            ['key' => 'food-orders',        'name' => 'Food Orders',                  'route' => '/food-orders/scan',          'icon' => 'fas fa-utensils',           'color' => 'primary', 'order' => 5, 'is_visible' => false],
            ['key' => 'leave-request',      'name' => 'Pengajuan Cuti',               'route' => '/pengajuan-cuti/login',      'icon' => 'fas fa-calendar',           'color' => 'warning', 'order' => 6],
            ['key' => 'face-attendance',    'name' => 'Presensi Wajah',               'route' => '/face-attendance/login',     'icon' => 'fas fa-camera',             'color' => 'primary', 'order' => 7],
            ['key' => 'self-register-face', 'name' => 'Self Register Wajah',          'route' => '/self-register-face',        'icon' => 'fas fa-id-badge',           'color' => 'info',    'order' => 8],
            ['key' => 'canteen-transaction', 'name' => 'Canteen Transaction',               'route' => '/canteen-transaction',       'icon' => 'fas fa-utensils',         'color' => 'warning', 'order' => 9],
            ['key' => 'satisfaction-survey', 'name' => 'Satisfaction Survey',              'route' => '/satisfaction-survey',       'icon' => 'fas fa-heart',            'color' => 'danger',  'order' => 10],
        ];

        foreach ($defaults as $d) {
            $exists = DB::table('card_menu_settings')->where('key', $d['key'])->exists();
            if ($exists) {
                continue;
            }

            DB::table('card_menu_settings')->insert([
                'key'        => $d['key'],
                'name'       => $d['name'],
                'route'      => $d['route'],
                'icon'       => $d['icon'],
                'color'      => $d['color'],
                'is_visible' => $d['is_visible'] ?? true,
                'order'      => $d['order'],
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('card_menu_settings');
    }
};
