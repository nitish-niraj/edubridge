<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class AdminSuspendTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        foreach (['student', 'teacher', 'admin'] as $role) {
            Role::firstOrCreate(['name' => $role, 'guard_name' => 'web']);
        }
    }

    private function makeAdmin(): User
    {
        $user = User::factory()->create([
            'role'   => 'admin',
            'status' => 'active',
        ]);

        $user->assignRole('admin');

        return $user;
    }

    private function makeStudent(array $overrides = []): User
    {
        $user = User::factory()->create(array_merge([
            'role'   => 'student',
            'status' => 'active',
            'password' => 'Password@123',
        ], $overrides));

        $user->assignRole('student');

        return $user;
    }

    public function test_suspending_user_sets_status_to_suspended(): void
    {
        Mail::fake();

        $admin = $this->makeAdmin();
        $student = $this->makeStudent();

        Sanctum::actingAs($admin);

        $response = $this->postJson("/api/admin/users/{$student->id}/suspend", [
            'reason' => 'Repeated policy violations.',
        ]);

        $response->assertOk();
        $response->assertJsonFragment(['message' => 'User suspended.']);

        $student->refresh();
        $this->assertSame('suspended', $student->status);
    }

    public function test_suspended_user_login_returns_blocked_message_not_401(): void
    {
        $student = $this->makeStudent([
            'status' => 'suspended',
        ]);

        $response = $this->postJson('/login', [
            'email'    => $student->email,
            'password' => 'Password@123',
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors('email');
        $response->assertJsonPath('errors.email.0', 'Your account has been suspended. Contact support at support@edubridge.com.');
    }
}
