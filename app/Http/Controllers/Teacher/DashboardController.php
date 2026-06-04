<?php

namespace App\Http\Controllers\Teacher;

use App\Http\Controllers\Controller;
use App\Models\Announcement;
use App\Models\Booking;
use App\Models\Message;
use App\Models\TeacherEarning;
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
            ->whereIn('status', ['confirmed', 'completed'])
            ->whereBetween('start_at', [Carbon::now('UTC')->startOfMonth(), Carbon::now('UTC')->endOfMonth()])
            ->count();

        $totalStudents = Booking::query()
            ->where('teacher_id', $teacherId)
            ->whereIn('status', ['confirmed', 'completed'])
            ->distinct('student_id')
            ->count('student_id');

        $earningsThisMonth = (float) TeacherEarning::query()
            ->where('teacher_id', $teacherId)
            ->whereIn('status', ['released', 'pending'])
            ->whereBetween('created_at', [Carbon::now('UTC')->startOfMonth(), Carbon::now('UTC')->endOfMonth()])
            ->sum('net_amount');

        $freeSessionsCompleted = (int) Booking::query()
            ->where('teacher_id', $teacherId)
            ->where('price', 0)
            ->whereIn('status', ['completed', 'confirmed'])
            ->count();

        $unreadMessages = Message::query()
            ->whereNull('read_at')
            ->where('sender_id', '!=', $teacherId)
            ->whereHas('conversation', function ($query) use ($teacherId): void {
                $query->where(function ($visible) use ($teacherId): void {
                    $visible->where(function ($direct) use ($teacherId): void {
                        $direct->where('is_group', false)
                            ->whereHas('participants', function ($participant) use ($teacherId): void {
                                $participant->where('users.id', $teacherId)
                                    ->whereNull('conversation_participants.left_at');
                            });
                    })->orWhere(function ($group) use ($teacherId): void {
                        $group->where('is_group', true)
                            ->whereHas('activeClassMembers', function ($member) use ($teacherId): void {
                                $member->where('user_id', $teacherId);
                            });
                    });
                });
            })
            ->count();

        $announcements = Announcement::activeForRole('teacher')
            ->orderByDesc('starts_at')
            ->get();

        return Inertia::render('Teacher/Dashboard', [
            'user'        => $user,
            'profile'     => $profile,
            'is_verified' => $profile?->is_verified ?? false,
            'completeness_score' => $profile?->getCompletenessScore() ?? 0,
            'announcements' => $announcements,
            'stats'       => [
                'sessions_this_month' => $sessionsThisMonth,
                'total_students'      => $totalStudents,
                'earnings_this_month' => $earningsThisMonth,
                'upcoming_sessions'   => $upcomingSessions,
                'unread_messages'     => $unreadMessages,
                'free_sessions'       => $freeSessionsCompleted,
            ],
            'today_sessions' => $todaySessions,
        ]);
    }
}
