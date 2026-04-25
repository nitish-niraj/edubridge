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
                'total_active_users'    => User::where('status', 'active')->count(),
                'sessions_today'        => Booking::where('status', 'completed')->whereDate('start_at', now('UTC')->toDateString())->count(),
                'pending_verifications' => $pendingVerifications,
                'unread_reports'        => $unreadReports,
                'revenue_this_month'    => (float) Payment::query()
                    ->where('status', 'released')
                    ->whereBetween('paid_at', [now('UTC')->startOfMonth(), now('UTC')->endOfMonth()])
                    ->sum('amount'),
            ],
            'recent_signups' => User::latest()
                ->take(10)
                ->get(['id', 'name', 'email', 'role', 'status', 'created_at']),
            'pending_actions' => $pendingActions,
            'sessions_chart' => $sessionsChart,
            'new_users_chart' => $newUsersChart,
        ];
    }
}
