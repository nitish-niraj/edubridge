<?php

namespace Tests\Feature;

use App\Mail\OtpMail;
use App\Models\User;
use App\Models\StudentProfile;
use App\Models\Verification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class StudentRegistrationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        // Create roles directly — artisan db:seed opens a separate in-memory connection
        foreach (['student', 'teacher', 'admin'] as $role) {
            Role::firstOrCreate(['name' => $role, 'guard_name' => 'web']);
        }
    }

    public function test_successful_registration_creates_user_profile_and_otp(): void
    {
        Mail::fake();

        $response = $this->post(route('student.register.submit'), [
            'name'                  => 'Test Student',
            'email'                 => 'student@test.com',
            'phone'                 => '9876543210',
            'password'              => 'Password@123',
            'password_confirmation' => 'Password@123',
            'class_grade'           => 'Class 10',
            'school_name'           => 'Test School',
        ]);

        // User created
        $this->assertDatabaseHas('users', [
            'email' => 'student@test.com',
            'role'  => 'student',
            'status'=> 'pending',
        ]);

        $user = User::where('email', 'student@test.com')->first();
        $this->assertNotNull($user);

        // Student profile created
        $this->assertDatabaseHas('student_profiles', ['user_id' => $user->id]);

        // OTP record created
        $this->assertDatabaseHas('verifications', [
            'user_id' => $user->id,
            'type'    => 'email',
        ]);

        // OTP mail sent
        Mail::assertSent(OtpMail::class);

        // Redirected to OTP page
        $response->assertRedirect();
    }

    public function test_duplicate_email_returns_422(): void
    {
        User::factory()->create(['email' => 'duplicate@test.com', 'role' => 'student']);

        $response = $this->postJson(route('student.register.submit'), [
            'name'                  => 'Another Student',
            'email'                 => 'duplicate@test.com',
            'phone'                 => '9111111111',
            'password'              => 'Password@123',
            'password_confirmation' => 'Password@123',
        ]);

        $response->assertStatus(422);
    }

    public function test_otp_verification_sets_email_verified_at(): void
    {
        Mail::fake();

        // Register student
        $this->post(route('student.register.submit'), [
            'name'                  => 'OTP Student',
            'email'                 => 'otp@test.com',
            'phone'                 => '9000000001',
            'password'              => 'Password@123',
            'password_confirmation' => 'Password@123',
            'class_grade'           => 'Class 11',
            'school_name'           => 'OTP School',
        ]);

        $user = User::where('email', 'otp@test.com')->first();
        $this->assertNotNull($user);
        $this->assertNull($user->email_verified_at);

        // Get the actual OTP from the mail
        $otp = null;
        Mail::assertSent(OtpMail::class, function ($mail) use (&$otp) {
            $otp = $mail->otp;
            return true;
        });

        if ($otp) {
            $response = $this->post(route('verify.otp.submit'), [
                'user_id' => $user->id,
                'otp'     => $otp,
            ]);

            $user->refresh();
            $this->assertNotNull($user->email_verified_at);
            $this->assertEquals('active', $user->status);
        }
    }

    public function test_otp_resend_is_rate_limited_for_sixty_seconds(): void
    {
        Mail::fake();

        $user = User::factory()->create([
            'role' => 'student',
            'status' => 'pending',
            'email_verified_at' => null,
        ]);
        $user->assignRole('student');

        Verification::create([
            'user_id' => $user->id,
            'otp' => Hash::make('123456'),
            'type' => 'email',
            'expires_at' => now()->addMinutes(15),
        ]);

        $response = $this->from(route('verify.otp.form', ['user_id' => $user->id]))
            ->post(route('verify.otp.resend'), [
                'user_id' => $user->id,
            ]);

        $response->assertRedirect(route('verify.otp.form', ['user_id' => $user->id]));
        $response->assertSessionHasErrors('otp');
        Mail::assertNothingSent();
        $this->assertSame(1, Verification::where('user_id', $user->id)->count());
    }
}
