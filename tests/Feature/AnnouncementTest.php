<?php

namespace Tests\Feature;

use App\Jobs\BulkAnnouncementEmailJob;
use App\Mail\AnnouncementMail;
use App\Models\Announcement;
use App\Models\ClassMember;
use App\Models\Conversation;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AnnouncementTest extends TestCase
{
    use RefreshDatabase;

    protected User $teacher;
    protected User $student;
    protected Conversation $group;

    protected function setUp(): void
    {
        parent::setUp();

        $this->teacher = User::factory()->create(['role' => 'teacher']);
        $this->student = User::factory()->create(['role' => 'student']);

        $this->group = Conversation::create([
            'created_by'   => $this->teacher->id,
            'title'        => 'Announcement Test Class',
            'is_group'     => true,
            'subject'      => 'Math',
            'teacher_id'   => $this->teacher->id,
            'max_students' => 30,
            'invite_code'  => 'ANNCTEST',
        ]);

        $this->group->participants()->attach([
            $this->teacher->id => ['joined_at' => now()],
            $this->student->id => ['joined_at' => now()],
        ]);

        ClassMember::create(['conversation_id' => $this->group->id, 'user_id' => $this->teacher->id, 'role' => 'teacher', 'joined_at' => now()]);
        ClassMember::create(['conversation_id' => $this->group->id, 'user_id' => $this->student->id, 'role' => 'student', 'joined_at' => now()]);
    }

    public function test_announcement_message_type_is_stored(): void
    {
        $response = $this->actingAs($this->teacher)
            ->postJson("/api/conversations/{$this->group->id}/announcements", [
                'body' => 'Quiz tomorrow at 10 AM!',
            ]);

        $response->assertCreated();

        $this->assertDatabaseHas('messages', [
            'conversation_id' => $this->group->id,
            'type'            => 'announcement',
            'body'            => 'Quiz tomorrow at 10 AM!',
        ]);
    }

    public function test_non_teacher_cannot_post_announcements(): void
    {
        $response = $this->actingAs($this->student)
            ->postJson("/api/conversations/{$this->group->id}/announcements", [
                'body' => 'I should not be able to post this.',
            ]);

        $response->assertStatus(403);
    }

    public function test_pinned_announcement_returns_latest(): void
    {
        // Post two announcements
        $this->actingAs($this->teacher)
            ->postJson("/api/conversations/{$this->group->id}/announcements", ['body' => 'First announcement']);
        $this->actingAs($this->teacher)
            ->postJson("/api/conversations/{$this->group->id}/announcements", ['body' => 'Latest announcement']);

        $response = $this->actingAs($this->student)
            ->getJson("/api/conversations/{$this->group->id}/pinned-announcement");

        $response->assertOk();
        $response->assertJsonFragment(['body' => 'Latest announcement']);
    }

    public function test_student_targeted_active_announcement_is_not_returned_for_teacher(): void
    {
        $announcement = Announcement::create([
            'title'         => 'Student Notice',
            'message'       => 'Students only.',
            'target_role'   => 'student',
            'delivery_type' => 'banner',
            'is_active'     => true,
            'starts_at'     => now()->subHour(),
            'ends_at'       => now()->addHour(),
            'created_by'    => $this->teacher->id,
        ]);

        Sanctum::actingAs($this->teacher);

        $response = $this->getJson('/api/announcements/active');

        $response->assertOk();
        $response->assertJsonMissing(['id' => $announcement->id]);
    }

    public function test_bulk_announcement_email_job_sends_expected_number_of_mailables(): void
    {
        Mail::fake();

        $announcement = Announcement::create([
            'title'         => 'Platform Update',
            'message'       => 'Scheduled maintenance tonight.',
            'target_role'   => 'student',
            'delivery_type' => 'email',
            'is_active'     => true,
            'starts_at'     => now()->subHour(),
            'ends_at'       => now()->addHour(),
            'created_by'    => $this->teacher->id,
        ]);

        User::factory()->count(2)->create([
            'role'   => 'student',
            'status' => 'active',
        ]);

        User::factory()->count(2)->create([
            'role'   => 'teacher',
            'status' => 'active',
        ]);

        User::factory()->create([
            'role'   => 'student',
            'status' => 'suspended',
        ]);

        (new BulkAnnouncementEmailJob($announcement->id))->handle();

        Mail::assertSentCount(3);
        Mail::assertSent(AnnouncementMail::class, function (AnnouncementMail $mail) use ($announcement): bool {
            return $mail->announcement->is($announcement);
        });

        $announcement->refresh();
        $this->assertSame(3, $announcement->sent_count);
    }
}
