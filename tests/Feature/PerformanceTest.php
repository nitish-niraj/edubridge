<?php

namespace Tests\Feature;

use App\Models\TeacherProfile;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Benchmark;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class PerformanceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        foreach (['student', 'teacher', 'admin'] as $role) {
            Role::firstOrCreate(['name' => $role, 'guard_name' => 'web']);
        }
    }

    private function seedTeacher(): void
    {
        $teacher = User::factory()->create([
            'name' => 'Cached Teacher',
            'role' => 'teacher',
            'status' => 'active',
        ]);

        $teacher->assignRole('teacher');

        TeacherProfile::factory()->create([
            'user_id' => $teacher->id,
            'is_verified' => true,
            'rating_avg' => 4.8,
            'subjects' => ['Math'],
            'languages' => ['English'],
        ]);
    }

    public function test_teacher_search_api_responds_under_500ms(): void
    {
        Cache::flush();
        $this->seedTeacher();

        $durationMs = Benchmark::measure(function (): void {
            $this->getJson('/api/teachers?sort=rating_desc')->assertOk();
        }, iterations: 1);

        $this->assertLessThan(500, $durationMs, sprintf('Teacher search took %.2fms', $durationMs));
    }

    public function test_teacher_search_results_are_cached_on_second_call(): void
    {
        Cache::flush();
        $this->seedTeacher();

        DB::flushQueryLog();
        DB::enableQueryLog();

        $firstResponse = $this->getJson('/api/teachers?sort=rating_desc');
        $firstResponse->assertOk();

        $firstQueryCount = collect(DB::getQueryLog())->count();
        $this->assertGreaterThan(0, $firstQueryCount);

        DB::flushQueryLog();

        $secondResponse = $this->getJson('/api/teachers?sort=rating_desc');
        $secondResponse->assertOk();

        $secondQueryCount = collect(DB::getQueryLog())->count();
        $this->assertSame(0, $secondQueryCount);
    }
}
