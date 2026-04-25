<?php

namespace Tests\Feature;

use App\Models\TeacherProfile;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class TeacherPortalNavigationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        foreach (['student', 'teacher', 'admin'] as $role) {
            Role::firstOrCreate(['name' => $role, 'guard_name' => 'web']);
        }
    }

    private function makeTeacher(): User
    {
        $user = User::factory()->create([
            'email' => 'teacher-nav@test.com',
            'password' => Hash::make('Password@123'),
            'role' => 'teacher',
            'status' => 'active',
            'email_verified_at' => now(),
        ]);

        $user->assignRole('teacher');
        TeacherProfile::factory()->create(['user_id' => $user->id]);

        return $user;
    }

    public function test_teacher_can_open_core_navigation_pages(): void
    {
        $user = $this->makeTeacher();

        $this->actingAs($user)->get(route('teacher.dashboard'))->assertOk();
        $this->actingAs($user)->get(route('teacher.profile.step', ['step' => 1]))->assertOk();
        $this->actingAs($user)->get(route('teacher.availability'))->assertOk();
        $this->actingAs($user)->get(route('teacher.sessions'))->assertOk();
        $this->actingAs($user)->get(route('teacher.chat'))->assertOk();
        $this->actingAs($user)->get(route('teacher.settings'))->assertOk();
    }
}
