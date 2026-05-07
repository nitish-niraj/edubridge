<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\AnalyticsRangeRequest;
use App\Models\Booking;
use App\Models\Payment;
use App\Models\TeacherEarning;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class AnalyticsController extends Controller
{
    public function overview(AnalyticsRangeRequest $request): JsonResponse
    {
        $validated = $request->validated();
        $from = $validated['from'] ?? now()->subDays(30)->toDateString();
        $to = $validated['to'] ?? now()->toDateString();
        $cacheKey = "analytics.overview.{$from}.{$to}";

        $data = $this->rememberAnalytics($cacheKey, function () use ($from, $to) {
            return [
                'total_users' => User::where('status', 'active')->count(),
                'new_registrations' => User::whereBetween('created_at', [$from, $to . ' 23:59:59'])->count(),
                'sessions_completed' => Booking::where('status', 'completed')->whereBetween('start_at', [$from, $to . ' 23:59:59'])->count(),
                'revenue' => Payment::whereIn('status', ['held', 'released'])->whereBetween('paid_at', [$from, $to . ' 23:59:59'])->sum('amount'),
                'daily_users' => User::select(DB::raw('DATE(created_at) as date'), DB::raw('COUNT(*) as count'))
                    ->whereBetween('created_at', [$from, $to . ' 23:59:59'])
                    ->groupBy('date')
                    ->orderBy('date')
                    ->pluck('count', 'date'),
                'daily_sessions' => Booking::select(DB::raw('DATE(start_at) as date'), DB::raw('COUNT(*) as count'))
                    ->where('status', 'completed')
                    ->whereBetween('start_at', [$from, $to . ' 23:59:59'])
                    ->groupBy('date')
                    ->orderBy('date')
                    ->pluck('count', 'date'),
            ];
        });

        return response()->json($data);
    }

    public function users(AnalyticsRangeRequest $request): JsonResponse
    {
        $validated = $request->validated();
        $from = $validated['from'] ?? now()->subWeeks(12)->toDateString();
        $to = $validated['to'] ?? now()->toDateString();
        $cacheKey = "analytics.users.{$from}.{$to}";

        $data = $this->rememberAnalytics($cacheKey, function () use ($from, $to) {
            $topCities = [];
            if (Schema::hasColumn('users', 'city')) {
                $topCities = User::query()
                    ->select('city', DB::raw('COUNT(*) as count'))
                    ->whereNotNull('city')
                    ->whereBetween('created_at', [$from, $to . ' 23:59:59'])
                    ->groupBy('city')
                    ->orderByDesc('count')
                    ->limit(10)
                    ->get()
                    ->map(fn ($row) => [
                        'city' => $row->city,
                        'count' => (int) $row->count,
                    ])
                    ->values()
                    ->all();
            }

            return [
                'weekly_students' => User::select(DB::raw('YEARWEEK(created_at) as week'), DB::raw('COUNT(*) as count'))
                    ->where('role', 'student')
                    ->whereBetween('created_at', [$from, $to . ' 23:59:59'])
                    ->groupBy('week')
                    ->orderBy('week')
                    ->get(),
                'weekly_teachers' => User::select(DB::raw('YEARWEEK(created_at) as week'), DB::raw('COUNT(*) as count'))
                    ->where('role', 'teacher')
                    ->whereBetween('created_at', [$from, $to . ' 23:59:59'])
                    ->groupBy('week')
                    ->orderBy('week')
                    ->get(),
                'top_cities' => $topCities,
            ];
        });

        return response()->json($data);
    }

    public function revenue(AnalyticsRangeRequest $request): JsonResponse
    {
        $validated = $request->validated();
        $from = $validated['from'] ?? now()->subDays(30)->toDateString();
        $to = $validated['to'] ?? now()->toDateString();
        $cacheKey = "analytics.revenue.{$from}.{$to}";

        $data = $this->rememberAnalytics($cacheKey, function () use ($from, $to) {
            return [
                'daily_revenue' => Payment::select(DB::raw('DATE(paid_at) as date'), DB::raw('SUM(amount) as total'))
                    ->whereIn('status', ['held', 'released'])
                    ->whereBetween('paid_at', [$from, $to . ' 23:59:59'])
                    ->groupBy('date')
                    ->orderBy('date')
                    ->get(),
                'platform_fees' => Payment::whereIn('status', ['held', 'released'])->whereBetween('paid_at', [$from, $to . ' 23:59:59'])->sum('platform_fee'),
                'teacher_payouts' => TeacherEarning::query()
                    ->where('status', 'released')
                    ->whereBetween('created_at', [$from, $to . ' 23:59:59'])
                    ->sum('net_amount'),
                'free_sessions' => Booking::where('price', 0)->where('status', 'completed')->whereBetween('start_at', [$from, $to . ' 23:59:59'])->count(),
                'paid_sessions' => Booking::where('price', '>', 0)->where('status', 'completed')->whereBetween('start_at', [$from, $to . ' 23:59:59'])->count(),
            ];
        });

        return response()->json($data);
    }

    public function sessions(AnalyticsRangeRequest $request): JsonResponse
    {
        $validated = $request->validated();
        $from = $validated['from'] ?? now()->subDays(30)->toDateString();
        $to = $validated['to'] ?? now()->toDateString();
        $cacheKey = "analytics.sessions.{$from}.{$to}";

        $data = $this->rememberAnalytics($cacheKey, function () use ($from, $to) {
            $completed = Booking::where('status', 'completed')->whereBetween('start_at', [$from, $to . ' 23:59:59'])->count();
            $noShow = Booking::where('status', 'no_show')->whereBetween('start_at', [$from, $to . ' 23:59:59'])->count();
            $cancelled = Booking::where('status', 'cancelled')->whereBetween('start_at', [$from, $to . ' 23:59:59'])->count();
            $total = $completed + $noShow + $cancelled;

            $mostActive = User::select('users.id', 'users.name')
                ->join('bookings', 'users.id', '=', 'bookings.teacher_id')
                ->where('bookings.status', 'completed')
                ->whereBetween('bookings.start_at', [$from, $to . ' 23:59:59'])
                ->groupBy('users.id', 'users.name')
                ->orderByRaw('COUNT(*) DESC')
                ->limit(1)
                ->selectRaw('COUNT(*) as session_count')
                ->first();

            $bySubject = Booking::where('bookings.status', 'completed')
                ->whereBetween('bookings.start_at', [$from, $to . ' 23:59:59'])
                ->get()
                ->map(fn ($b) => $b->subject)
                ->filter()
                ->countBy()
                ->sortDesc()
                ->take(10);

            // Ensure by_subject is always an object, even if empty
            $bySubjectData = $bySubject->isEmpty() ? new \stdClass() : $bySubject->all();

            return [
                'by_subject' => $bySubjectData,
                'avg_duration' => (int) \App\Models\VideoSession::whereBetween('started_at', [$from, $to . ' 23:59:59'])->avg('duration_minutes'),
                'completion_rate' => $total > 0 ? round(($completed / $total) * 100, 1) : 0,
                'most_active_teacher' => $mostActive,
            ];
        });

        return response()->json($data);
    }

    private function rememberAnalytics(string $key, \Closure $callback): mixed
    {
        // Try redis first, fallback to file if not available, or use array for testing
        $driver = config('cache.default');
        
        if (app()->environment('testing')) {
            $driver = 'array';
        }

        return Cache::store($driver)->remember($key, 900, $callback);
    }
}
