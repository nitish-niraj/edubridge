<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     *
     * Run order:
     *  1. RoleSeeder   — must be first (other seeders assign roles)
     *  2. AdminSeeder
     *  3. TeacherSeeder
     *  4. StudentSeeder
     *  5. DemoDataSeeder
     */
    public function run(): void
    {
        $this->call([
            RoleSeeder::class,
            AdminSeeder::class,
            TeacherSeeder::class,
            StudentSeeder::class,
            DemoDataSeeder::class,
        ]);
    }
}
