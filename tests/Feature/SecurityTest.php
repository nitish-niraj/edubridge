<?php

namespace Tests\Feature;

use App\Models\TeacherProfile;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class SecurityTest extends TestCase
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
            'email' => 'student@security.test',
            'password' => Hash::make('Password@123'),
            'role' => 'student',
            'status' => 'active',
        ]);

        $user->assignRole('student');

        return $user;
    }

    public function test_login_returns_429_after_multiple_failed_attempts(): void
    {
        $this->makeStudent();

        for ($attempt = 1; $attempt <= 6; $attempt++) {
            $response = $this->postJson('/login', [
                'email' => 'student@security.test',
                'password' => 'WrongPassword',
            ]);

            if ($attempt < 6) {
                $response->assertStatus(422);
                continue;
            }

            if ($response->status() === 500) {
                $response->dump();
            }
            $response->assertStatus(429);
        }
    }

    public function test_uploading_a_double_extension_feedback_file_is_rejected(): void
    {
        $response = $this->postJson('/api/feedback', [
            'type' => 'bug',
            'description' => 'Test',
            'page_url' => 'http://localhost/teachers',
            'screenshot' => UploadedFile::fake()->image('evidence.php.jpg'),
        ]);

        $response->assertStatus(422);
    }

    public function test_uploading_a_php_file_is_rejected(): void
    {
        $response = $this->postJson('/api/feedback', [
            'type' => 'bug',
            'description' => 'Test',
            'page_url' => 'http://localhost/teachers',
            'screenshot' => UploadedFile::fake()->create('payload.php', 4, 'application/x-php'),
        ]);

        $response->assertStatus(422);
    }

    public function test_security_headers_are_present_on_web_response(): void
    {
        $response = $this->get('/');

        $response->assertHeader('X-Frame-Options', 'DENY');
        $response->assertHeader('X-Content-Type-Options', 'nosniff');
        $response->assertHeader('Referrer-Policy', 'strict-origin-when-cross-origin');
    }
}
