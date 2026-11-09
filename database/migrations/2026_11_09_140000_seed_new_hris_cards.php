<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('card_menu_settings')) return;

        $cards = [
            ['key' => 'canteen-transaction', 'name' => 'Canteen Transaction', 'route' => '/canteen-transaction', 'icon' => 'fas fa-utensils', 'color' => 'warning', 'is_visible' => true, 'order' => 9],
            ['key' => 'satisfaction-survey', 'name' => 'Satisfaction Survey', 'route' => '/satisfaction-survey', 'icon' => 'fas fa-heart', 'color' => 'danger', 'is_visible' => true, 'order' => 10],
        ];
        foreach ($cards as $c) {
            $exists = DB::table('card_menu_settings')->where('key', $c['key'])->exists();
            if ($exists) continue;
            DB::table('card_menu_settings')->insert([
                'key' => $c['key'], 'name' => $c['name'], 'route' => $c['route'], 'icon' => $c['icon'],
                'color' => $c['color'], 'is_visible' => $c['is_visible'] ? 1 : 0, 'order' => $c['order'],
                'created_at' => now(), 'updated_at' => now(),
            ]);
        }
    }

    public function down(): void
    {
        if (!Schema::hasTable('card_menu_settings')) return;
        DB::table('card_menu_settings')->whereIn('key', ['canteen-transaction', 'satisfaction-survey'])->delete();
    }
};
