<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class AdminSeeder extends Seeder
{
    public function run(): void
    {
        $admin = User::firstOrCreate(
            ['email' => 'admin@edubridge.com'],
            [
                'name'              => 'EduBridge Admin',
                'password'          => Hash::make('Admin@123'),
                'role'              => 'admin',
                'status'            => 'active',
                'email_verified_at' => now(),
            ]
        );

        $admin->assignRole('admin');
    }
}
