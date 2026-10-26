<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class CreateDefaultAdminSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // ADMIN ROLE ID
        $adminRoleId = \DB::table('roles')->where('name', 'Super Admin')->value('id');

        // EMPLOYEE ROLE ID
        $employeeRoleId = \DB::table('roles')->where('name', 'Employee')->value('id');

        // ✅ ADMIN
        \DB::table('users')->insert([
            'type' => 'admin',
            'role_id' => $adminRoleId,
            'username' => 'super_admin',
            'name' => 'Super Admin',
            'email' => 'super-admin@demo.com',
            'password' => bcrypt('711009'),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // ✅ EMPLOYEE
        \DB::table('users')->insert([
            'type' => 'employee',
            'role_id' => $employeeRoleId,
            'username' => 'employee_user',
            'name' => 'Employee User',
            'email' => 'employee@demo.com',
            'password' => bcrypt('711009'),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
}