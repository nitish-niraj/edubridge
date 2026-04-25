<?php

namespace App\Http\Controllers;

use App\Models\Booking;
use App\Models\Review;
use App\Models\User;
use App\Services\SeoService;
use Illuminate\Database\QueryException;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

class LandingController extends Controller
{
    public function __construct(
        private readonly SeoService $seoService,
    ) {}

    public function index(Request $request): View|RedirectResponse
    {
        if ($request->user()) {
            return match ($request->user()->role) {
                'student' => redirect()->route('student.dashboard'),
                'teacher' => redirect()->route('teacher.dashboard'),
                'admin' => redirect()->route('admin.dashboard'),
                default => redirect()->route('login'),
            };
        }

        $statsResolver = function (): array {
            return [
                'teachers' => User::query()->where('role', 'teacher')->where('status', 'active')->count(),
                'students' => User::query()->where('role', 'student')->where('status', 'active')->count(),
                'sessions' => Booking::query()->where('status', 'completed')->count(),
                'reviews' => Review::query()->where('is_visible', true)->count(),
            ];
        };

        try {
            $stats = app()->environment('testing')
                ? Cache::remember('landing:stats:v1', now()->addHour(), $statsResolver)
                : $this->rememberLandingStats($statsResolver);

            $featuredTeachers = User::query()
                ->select('users.*')
                ->with(['teacherProfile' => function ($query): void {
                    $query->select('id', 'user_id', 'subjects', 'hourly_rate', 'is_free', 'rating_avg', 'total_reviews', 'is_verified');
                }])
                ->join('teacher_profiles', 'teacher_profiles.user_id', '=', 'users.id')
                ->where('users.role', 'teacher')
                ->where('users.status', 'active')
                ->where('teacher_profiles.is_verified', true)
                ->orderByDesc('teacher_profiles.rating_avg')
                ->limit(4)
                ->get();
        } catch (QueryException) {
            // Keep landing page available when local database service is temporarily down.
            $stats = [
                'teachers' => 0,
                'students' => 0,
                'sessions' => 0,
                'reviews' => 0,
            ];

            $featuredTeachers = collect();
        }

        $seo = $this->seoService->page([
            'title' => 'EduBridge | Learn confidently with verified teachers',
            'description' => 'Join EduBridge to discover trusted teachers, book secure classes, and track your learning progress in one place.',
            'keywords' => [
                'online classes',
                'trusted tutors',
                'teacher marketplace',
                'student learning',
                'EduBridge',
            ],
            'url' => route('landing'),
        ]);

        return view('landing', [
            'stats' => $stats,
            'featuredTeachers' => $featuredTeachers,
            'seoTags' => $this->seoService->render($seo),
        ]);
    }

    private function rememberLandingStats(\Closure $resolver): array
    {
        try {
            return Cache::store('redis')->remember('landing:stats:v1', now()->addHour(), $resolver);
        } catch (\Throwable) {
            return Cache::remember('landing:stats:v1', now()->addHour(), $resolver);
        }
    }
}
