<?php

namespace Database\Seeders;

// use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $this->call(UsersSeeder::class);
        $this->call(RolesSeeder::class);
        $this->call(ModelHasRolesSeeder::class);
        $this->call(SectionsSeeder::class);
        $this->call(PayrollComponentSeeder::class);
        $this->call(ApprovalSeeder::class);
        $this->call(LeavesTypeSeeder::class);
        $this->call(LeavesBalancesSeeder::class);
    }
}
