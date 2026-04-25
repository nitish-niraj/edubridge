<?php

namespace Tests\Feature;

use App\Mail\OtpMail;
use App\Models\StudentProfile;
use App\Models\TeacherProfile;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class LoginTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        foreach (['student', 'teacher', 'admin'] as $role) {
            Role::firstOrCreate(['name' => $role, 'guard_name' => 'web']);
        }
    }

    private function makeStudent(array $overrides = []): User
    {
        $user = User::factory()->create(array_merge([
            'email'             => 'student@login.com',
            'password'          => Hash::make('Password@123'),
            'role'              => 'student',
            'status'            => 'active',
            'email_verified_at' => now(),
        ], $overrides));
        $user->assignRole('student');
        StudentProfile::factory()->create(['user_id' => $user->id]);
        return $user;
    }

    private function makeTeacher(array $overrides = []): User
    {
        $user = User::factory()->create(array_merge([
            'email'             => 'teacher@login.com',
            'password'          => Hash::make('Password@123'),
            'role'              => 'teacher',
            'status'            => 'active',
            'email_verified_at' => now(),
        ], $overrides));
        $user->assignRole('teacher');
        TeacherProfile::factory()->create(['user_id' => $user->id]);
        return $user;
    }

    private function makeAdmin(array $overrides = []): User
    {
        $user = User::factory()->create(array_merge([
            'email'             => 'admin@login.com',
            'password'          => Hash::make('Password@123'),
            'role'              => 'admin',
            'status'            => 'active',
            'email_verified_at' => now(),
        ], $overrides));
        $user->assignRole('admin');
        return $user;
    }

    public function test_correct_credentials_redirect_to_student_dashboard(): void
    {
        $this->makeStudent();

        $response = $this->post('/login', [
            'email'    => 'student@login.com',
            'password' => 'Password@123',
        ]);

        $response->assertRedirect(route('student.dashboard'));
    }

    public function test_student_login_preserves_safe_redirect_path(): void
    {
        $this->makeStudent();

        $response = $this->post('/login', [
            'email' => 'student@login.com',
            'password' => 'Password@123',
            'redirect' => '/teachers',
        ]);

        $response->assertRedirect('/teachers');
    }

    public function test_student_login_rejects_external_redirect_path(): void
    {
        $this->makeStudent();

        $response = $this->post('/login', [
            'email' => 'student@login.com',
            'password' => 'Password@123',
            'redirect' => 'https://evil.example/phish',
        ]);

        $response->assertRedirect(route('student.dashboard'));
    }

    public function test_student_login_rejects_role_incompatible_redirect_path(): void
    {
        $this->makeStudent();

        $response = $this->post('/login', [
            'email' => 'student@login.com',
            'password' => 'Password@123',
            'redirect' => '/admin/users',
        ]);

        $response->assertRedirect(route('student.dashboard'));
    }

    public function test_wrong_password_returns_422(): void
    {
        $this->makeStudent();

        $response = $this->postJson('/login', [
            'email'    => 'student@login.com',
            'password' => 'WrongPassword',
        ]);

        $response->assertStatus(422);
    }

    public function test_correct_teacher_credentials_redirect_to_teacher_dashboard(): void
    {
        $this->makeTeacher();

        $response = $this->post('/login', [
            'email'    => 'teacher@login.com',
            'password' => 'Password@123',
        ]);

        $response->assertRedirect(route('teacher.dashboard'));
    }

    public function test_correct_admin_credentials_redirect_to_admin_dashboard(): void
    {
        $this->makeAdmin();

        $response = $this->post('/login', [
            'email'    => 'admin@login.com',
            'password' => 'Password@123',
        ]);

        $response->assertRedirect(route('admin.dashboard'));
    }

    public function test_admin_login_preserves_safe_admin_redirect_path(): void
    {
        $this->makeAdmin();

        $response = $this->post('/login', [
            'email' => 'admin@login.com',
            'password' => 'Password@123',
            'redirect' => '/admin/users',
        ]);

        $response->assertRedirect('/admin/users');
    }

    public function test_admin_two_factor_challenge_uses_intended_route_when_already_verified(): void
    {
        $admin = $this->makeAdmin([
            'two_factor_enabled' => true,
        ]);

        $response = $this->actingAs($admin)
            ->withSession([
                'admin_2fa_passed' => true,
                'url.intended' => '/admin/users',
            ])
            ->get('/admin/2fa');

        $response->assertRedirect('/admin/users');
    }

    public function test_admin_two_factor_challenge_uses_intended_route_when_two_factor_is_disabled(): void
    {
        $admin = $this->makeAdmin([
            'two_factor_enabled' => false,
        ]);

        $response = $this->actingAs($admin)
            ->withSession([
                'url.intended' => '/admin/users',
            ])
            ->get('/admin/2fa');

        $response->assertRedirect('/admin/users');
    }

    public function test_suspended_user_gets_blocked_with_message(): void
    {
        $this->makeStudent(['status' => 'suspended']);

        $response = $this->postJson('/login', [
            'email'    => 'student@login.com',
            'password' => 'Password@123',
        ]);
        $response->assertStatus(422);
        $this->assertGuest();
    }

    public function test_unverified_student_login_redirects_to_otp_flow(): void
    {
        Mail::fake();

        $user = $this->makeStudent([
            'email' => 'pending@login.com',
            'status' => 'pending',
            'email_verified_at' => null,
        ]);

        $response = $this->post('/login', [
            'email'    => 'pending@login.com',
            'password' => 'Password@123',
        ]);

        $response->assertRedirect(route('verify.otp.form', ['user_id' => $user->id]));
        $this->assertGuest();
        $this->assertDatabaseHas('verifications', [
            'user_id' => $user->id,
            'type'    => 'email',
        ]);
        Mail::assertSent(OtpMail::class);
    }
}
