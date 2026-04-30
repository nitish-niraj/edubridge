<?php

namespace Tests\Feature;

use App\Models\ClassMember;
use App\Models\Conversation;
use App\Models\TeacherProfile;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class GroupTest extends TestCase
{
    use RefreshDatabase;

    protected User $teacher;
    protected User $student;

    protected function setUp(): void
    {
        parent::setUp();
        $this->teacher = User::factory()->create(['role' => 'teacher']);
        TeacherProfile::factory()->create([
            'user_id' => $this->teacher->id,
            'is_verified' => true,
        ]);
        $this->student = User::factory()->create(['role' => 'student']);
    }

    public function test_group_creation_creates_conversation_with_correct_fields(): void
    {
        $response = $this->actingAs($this->teacher)->postJson('/api/groups', [
            'name'         => 'Class 12 Math — Batch A',
            'subject'      => 'Mathematics',
            'description'  => 'Advanced calculus',
            'max_students' => 25,
        ]);

        $response->assertStatus(201);
        $response->assertJsonStructure(['conversation', 'invite_code', 'invite_link']);

        $this->assertDatabaseHas('conversations', [
            'title'        => 'Class 12 Math — Batch A',
            'is_group'     => true,
            'subject'      => 'Mathematics',
            'teacher_id'   => $this->teacher->id,
            'max_students' => 25,
        ]);

        // Check invite_code is 8 chars
        $code = $response->json('invite_code');
        $this->assertEquals(8, strlen($code));

        // Teacher is a class member with role = teacher
        $this->assertDatabaseHas('class_members', [
            'user_id' => $this->teacher->id,
            'role'    => 'teacher',
        ]);
    }

    public function test_joining_via_invite_code_adds_participant(): void
    {
        $conv = Conversation::create([
            'created_by'   => $this->teacher->id,
            'title'        => 'Test Class',
            'is_group'     => true,
            'subject'      => 'Science',
            'teacher_id'   => $this->teacher->id,
            'max_students' => 30,
            'invite_code'  => 'TESTCODE',
        ]);

        $conv->participants()->attach($this->teacher->id, ['joined_at' => now()]);
        ClassMember::create(['conversation_id' => $conv->id, 'user_id' => $this->teacher->id, 'role' => 'teacher', 'joined_at' => now()]);

        $response = $this->actingAs($this->student)->postJson('/api/groups/join/TESTCODE');

        $response->assertOk();
        $response->assertJsonFragment(['message' => 'Successfully joined Test Class']);

        $this->assertDatabaseHas('class_members', [
            'conversation_id' => $conv->id,
            'user_id'         => $this->student->id,
            'role'            => 'student',
        ]);
    }

    public function test_max_students_limit_blocks_additional_joins(): void
    {
        $conv = Conversation::create([
            'created_by'   => $this->teacher->id,
            'title'        => 'Tiny Class',
            'is_group'     => true,
            'subject'      => 'Art',
            'teacher_id'   => $this->teacher->id,
            'max_students' => 1,
            'invite_code'  => 'TINYCODE',
        ]);

        $conv->participants()->attach($this->teacher->id, ['joined_at' => now()]);
        ClassMember::create(['conversation_id' => $conv->id, 'user_id' => $this->teacher->id, 'role' => 'teacher', 'joined_at' => now()]);

        // First student joins
        $s1 = User::factory()->create(['role' => 'student']);
        $conv->participants()->attach($s1->id, ['joined_at' => now()]);
        ClassMember::create(['conversation_id' => $conv->id, 'user_id' => $s1->id, 'role' => 'student', 'joined_at' => now()]);

        // Second student tries
        $response = $this->actingAs($this->student)->postJson('/api/groups/join/TINYCODE');

        $response->assertStatus(422);
        $response->assertJsonFragment(['message' => 'This class is full.']);
    }

    public function test_duplicate_join_returns_409(): void
    {
        $conv = Conversation::create([
            'created_by'   => $this->teacher->id,
            'title'        => 'Dup Test',
            'is_group'     => true,
            'subject'      => 'Music',
            'teacher_id'   => $this->teacher->id,
            'max_students' => 30,
            'invite_code'  => 'DUPECODE',
        ]);

        $conv->participants()->attach([$this->teacher->id => ['joined_at' => now()], $this->student->id => ['joined_at' => now()]]);
        ClassMember::create(['conversation_id' => $conv->id, 'user_id' => $this->teacher->id, 'role' => 'teacher', 'joined_at' => now()]);
        ClassMember::create(['conversation_id' => $conv->id, 'user_id' => $this->student->id, 'role' => 'student', 'joined_at' => now()]);

        $response = $this->actingAs($this->student)->postJson('/api/groups/join/DUPECODE');

        $response->assertStatus(409);
        $response->assertJsonFragment(['message' => 'You are already a member of this class.']);
    }
}
