<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class RolePermissionSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
         // reset cahced roles and permission
         app()[PermissionRegistrar::class]->forgetCachedPermissions();

         // create permissions
         Permission::create(['name' => 'view order master']);
         Permission::create(['name' => 'create order master']);
         Permission::create(['name' => 'edit order master']);
         Permission::create(['name' => 'delete order master']);
 
         //create roles and assign existing permissions
         $adminRole = Role::create(['name' => 'Admin']);
         $adminRole->givePermissionTo('view order master');
         $adminRole->givePermissionTo('create order master');
         $adminRole->givePermissionTo('edit order master');
         $adminRole->givePermissionTo('delete order master');
 
         $userRole = Role::create(['name' => 'User']);
         $userRole->givePermissionTo('view order master');

         // gets all permissions via Gate::before rule
 
         // create demo users
         $user = User::factory()->create([
             'name' => 'IT Chutex',
             'email' => 'it.japer.12@gmail.com',
             'password' => bcrypt('japer321')
         ]);
         $user->assignRole($adminRole);
 
         $user = User::factory()->create([
             'name' => 'Dimas Galang R',
             'email' => 'dgalangramadhan@gmail.com',
             'password' => bcrypt('japer321')
         ]);
         $user->assignRole($userRole);
    }
}
