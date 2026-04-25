<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Role;

class RoleSeeder extends Seeder
{
    public function run(): void
    {
        foreach (['student', 'teacher', 'admin'] as $role) {
            if (!Role::where('name', $role)->where('guard_name', 'web')->exists()) {
                Role::create(['name' => $role, 'guard_name' => 'web']);
            }
        }
    }
}
