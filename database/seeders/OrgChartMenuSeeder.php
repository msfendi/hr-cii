<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Menu sidebar untuk Organization Chart (index + setting).
 * Idempotent: cek route_name dulu sebelum insert.
 */
class OrgChartMenuSeeder extends Seeder
{
    public function run(): void
    {
        // Parent menu "HR" (dibuat jika belum ada), atau pakai yang sudah ada.
        $parent = DB::table('menus')->where('name', 'HR')->whereNull('parent_id')->first();
        if (!$parent) {
            $parent = DB::table('menus')->where('menu_name', 'HR')->whereNull('parent_id')->first();
        }

        $parentId = $parent->id ?? null;

        if (!$parentId) {
            $parentId = DB::table('menus')->insertGetId([
                'parent_id' => null,
                'name' => 'HR',
                'route_name' => null,
                'icon' => 'fas fa-users',
                'order' => 40,
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        $items = [
            [
                'name' => 'Organization Chart',
                'route_name' => 'org-chart.index',
                'icon' => 'fas fa-sitemap',
                'order' => 90,
            ],
            [
                'name' => 'Org Chart Setting',
                'route_name' => 'org-chart.settings',
                'icon' => 'fas fa-cog',
                'order' => 91,
            ],
        ];

        foreach ($items as $item) {
            $exists = DB::table('menus')->where('route_name', $item['route_name'])->first();
            if ($exists) {
                continue;
            }

            DB::table('menus')->insert([
                'parent_id' => $parentId,
                'name' => $item['name'],
                'route_name' => $item['route_name'],
                'icon' => $item['icon'],
                'order' => $item['order'],
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }
}