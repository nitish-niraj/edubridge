<?php

namespace App\Http\Controllers\Student;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class OnboardingController extends Controller
{
    public function show(): Response|RedirectResponse
    {
        $user = auth()->user()->load('studentProfile');

        if ($user->studentProfile?->onboarding_completed) {
            return redirect()->route('student.dashboard');
        }

        return Inertia::render('Student/Onboarding');
    }

    public function complete(): RedirectResponse
    {
        auth()->user()->studentProfile->update([
            'onboarding_completed' => true,
        ]);

        return redirect()->route('student.dashboard');
    }
}
