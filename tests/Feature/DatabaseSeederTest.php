<?php

namespace Tests\Feature;

use App\Models\TeacherProfile;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class DatabaseSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_database_seeder_runs_cleanly_from_a_fresh_database(): void
    {
        $this->artisan('db:seed')
            ->assertExitCode(0);

        $this->assertSame(3, Role::count());
        $this->assertDatabaseHas('users', [
            'email' => 'admin@edubridge.com',
            'role' => 'admin',
            'status' => 'active',
        ]);

        $this->assertSame(10, TeacherProfile::where('is_verified', true)->count());
        $this->assertSame(5, User::where('role', 'student')->count());
    }
}
