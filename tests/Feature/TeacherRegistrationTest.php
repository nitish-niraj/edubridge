<?php

namespace Tests\Feature;

use App\Mail\OtpMail;
use App\Models\User;
use App\Models\TeacherProfile;
use App\Models\Verification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class TeacherRegistrationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        foreach (['student', 'teacher', 'admin'] as $role) {
            Role::firstOrCreate(['name' => $role, 'guard_name' => 'web']);
        }
    }

    public function test_successful_registration_creates_user_profile_and_otp(): void
    {
        Mail::fake();

        $response = $this->post(route('teacher.register.submit'), [
            'name'                  => 'Test Teacher',
            'email'                 => 'teacher@test.com',
            'phone'                 => '9876543200',
            'gender'                => 'female',
            'password'              => 'Password@123',
            'password_confirmation' => 'Password@123',
        ]);

        $this->assertDatabaseHas('users', [
            'email' => 'teacher@test.com',
            'role'  => 'teacher',
            'status'=> 'pending',
        ]);

        $user = User::where('email', 'teacher@test.com')->first();
        $this->assertNotNull($user);

        $this->assertDatabaseHas('teacher_profiles', ['user_id' => $user->id]);
        $this->assertDatabaseHas('verifications', ['user_id' => $user->id, 'type' => 'email']);

        Mail::assertSent(OtpMail::class);
        $response->assertRedirect();
    }

    public function test_duplicate_email_returns_422(): void
    {
        User::factory()->create(['email' => 'dup.teacher@test.com', 'role' => 'teacher']);

        $response = $this->postJson(route('teacher.register.submit'), [
            'name'                  => 'Another Teacher',
            'email'                 => 'dup.teacher@test.com',
            'phone'                 => '9222222222',
            'gender'                => 'male',
            'password'              => 'Password@123',
            'password_confirmation' => 'Password@123',
        ]);

        $response->assertStatus(422);
    }

    public function test_otp_verification_sets_email_verified_at(): void
    {
        Mail::fake();

        $this->post(route('teacher.register.submit'), [
            'name'                  => 'OTP Teacher',
            'email'                 => 'otpteacher@test.com',
            'phone'                 => '9000000002',
            'gender'                => 'male',
            'password'              => 'Password@123',
            'password_confirmation' => 'Password@123',
        ]);

        $user = User::where('email', 'otpteacher@test.com')->first();
        $this->assertNotNull($user);
        $this->assertNull($user->email_verified_at);

        $otp = null;
        Mail::assertSent(OtpMail::class, function ($mail) use (&$otp) {
            $otp = $mail->otp;
            return true;
        });

        if ($otp) {
            $this->post(route('verify.otp.submit'), [
                'user_id' => $user->id,
                'otp'     => $otp,
            ]);

            $user->refresh();
            $this->assertNotNull($user->email_verified_at);
            $this->assertEquals('active', $user->status);
        }
    }
}
