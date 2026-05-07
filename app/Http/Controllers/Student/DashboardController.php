<?php

namespace App\Http\Controllers\Student;

use App\Models\Announcement;
use App\Http\Controllers\Controller;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    public function index(): Response
    {
        $user = auth()->user()->load('studentProfile');

        $announcements = Announcement::activeForRole('student')
            ->orderByDesc('starts_at')
            ->get();

        return Inertia::render('Student/Dashboard', [
            'user'    => $user,
            'profile' => $user->studentProfile,
            'announcements' => $announcements,
            'stats'   => [
                'sessions_completed' => 0,
                'saved_teachers'     => 0,
                'upcoming_sessions'  => 0,
            ],
        ]);
    }
}
