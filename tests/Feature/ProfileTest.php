<?php

namespace Tests\Feature;

use App\Models\StudentProfile;
use App\Models\TeacherProfile;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class ProfileTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        foreach (['student', 'teacher', 'admin'] as $role) {
            Role::firstOrCreate(['name' => $role, 'guard_name' => 'web']);
        }
    }

    private function makeStudent(): User
    {
        $user = User::factory()->create([
            'role'              => 'student',
            'status'            => 'active',
            'email_verified_at' => now(),
        ]);
        $user->assignRole('student');
        StudentProfile::factory()->create(['user_id' => $user->id]);
        return $user;
    }

    private function makeTeacher(): User
    {
        $user = User::factory()->create([
            'role'              => 'teacher',
            'status'            => 'active',
            'email_verified_at' => now(),
        ]);
        $user->assignRole('teacher');
        TeacherProfile::factory()->create(['user_id' => $user->id]);

        return $user;
    }

    private function makeAdmin(): User
    {
        $user = User::factory()->create([
            'role'              => 'admin',
            'status'            => 'active',
            'email_verified_at' => now(),
        ]);
        $user->assignRole('admin');

        return $user;
    }

    private function fakeJpegAvatar(): UploadedFile
    {
        return UploadedFile::fake()->createWithContent(
            'avatar.jpg',
            base64_decode('/9j/4AAQSkZJRgABAQEASABIAAD/2wBDAP//////////////////////////////////////////////////////////////////////////////////////wgALCAABAAEBAREA/8QAFBABAAAAAAAAAAAAAAAAAAAAAP/aAAgBAQABPxA=')
        );
    }

    public function test_student_profile_page_is_displayed(): void
    {
        $user = $this->makeStudent();

        $response = $this->actingAs($user)->get(route('student.profile'));

        $response->assertOk();
    }

    public function test_student_profile_information_can_be_updated(): void
    {
        $user = $this->makeStudent();

        $response = $this
            ->actingAs($user)
            ->patch(route('student.profile.update'), [
                'name'               => 'Updated Student',
                'class_grade'        => 'Class 12',
                'school_name'        => 'New School',
                'subjects_needed'    => ['Math', 'Physics'],
                'preferred_language' => 'English',
            ]);

        $response->assertOk();

        $this->assertSame('Updated Student', $user->refresh()->name);
    }

    public function test_student_profile_requires_authentication(): void
    {
        $response = $this->get(route('student.profile'));

        $response->assertRedirect(route('login'));
    }

    public function test_shared_account_profile_update_persists_student_fields_and_avatar(): void
    {
        Storage::fake('public');
        $user = $this->makeStudent();

        $response = $this->actingAs($user)
            ->withHeader('Accept', 'application/json')
            ->patch(route('account.profile.update'), [
                'name' => 'Student Shared Update',
                'email' => 'student-shared@example.com',
                'class_grade' => 'Class 10',
                'school_name' => 'Springfield School',
                'subjects_needed' => ['Math', 'Science'],
                'preferred_language' => 'English',
                'avatar' => $this->fakeJpegAvatar(),
            ]);

        $response->assertOk()
            ->assertJsonPath('user.name', 'Student Shared Update')
            ->assertJsonPath('user.email', 'student-shared@example.com');

        $this->assertDatabaseHas('users', [
            'id' => $user->id,
            'name' => 'Student Shared Update',
            'email' => 'student-shared@example.com',
        ]);

        $this->assertDatabaseHas('student_profiles', [
            'user_id' => $user->id,
            'class_grade' => 'Class 10',
            'school_name' => 'Springfield School',
            'preferred_language' => 'English',
        ]);

        $this->assertStringContainsString('/storage/avatars/', (string) $user->fresh()->avatar);
    }

    public function test_shared_account_profile_update_persists_teacher_fields_and_avatar(): void
    {
        Storage::fake('public');
        $user = $this->makeTeacher();

        $response = $this->actingAs($user)
            ->withHeader('Accept', 'application/json')
            ->patch(route('account.profile.update'), [
                'name' => 'Teacher Shared Update',
                'email' => 'teacher-shared@example.com',
                'bio' => 'Updated teacher biography from account settings.',
                'experience_years' => 12,
                'previous_school' => 'National Public School',
                'avatar' => $this->fakeJpegAvatar(),
            ]);

        $response->assertOk()
            ->assertJsonPath('user.name', 'Teacher Shared Update')
            ->assertJsonPath('user.email', 'teacher-shared@example.com');

        $this->assertDatabaseHas('users', [
            'id' => $user->id,
            'name' => 'Teacher Shared Update',
            'email' => 'teacher-shared@example.com',
        ]);

        $this->assertDatabaseHas('teacher_profiles', [
            'user_id' => $user->id,
            'bio' => 'Updated teacher biography from account settings.',
            'experience_years' => 12,
            'previous_school' => 'National Public School',
        ]);

        $this->assertStringContainsString('/storage/avatars/', (string) $user->fresh()->avatar);
    }

    public function test_shared_account_profile_update_persists_admin_fields_and_avatar(): void
    {
        Storage::fake('public');
        $user = $this->makeAdmin();

        $response = $this->actingAs($user)
            ->withHeader('Accept', 'application/json')
            ->patch(route('account.profile.update'), [
                'name' => 'Admin Shared Update',
                'email' => 'admin-shared@example.com',
                'avatar' => $this->fakeJpegAvatar(),
            ]);

        $response->assertOk()
            ->assertJsonPath('user.name', 'Admin Shared Update')
            ->assertJsonPath('user.email', 'admin-shared@example.com');

        $this->assertDatabaseHas('users', [
            'id' => $user->id,
            'name' => 'Admin Shared Update',
            'email' => 'admin-shared@example.com',
        ]);

        $this->assertStringContainsString('/storage/avatars/', (string) $user->fresh()->avatar);
    }
}
