<?php

namespace App\Http\Controllers\Student;

use App\Http\Controllers\Controller;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    public function index(): Response
    {
        $user = auth()->user()->load('studentProfile');

        return Inertia::render('Student/Dashboard', [
            'user'    => $user,
            'profile' => $user->studentProfile,
            'stats'   => [
                'sessions_completed' => 0,
                'saved_teachers'     => 0,
                'upcoming_sessions'  => 0,
            ],
        ]);
    }
}
