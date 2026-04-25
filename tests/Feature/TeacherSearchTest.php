<?php

namespace Tests\Feature;

use App\Models\TeacherProfile;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TeacherSearchTest extends TestCase
{
    use RefreshDatabase;

    public function test_filter_by_subject_returns_only_matching_teachers(): void
    {
        $mathTeacher = User::factory()->create([
            'role' => 'teacher',
            'status' => 'active',
        ]);

        TeacherProfile::factory()->create([
            'user_id' => $mathTeacher->id,
            'is_verified' => true,
            'subjects' => ['Math', 'Science'],
        ]);

        $languageTeacher = User::factory()->create([
            'role' => 'teacher',
            'status' => 'active',
        ]);

        TeacherProfile::factory()->create([
            'user_id' => $languageTeacher->id,
            'is_verified' => true,
            'subjects' => ['Languages'],
        ]);

        $response = $this->getJson('/api/teachers?subjects[]=Math');

        $response->assertOk();
        $subjects = collect($response->json('data'))->pluck('subjects')->flatten()->all();
        $this->assertContains('Math', $subjects);
        $this->assertNotContains('Languages', $subjects);
    }

    public function test_sort_by_rating_returns_descending_order(): void
    {
        $first = User::factory()->create(['role' => 'teacher', 'status' => 'active']);
        $second = User::factory()->create(['role' => 'teacher', 'status' => 'active']);
        $third = User::factory()->create(['role' => 'teacher', 'status' => 'active']);

        TeacherProfile::factory()->create([
            'user_id' => $first->id,
            'is_verified' => true,
            'rating_avg' => 4.9,
        ]);
        TeacherProfile::factory()->create([
            'user_id' => $second->id,
            'is_verified' => true,
            'rating_avg' => 4.2,
        ]);
        TeacherProfile::factory()->create([
            'user_id' => $third->id,
            'is_verified' => true,
            'rating_avg' => 3.7,
        ]);

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
}
