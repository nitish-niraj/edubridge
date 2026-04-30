<?php

namespace Tests\Feature;

use App\Mail\TeacherApprovedMail;
use App\Mail\TeacherRejectedMail;
use App\Models\TeacherDocument;
use App\Models\TeacherProfile;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class AdminVerificationTest extends TestCase
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

    private function makeTeacherWithPendingProfile(): TeacherProfile
    {
        $teacher = User::factory()->create([
            'role'   => 'teacher',
            'status' => 'active',
        ]);

        $teacher->assignRole('teacher');

        return TeacherProfile::factory()->create([
            'user_id'     => $teacher->id,
            'is_verified' => false,
        ]);
    }

    private function makeFullyVerifiableTeacherProfile(): TeacherProfile
    {
        $profile = $this->makeTeacherWithPendingProfile();

        TeacherDocument::create([
            'teacher_id' => $profile->id,
            'type' => 'degree',
            'file_path' => 'verifications/degree.pdf',
            'original_filename' => 'degree.pdf',
            'status' => 'approved',
            'reviewed_at' => now(),
        ]);

        TeacherDocument::create([
            'teacher_id' => $profile->id,
            'type' => 'service_record',
            'file_path' => 'verifications/service-record.pdf',
            'original_filename' => 'service-record.pdf',
            'status' => 'approved',
            'reviewed_at' => now(),
        ]);

        return $profile;
    }

    private function makePendingRequiredDocumentsTeacherProfile(): TeacherProfile
    {
        $profile = $this->makeTeacherWithPendingProfile();

        TeacherDocument::create([
            'teacher_id' => $profile->id,
            'type' => 'degree',
            'file_path' => 'verifications/degree-pending.pdf',
            'original_filename' => 'degree-pending.pdf',
            'status' => 'pending',
            'uploaded_at' => now(),
        ]);

        TeacherDocument::create([
            'teacher_id' => $profile->id,
            'type' => 'service_record',
            'file_path' => 'verifications/service-record-pending.pdf',
            'original_filename' => 'service-record-pending.pdf',
            'status' => 'pending',
            'uploaded_at' => now(),
        ]);

        return $profile;
    }

    private function makeSinglePendingDocumentTeacherProfile(): TeacherProfile
    {
        $profile = $this->makeTeacherWithPendingProfile();

        TeacherDocument::create([
            'teacher_id' => $profile->id,
            'type' => 'degree',
            'file_path' => 'verifications/degree-only.pdf',
            'original_filename' => 'degree-only.pdf',
            'status' => 'pending',
            'uploaded_at' => now(),
        ]);

        return $profile;
    }

    public function test_approving_teacher_sets_verified_and_sends_mail(): void
    {
        Mail::fake();

        $admin = $this->makeAdmin();
        $profile = $this->makeFullyVerifiableTeacherProfile();

        $response = $this->actingAs($admin)->postJson(route('admin.verifications.approve', $profile->id));

        $response->assertOk();
        $this->assertStringContainsString('Teacher approved successfully', (string) $response->json('message'));

        $profile->refresh();
        $this->assertTrue($profile->is_verified);

        Mail::assertSent(TeacherApprovedMail::class, function (TeacherApprovedMail $mail) use ($profile): bool {
            return $mail->user->is($profile->user);
        });
    }

    public function test_rejection_sends_mail_with_reason(): void
    {
        Mail::fake();

        $admin = $this->makeAdmin();
        $profile = $this->makeTeacherWithPendingProfile();
        $reason = 'Missing verified teaching credentials.';

        $response = $this->actingAs($admin)->postJson(route('admin.verifications.reject', $profile->id), [
            'reason' => $reason,
        ]);

        $response->assertOk();
        $this->assertStringContainsString('Teacher application rejected', (string) $response->json('message'));

        Mail::assertSent(TeacherRejectedMail::class, function (TeacherRejectedMail $mail) use ($profile, $reason): bool {
            return $mail->user->is($profile->user) && $mail->reason === $reason;
        });

        $this->assertDatabaseHas('teacher_profiles', [
            'id' => $profile->id,
            'is_verified' => false,
        ]);
    }

    public function test_approving_pending_required_documents_marks_profile_verified_and_documents_approved(): void
    {
        Mail::fake();

        $admin = $this->makeAdmin();
        $profile = $this->makePendingRequiredDocumentsTeacherProfile();

        $response = $this->actingAs($admin)->postJson(route('admin.verifications.approve', $profile->id));

        $response->assertOk();
        $this->assertStringContainsString('Teacher approved successfully', (string) $response->json('message'));

        $profile->refresh();
        $this->assertTrue($profile->is_verified);

        $this->assertDatabaseHas('teacher_documents', [
            'teacher_id' => $profile->id,
            'type' => 'degree',
            'status' => 'approved',
        ]);

        $this->assertDatabaseHas('teacher_documents', [
            'teacher_id' => $profile->id,
            'type' => 'service_record',
            'status' => 'approved',
        ]);

        Mail::assertSent(TeacherApprovedMail::class, function (TeacherApprovedMail $mail) use ($profile): bool {
            return $mail->user->is($profile->user);
        });
    }

    public function test_approving_single_pending_document_teacher_profile_is_rejected(): void
    {
        Mail::fake();

        $admin = $this->makeAdmin();
        $profile = $this->makeSinglePendingDocumentTeacherProfile();

        $response = $this->actingAs($admin)->postJson(route('admin.verifications.approve', $profile->id));

        $response->assertStatus(422);
        $this->assertStringContainsString('Required verification documents', (string) $response->json('message'));

        $profile->refresh();
        $this->assertFalse($profile->is_verified);
    }

    public function test_verification_actions_create_audit_logs(): void
    {
        Mail::fake();

        $admin = $this->makeAdmin();
        $profile = $this->makePendingRequiredDocumentsTeacherProfile();

        $this->actingAs($admin)->postJson(route('admin.verifications.approve', $profile->id))->assertOk();

        $this->assertDatabaseHas('audit_logs', [
            'admin_id' => $admin->id,
            'action' => 'teacher_verification.approved',
            'entity_type' => 'TeacherProfile',
            'entity_id' => $profile->id,
        ]);
    }

    public function test_non_admin_cannot_access_verification_endpoints(): void
    {
        $teacher = User::factory()->create([
            'role'   => 'teacher',
            'status' => 'active',
        ]);

        $teacher->assignRole('teacher');

        $profile = $this->makeTeacherWithPendingProfile();

        $this->actingAs($teacher, 'web')
            ->getJson(route('admin.verifications'))
            ->assertStatus(403);

        $this->actingAs($teacher, 'web')
            ->postJson(route('admin.verifications.approve', $profile->id))
            ->assertStatus(403);

        $this->actingAs($teacher, 'web')
            ->postJson(route('admin.verifications.reject', $profile->id), ['reason' => 'Not enough documents.'])
            ->assertStatus(403);
    }
}
