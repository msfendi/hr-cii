<?php

namespace Database\Seeders;

use App\Models\Menu;
use App\Models\Permission;
use App\Models\Role;
use Illuminate\Database\Seeder;

class SatisfactionSurveyMenuSeeder extends Seeder
{
    public function run(): void
    {
        // -----------------------------------------------------------------
        // Permission per route — kolom yang dipakai middleware CheckPermission
        // adalah `route_name`. `name` hanya label.
        // -----------------------------------------------------------------
        $permissions = [
            'satisfaction-survey.admin.index'   => ['name' => 'Kelola Kuesioner Kepuasan',        'group' => 'Kuesioner'],
            'satisfaction-survey.admin.store'   => ['name' => 'Tambah Pertanyaan Kuesioner',      'group' => 'Kuesioner'],
            'satisfaction-survey.admin.update'  => ['name' => 'Edit Pertanyaan Kuesioner',        'group' => 'Kuesioner'],
            'satisfaction-survey.admin.destroy' => ['name' => 'Hapus Pertanyaan Kuesioner',       'group' => 'Kuesioner'],
            'satisfaction-survey.admin.toggle'  => ['name' => 'Toggle Aktif Pertanyaan Kuesioner','group' => 'Kuesioner'],
            'satisfaction-survey.admin.results' => ['name' => 'Lihat Hasil Kuesioner Kepuasan',  'group' => 'Kuesioner'],
            'satisfaction-survey.admin.export'  => ['name' => 'Export Hasil Kuesioner Excel',    'group' => 'Kuesioner'],
        ];

        $permModels = [];
        foreach ($permissions as $routeName => $info) {
            $permModels[$routeName] = Permission::firstOrCreate(
                ['route_name' => $routeName],
                ['name' => $info['name'], 'group' => $info['group']]
            );
        }

        // -----------------------------------------------------------------
        // Assign ke role Admin (semua) + HRD (CRUD + lihat + export)
        // -----------------------------------------------------------------
        $mapping = [
            'Admin' => array_keys($permModels),
            'HRD'   => array_keys($permModels),
        ];

        foreach ($mapping as $roleName => $routeNames) {
            $role = Role::where('name', $roleName)->first();
            if (!$role) {
                continue;
            }
            $ids = collect($routeNames)->map(fn($rn) => $permModels[$rn]->id ?? null)->filter();
            $role->permissions()->syncWithoutDetaching($ids);
        }

        // -----------------------------------------------------------------
        // Menu sidebar — parent "Kuesioner" + child menu utama
        // Menu::buildSidebarFor() membaca parent_id + route_name + permission_id
        // -----------------------------------------------------------------
        $parent = Menu::firstOrCreate(
            ['name' => 'Kuesioner', 'parent_id' => null],
            ['icon' => 'fas fa-clipboard-list', 'order' => 30, 'is_active' => true]
        );

        Menu::firstOrCreate(
            ['name' => 'Kelola Kuesioner Kepuasan', 'parent_id' => $parent->id, 'route_name' => 'satisfaction-survey.admin.index'],
            ['icon' => 'fas fa-poll-h', 'order' => 1, 'is_active' => true, 'permission_id' => $permModels['satisfaction-survey.admin.index']->id]
        );

        Menu::firstOrCreate(
            ['name' => 'Hasil Kuesioner Kepuasan', 'parent_id' => $parent->id, 'route_name' => 'satisfaction-survey.admin.results'],
            ['icon' => 'fas fa-chart-bar', 'order' => 2, 'is_active' => true, 'permission_id' => $permModels['satisfaction-survey.admin.results']->id]
        );
    }
}
