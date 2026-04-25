<?php

namespace Tests\Feature;

use App\Models\TeacherProfile;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class AdminUsersVisibilityTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        foreach (['student', 'teacher', 'admin'] as $role) {
            Role::firstOrCreate(['name' => $role, 'guard_name' => 'web']);
        }
    }

    public function test_admin_users_index_includes_dynamic_student_visibility_for_teachers(): void
    {
        $admin = User::factory()->create([
            'role' => 'admin',
            'status' => 'active',
        ]);
        $admin->assignRole('admin');

        $hiddenTeacher = User::factory()->create([
            'role' => 'teacher',
            'status' => 'active',
        ]);
        $hiddenTeacher->assignRole('teacher');
        TeacherProfile::factory()->create([
            'user_id' => $hiddenTeacher->id,
            'is_verified' => false,
        ]);

        $visibleTeacher = User::factory()->create([
            'role' => 'teacher',
            'status' => 'active',
        ]);
        $visibleTeacher->assignRole('teacher');
        TeacherProfile::factory()->create([
            'user_id' => $visibleTeacher->id,
            'is_verified' => true,
        ]);

        $response = $this->actingAs($admin)->getJson('/api/admin/users?role=teacher');

        $response->assertOk();

        $rows = collect($response->json('data'));

        $hidden = $rows->firstWhere('id', $hiddenTeacher->id);
        $visible = $rows->firstWhere('id', $visibleTeacher->id);

        $this->assertNotNull($hidden);
        $this->assertNotNull($visible);

        $this->assertFalse((bool) data_get($hidden, 'student_visibility.is_visible_to_students'));
        $this->assertSame('profile_not_verified', data_get($hidden, 'student_visibility.reasons.0.code'));

        $this->assertTrue((bool) data_get($visible, 'student_visibility.is_visible_to_students'));
        $this->assertSame([], data_get($visible, 'student_visibility.reasons'));
    }
}
