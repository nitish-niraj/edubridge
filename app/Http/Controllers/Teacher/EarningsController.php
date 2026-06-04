<?php

namespace App\Http\Controllers\Teacher;

use App\Http\Controllers\Controller;
use App\Models\Booking;
use App\Models\TeacherEarning;
use Carbon\Carbon;
use Inertia\Inertia;
use Inertia\Response;

class EarningsController extends Controller
{
    public function index(): Response
    {
        $user = auth()->user();
        $teacherId = (int) $user->id;

        $now = Carbon::now('UTC');
        $monthStart = $now->copy()->startOfMonth();
        $monthEnd = $now->copy()->endOfMonth();
        $weekStart = $now->copy()->startOfWeek();
        $weekEnd = $now->copy()->endOfWeek();

        $earnings = TeacherEarning::query()
            ->where('teacher_id', $teacherId)
            ->with([
                'booking:id,student_id,start_at,subject,session_type,price,platform_fee,teacher_payout',
                'booking.student:id,name,avatar',
            ])
            ->orderByDesc('payout_date')
            ->orderByDesc('id')
            ->paginate(20)
            ->withQueryString();

        $summary = [
            'this_month' => (float) TeacherEarning::query()
                ->where('teacher_id', $teacherId)
                ->where('status', 'released')
                ->whereBetween('payout_date', [$monthStart->toDateString(), $monthEnd->toDateString()])
                ->sum('net_amount'),
            'this_week' => (float) TeacherEarning::query()
                ->where('teacher_id', $teacherId)
                ->where('status', 'released')
                ->whereBetween('payout_date', [$weekStart->toDateString(), $weekEnd->toDateString()])
                ->sum('net_amount'),
            'total' => (float) TeacherEarning::query()
                ->where('teacher_id', $teacherId)
                ->where('status', 'released')
                ->sum('net_amount'),
            'pending' => (float) TeacherEarning::query()
                ->where('teacher_id', $teacherId)
                ->where('status', 'pending')
                ->sum('net_amount'),
            'released_count' => (int) TeacherEarning::query()
                ->where('teacher_id', $teacherId)
                ->where('status', 'released')
                ->count(),
            'pending_count' => (int) TeacherEarning::query()
                ->where('teacher_id', $teacherId)
                ->where('status', 'pending')
                ->count(),
        ];

        $freeSessions = (int) Booking::query()
            ->where('teacher_id', $teacherId)
            ->where('price', 0)
            ->whereIn('status', ['confirmed', 'completed'])
            ->count();

        return Inertia::render('Teacher/Earnings', [
            'earnings' => $earnings,
            'summary' => $summary,
            'free_sessions' => $freeSessions,
            'commission_rate' => (float) config('edubridge.commission_rate', 0.12),
            'payout_delay_hours' => (int) config('edubridge.payout_delay_hours', 24),
        ]);
    }
}
