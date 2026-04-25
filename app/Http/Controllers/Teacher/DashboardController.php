<?php

namespace App\Http\Controllers\Teacher;

use App\Http\Controllers\Controller;
use App\Models\Booking;
use App\Models\Message;
use Carbon\Carbon;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    public function index(): Response
    {
        $user    = auth()->user()->load('teacherProfile');
        $profile = $user->teacherProfile;
        $teacherId = (int) $user->id;

        $todayStart = Carbon::now('UTC')->startOfDay();
        $todayEnd = Carbon::now('UTC')->endOfDay();

        $todaySessions = Booking::query()
            ->with('student:id,name')
            ->where('teacher_id', $teacherId)
            ->whereIn('status', ['pending', 'confirmed'])
            ->whereBetween('start_at', [$todayStart, $todayEnd])
            ->orderBy('start_at')
            ->limit(6)
            ->get(['id', 'student_id', 'subject', 'start_at', 'end_at', 'status']);

        $upcomingSessions = Booking::query()
            ->where('teacher_id', $teacherId)
            ->whereIn('status', ['pending', 'confirmed'])
            ->where('start_at', '>=', Carbon::now('UTC'))
            ->count();

        $sessionsThisMonth = Booking::query()
            ->where('teacher_id', $teacherId)
            ->where('status', 'completed')
            ->whereBetween('start_at', [Carbon::now('UTC')->startOfMonth(), Carbon::now('UTC')->endOfMonth()])
            ->count();

        $totalStudents = Booking::query()
            ->where('teacher_id', $teacherId)
            ->whereIn('status', ['confirmed', 'completed'])
            ->distinct('student_id')
            ->count('student_id');

        $earningsThisMonth = (float) Booking::query()
            ->where('teacher_id', $teacherId)
            ->where('status', 'completed')
            ->whereBetween('start_at', [Carbon::now('UTC')->startOfMonth(), Carbon::now('UTC')->endOfMonth()])
            ->sum('teacher_payout');

        $unreadMessages = Message::query()
            ->whereNull('read_at')
            ->where('sender_id', '!=', $teacherId)
            ->whereHas('conversation.participants', function ($query) use ($teacherId): void {
                $query->where('users.id', $teacherId);
            })
            ->count();

        return Inertia::render('Teacher/Dashboard', [
            'user'        => $user,
            'profile'     => $profile,
            'is_verified' => $profile?->is_verified ?? false,
            'stats'       => [
                'sessions_this_month' => $sessionsThisMonth,
                'total_students'      => $totalStudents,
                'earnings_this_month' => $earningsThisMonth,
                'upcoming_sessions'   => $upcomingSessions,
                'unread_messages'     => $unreadMessages,
            ],
            'today_sessions' => $todaySessions,
        ]);
    }
}
