<?php

namespace Tests\Feature;

use App\Models\SavedTeacher;
use App\Models\TeacherAvailability;
use App\Models\TeacherProfile;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TeacherSearchTest extends TestCase
{
    use RefreshDatabase;

    private function createTeacher(array $userOverrides = [], array $profileOverrides = []): User
    {
        $teacher = User::factory()->create(array_merge([
            'role' => 'teacher',
            'status' => 'active',
            'avatar' => '/storage/avatars/teacher.jpg',
        ], $userOverrides));

        TeacherProfile::factory()->create(array_merge([
            'user_id' => $teacher->id,
            'is_verified' => true,
            'subjects' => ['Math', 'Science'],
            'languages' => ['English', 'Hindi'],
            'rating_avg' => 4.5,
            'total_reviews' => 10,
        ], $profileOverrides));

        return $teacher;
    }

    public function test_filter_by_subject_returns_only_matching_teachers(): void
    {
        $this->createTeacher(profileOverrides: ['subjects' => ['Math', 'Science']]);
        $this->createTeacher(profileOverrides: ['subjects' => ['Languages']]);

        $response = $this->getJson('/api/teachers?subjects[]=Math');

        $response->assertOk();
        $subjects = collect($response->json('data'))->pluck('subjects')->flatten()->all();
        $this->assertContains('Math', $subjects);
        $this->assertNotContains('Languages', $subjects);
    }

    public function test_sort_by_rating_returns_descending_order(): void
    {
        $this->createTeacher(profileOverrides: ['rating_avg' => 4.9]);
        $this->createTeacher(profileOverrides: ['rating_avg' => 4.2]);
        $this->createTeacher(profileOverrides: ['rating_avg' => 3.7]);

        $response = $this->getJson('/api/teachers?sort=rating_desc');
        $response->assertOk();

        $ratings = collect($response->json('data'))->pluck('rating_avg')->map(fn ($value) => (float) $value)->values()->all();
        $sorted = $ratings;
        rsort($sorted);

        $this->assertSame($sorted, $ratings);
    }

    public function test_unauthenticated_user_can_access_search(): void
    {
        $response = $this->getJson('/api/teachers');

        $response->assertOk();
    }

    public function test_listing_returns_only_search_eligible_teachers(): void
    {
        $eligible = $this->createTeacher(['name' => 'Eligible Teacher']);
        $this->createTeacher(['name' => 'Suspended Teacher', 'status' => 'suspended']);
        $this->createTeacher(['name' => 'No Avatar Teacher', 'avatar' => null]);
        $this->createTeacher(['name' => 'Unverified Teacher'], ['is_verified' => false]);
        $this->createTeacher(['name' => 'No Subjects Teacher'], ['subjects' => []]);
        $this->createTeacher(['name' => 'No Languages Teacher'], ['languages' => []]);

        $response = $this->getJson('/api/teachers');

        $response->assertOk();
        $teacherIds = collect($response->json('data'))->pluck('teacher_id');

        $this->assertTrue($teacherIds->contains($eligible->id));
        $this->assertCount(1, $teacherIds);
    }

    public function test_filter_by_language_returns_only_matching_teachers(): void
    {
        $englishTeacher = $this->createTeacher(profileOverrides: ['languages' => ['English']]);
        $this->createTeacher(profileOverrides: ['languages' => ['Tamil']]);

        $response = $this->getJson('/api/teachers?languages[]=English');

        $response->assertOk();
        $teacherIds = collect($response->json('data'))->pluck('teacher_id');

        $this->assertTrue($teacherIds->contains($englishTeacher->id));
        $this->assertCount(1, $teacherIds);
    }

    public function test_price_low_to_high_puts_free_teachers_first(): void
    {
        $paidLow = $this->createTeacher(profileOverrides: ['is_free' => false, 'hourly_rate' => 150]);
        $free = $this->createTeacher(profileOverrides: ['is_free' => true, 'hourly_rate' => null]);
        $paidHigh = $this->createTeacher(profileOverrides: ['is_free' => false, 'hourly_rate' => 500]);

        $response = $this->getJson('/api/teachers?sort=price_asc');

        $response->assertOk();
        $teacherIds = collect($response->json('data'))->pluck('teacher_id')->all();

        $this->assertSame([$free->id, $paidLow->id, $paidHigh->id], $teacherIds);
    }

    public function test_availability_day_filter_uses_availability_table(): void
    {
        $mondayTeacher = $this->createTeacher();
        $tuesdayTeacher = $this->createTeacher();

        TeacherAvailability::create([
            'teacher_id' => $mondayTeacher->id,
            'day_of_week' => 'monday',
            'start_time' => '09:00',
            'end_time' => '10:00',
            'is_active' => true,
        ]);

        TeacherAvailability::create([
            'teacher_id' => $tuesdayTeacher->id,
            'day_of_week' => 'tuesday',
            'start_time' => '09:00',
            'end_time' => '10:00',
            'is_active' => true,
        ]);

        $response = $this->getJson('/api/teachers?availability_days[]=Monday');

        $response->assertOk();
        $teacherIds = collect($response->json('data'))->pluck('teacher_id');

        $this->assertTrue($teacherIds->contains($mondayTeacher->id));
        $this->assertFalse($teacherIds->contains($tuesdayTeacher->id));
    }

    public function test_public_profile_visible_only_for_active_verified_teacher_and_hides_private_fields(): void
    {
        $teacher = $this->createTeacher(
            ['email' => 'hidden-teacher@example.com', 'phone' => '9999999999'],
            ['previous_school' => 'Hidden School']
        );
        $unverified = $this->createTeacher(profileOverrides: ['is_verified' => false]);
        $suspended = $this->createTeacher(['status' => 'suspended']);

        $response = $this->getJson("/api/teachers/{$teacher->id}");

        $response->assertOk()
            ->assertJsonPath('data.teacher_id', $teacher->id)
            ->assertJsonMissingPath('data.email')
            ->assertJsonMissingPath('data.phone')
            ->assertJsonMissingPath('data.previous_school');

        $this->getJson("/api/teachers/{$unverified->id}")->assertNotFound();
        $this->getJson("/api/teachers/{$suspended->id}")->assertNotFound();
    }

    public function test_saved_teacher_toggle_and_list_excludes_suspended_teachers(): void
    {
        $student = User::factory()->create([
            'role' => 'student',
            'status' => 'active',
        ]);
        $teacher = $this->createTeacher();
        $suspended = $this->createTeacher(['status' => 'suspended']);

        SavedTeacher::create([
            'student_id' => $student->id,
            'teacher_id' => $suspended->id,
        ]);

        $this->actingAs($student, 'sanctum')
            ->postJson("/api/students/saved-teachers/{$teacher->id}")
            ->assertCreated()
            ->assertJsonPath('saved', true);

        $listResponse = $this->actingAs($student, 'sanctum')
            ->getJson('/api/students/saved-teachers');

        $listResponse->assertOk();
        $teacherIds = collect($listResponse->json('data'))->pluck('teacher_id');

        $this->assertTrue($teacherIds->contains($teacher->id));
        $this->assertFalse($teacherIds->contains($suspended->id));

        $this->actingAs($student, 'sanctum')
            ->deleteJson("/api/students/saved-teachers/{$teacher->id}")
            ->assertOk()
            ->assertJsonPath('saved', false);

        $this->assertDatabaseMissing('saved_teachers', [
            'student_id' => $student->id,
            'teacher_id' => $teacher->id,
        ]);
    }
}
