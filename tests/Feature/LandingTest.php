<?php

namespace Tests\Feature;

use App\Models\TeacherProfile;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LandingTest extends TestCase
{
    use RefreshDatabase;

    public function test_landing_page_is_public_and_renders_featured_teacher(): void
    {
        $teacher = User::factory()->create([
            'name' => 'Aisha Mentor',
            'role' => 'teacher',
            'status' => 'active',
        ]);

        TeacherProfile::factory()->create([
            'user_id' => $teacher->id,
            'is_verified' => true,
            'rating_avg' => 4.9,
            'total_reviews' => 48,
            'subjects' => ['Mathematics', 'Physics'],
        ]);

        $response = $this->get(route('landing'));

        $response->assertOk();
        $response->assertViewIs('landing');
        $response->assertViewHas('featuredTeachers');
        $response->assertViewHas('stats');
    }

    public function test_authenticated_users_are_redirected_from_landing(): void
    {
        $student = User::factory()->create([
            'role' => 'student',
            'status' => 'active',
        ]);

        $response = $this->actingAs($student)->get(route('landing'));

        $response->assertRedirect(route('student.dashboard'));
    }
}
