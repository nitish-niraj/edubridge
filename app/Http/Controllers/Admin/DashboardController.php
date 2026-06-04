<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Booking;
use App\Models\Payment;
use App\Models\Report;
use App\Models\TeacherProfile;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('Admin/Dashboard', $this->buildDashboardPayload());
    }

    public function summary(): JsonResponse
    {
        return response()->json($this->buildDashboardPayload());
    }

    private function buildDashboardPayload(): array
    {
        $pendingVerifications = TeacherProfile::where('is_verified', false)
            ->whereHas('user', fn ($q) => $q->where('status', 'active'))
            ->count();

        $unreadReports = Report::where('status', 'pending')->count();

        $startRange = Carbon::now('UTC')->subDays(29)->startOfDay();
        $endRange = Carbon::now('UTC')->endOfDay();

        $now = Carbon::now('UTC');
        $dayStart = $now->copy()->startOfDay();
        $dayEnd = $now->copy()->endOfDay();
        $monthStart = $now->copy()->startOfMonth();
        $monthEnd = $now->copy()->endOfMonth();
        $thirtyDaysAgo = $now->copy()->subDays(29)->startOfDay();

        $sessionsByDate = Booking::query()
            ->selectRaw('DATE(start_at) as date, COUNT(*) as total')
            ->where('status', 'completed')
            ->whereBetween('start_at', [$startRange, $endRange])
            ->groupBy('date')
            ->pluck('total', 'date');

        $newUsersByDate = User::query()
            ->selectRaw('DATE(created_at) as date, COUNT(*) as total')
            ->whereBetween('created_at', [$startRange, $endRange])
            ->groupBy('date')
            ->pluck('total', 'date');

        $sessionsChart = collect(range(29, 0))
            ->map(function (int $daysAgo): array {
                $date = Carbon::now('UTC')->subDays($daysAgo)->toDateString();

                return [
                    'date' => $date,
                    'count' => 0,
                ];
            })
            ->map(function (array $point) use ($sessionsByDate): array {
                $point['count'] = (int) ($sessionsByDate[$point['date']] ?? 0);

                return $point;
            })
            ->values()
            ->all();

        $newUsersChart = collect(range(29, 0))
            ->map(function (int $daysAgo): array {
                $date = Carbon::now('UTC')->subDays($daysAgo)->toDateString();

                return [
                    'date' => $date,
                    'count' => 0,
                ];
            })
            ->map(function (array $point) use ($newUsersByDate): array {
                $point['count'] = (int) ($newUsersByDate[$point['date']] ?? 0);

                return $point;
            })
            ->values()
            ->all();

        $dau = $this->calculateDau($dayStart, $dayEnd);
        $mau = $this->calculateMau($thirtyDaysAgo, $dayEnd);
        $topTeachers = $this->topTeachers($monthStart, $monthEnd);
        $revenueThisMonth = (float) Payment::query()
            ->whereIn('status', ['held', 'released'])
            ->whereBetween('paid_at', [$monthStart, $monthEnd])
            ->sum('amount');
        $platformFeesThisMonth = (float) Payment::query()
            ->whereIn('status', ['held', 'released'])
            ->whereBetween('paid_at', [$monthStart, $monthEnd])
            ->sum('platform_fee');
        $sessionsCompletedThisMonth = (int) Booking::query()
            ->where('status', 'completed')
            ->whereBetween('start_at', [$monthStart, $monthEnd])
            ->count();

        $pendingActions = [];

        if ($pendingVerifications > 0) {
            $pendingActions[] = [
                'id' => 'verifications',
                'label' => "Review {$pendingVerifications} pending teacher verification(s)",
                'sub' => 'Verification queue',
                'url' => route('admin.verifications'),
                'urgency' => 'high',
            ];
        }

        if ($unreadReports > 0) {
            $pendingActions[] = [
                'id' => 'reports',
                'label' => "Review {$unreadReports} unread report(s)",
                'sub' => 'User and content reports',
                'url' => route('admin.reports'),
                'urgency' => 'medium',
            ];
        }

        if (empty($pendingActions)) {
            $pendingActions[] = [
                'id' => 'analytics',
                'label' => 'Audit platform analytics for anomalies',
                'sub' => 'Routine operational check',
                'url' => route('admin.analytics'),
                'urgency' => 'low',
            ];
        }

        return [
            'stats' => [
                'total_active_users'            => User::where('status', 'active')->count(),
                'sessions_today'                => Booking::where('status', 'completed')->whereDate('start_at', $now->toDateString())->count(),
                'sessions_completed_this_month' => $sessionsCompletedThisMonth,
                'pending_verifications'         => $pendingVerifications,
                'unread_reports'                => $unreadReports,
                'revenue_this_month'            => $revenueThisMonth,
                'platform_fees_this_month'      => $platformFeesThisMonth,
                'dau'                           => $dau,
                'mau'                           => $mau,
            ],
            'recent_signups' => User::latest()
                ->take(10)
                ->get(['id', 'name', 'email', 'role', 'status', 'created_at']),
            'pending_actions' => $pendingActions,
            'sessions_chart' => $sessionsChart,
            'new_users_chart' => $newUsersChart,
            'top_teachers' => $topTeachers,
        ];
    }

    private function calculateDau(Carbon $from, Carbon $to): int
    {
        $bookingDau = Booking::query()
            ->whereIn('status', ['completed', 'confirmed', 'pending'])
            ->whereBetween('start_at', [$from, $to])
            ->distinct()
            ->count('student_id');

        $userDau = User::query()
            ->whereBetween('last_login_at', [$from, $to])
            ->count();

        return max((int) $bookingDau, (int) $userDau);
    }

    private function calculateMau(Carbon $from, Carbon $to): int
    {
        if (Schema::hasColumn('users', 'last_login_at')) {
            $userMau = User::query()
                ->whereBetween('last_login_at', [$from, $to])
                ->count();

            if ($userMau > 0) {
                return (int) $userMau;
            }
        }

        return (int) Booking::query()
            ->whereIn('status', ['completed', 'confirmed', 'pending'])
            ->whereBetween('start_at', [$from, $to])
            ->distinct()
            ->count(DB::raw('COALESCE(NULLIF(student_id, 0), teacher_id)'));
    }

    private function topTeachers(Carbon $from, Carbon $to): array
    {
        return Booking::query()
            ->selectRaw('users.id, users.name, users.avatar, COUNT(*) as sessions_completed, COALESCE(SUM(teacher_earnings.net_amount), 0) as earnings')
            ->join('users', 'users.id', '=', 'bookings.teacher_id')
            ->leftJoin('teacher_earnings', 'teacher_earnings.booking_id', '=', 'bookings.id')
            ->where('bookings.status', 'completed')
            ->where('bookings.start_at', '>=', $from)
            ->where('bookings.start_at', '<=', $to)
            ->groupBy('users.id', 'users.name', 'users.avatar')
            ->orderByDesc('sessions_completed')
            ->orderByDesc('earnings')
            ->limit(5)
            ->get()
            ->map(fn ($row) => [
                'id' => (int) $row->id,
                'name' => $row->name,
                'avatar' => $row->avatar,
                'sessions_completed' => (int) $row->sessions_completed,
                'earnings' => (float) $row->earnings,
            ])
            ->all();
    }
}
