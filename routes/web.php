<?php

use App\Http\Controllers\Admin\DashboardController as AdminDashboardController;
use App\Http\Controllers\Admin\AdminTwoFactorController;
use App\Http\Controllers\Admin\VerificationController;
use App\Http\Controllers\Api\PaymentController;
use App\Http\Controllers\Api\VideoSessionController;
use App\Http\Controllers\Auth\SocialiteController;
use App\Http\Controllers\Auth\StudentAuthController;
use App\Http\Controllers\Auth\TeacherAuthController;
use App\Http\Controllers\Auth\VerifyOtpController;
use App\Http\Controllers\LandingController;
use App\Http\Controllers\PageController;
use App\Http\Controllers\ProfileController as AccountProfileController;
use App\Http\Controllers\SitemapController;
use App\Http\Controllers\Student\DashboardController as StudentDashboardController;
use App\Http\Controllers\Student\OnboardingController;
use App\Http\Controllers\Student\ProfileController as StudentProfileController;
use App\Http\Controllers\Teacher\AvailabilityController;
use App\Http\Controllers\Teacher\DashboardController as TeacherDashboardController;
use App\Http\Controllers\Teacher\ProfileController as TeacherProfileController;
use App\Http\Middleware\VerifyCsrfToken;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

/*
|--------------------------------------------------------------------------
| Web Routes — EduBridge
|--------------------------------------------------------------------------
*/

Route::get('/', [LandingController::class, 'index'])->name('landing');

Route::get('/about-us', [PageController::class, 'about'])->name('about');
Route::get('/privacy-policy', [PageController::class, 'privacy'])->name('privacy-policy');
Route::get('/terms-and-conditions', [PageController::class, 'terms'])->name('terms');
Route::get('/contact', [PageController::class, 'contact'])->name('contact');
Route::post('/contact', [PageController::class, 'submitContact'])
    ->middleware('throttle:10,1')
    ->name('contact.submit');

Route::get('/sitemap.xml', [SitemapController::class, 'index'])->name('sitemap');

Route::middleware('auth')->get('/account/profile', [AccountProfileController::class, 'edit'])
    ->name('account.profile');
Route::middleware('auth')->patch('/account/profile', [AccountProfileController::class, 'update'])
    ->name('account.profile.update');
Route::middleware('auth')->delete('/account/profile', [AccountProfileController::class, 'destroy'])
    ->name('account.profile.destroy');

// ─── Public Teacher Discovery ────────────────────────────────────────────────
Route::get('/teachers', function () {
    return Inertia::render('Student/TeacherSearch');
})->name('teachers.index');

Route::get('/teachers/{teacher}', function (int $teacher) {
    return Inertia::render('Student/TeacherPublicProfile', [
        'teacherId' => $teacher,
    ]);
})->name('teachers.show');

Route::middleware(['auth', 'role:student'])->get('/students/saved-teachers', function () {
    return Inertia::render('Student/SavedTeachers');
})->name('students.saved-teachers');

Route::middleware('auth')->get('/chat/{conversation?}', function (?int $conversation = null) {
    $user = auth()->user();

    if ($user?->isTeacher()) {
        return Inertia::render('Teacher/Chat', [
            'initialConversationId' => $conversation,
        ]);
    }

    return Inertia::render('Student/Chat', [
        'initialConversationId' => $conversation,
    ]);
})->name('chat.show');

// ─── Student Registration ────────────────────────────────────────────────────
Route::get('/register/student',  [StudentAuthController::class, 'showRegisterForm'])->name('student.register')->middleware('guest');
Route::post('/register/student', [StudentAuthController::class, 'register'])->name('student.register.submit')->middleware('guest');

// ─── Teacher Registration ────────────────────────────────────────────────────
Route::get('/register/teacher',  [TeacherAuthController::class, 'showRegisterForm'])->name('teacher.register')->middleware('guest');
Route::post('/register/teacher', [TeacherAuthController::class, 'register'])->name('teacher.register.submit')->middleware('guest');

// ─── OTP Verification ────────────────────────────────────────────────────────
Route::get('/verify-otp',    [VerifyOtpController::class, 'showForm'])->name('verify.otp.form');
Route::post('/verify-otp',   [VerifyOtpController::class, 'verify'])
    ->middleware('throttle:otp-verify')
    ->name('verify.otp.submit');
Route::post('/resend-otp',   [VerifyOtpController::class, 'resend'])
    ->middleware('throttle:otp-resend')
    ->name('verify.otp.resend');

if (app()->environment('testing')) {
    Route::get('/test-verify-otp', function () {
        $email = request()->query('email');
        abort_unless(is_string($email) && $email !== '', 404);

        $user = \App\Models\User::query()->where('email', $email)->firstOrFail();
        $user->forceFill([
            'email_verified_at' => now(),
            'status' => 'active',
        ])->save();

        auth()->login($user);

        if ($user->isStudent()) {
            return redirect()->route('student.onboarding');
        }

        if ($user->isTeacher()) {
            return redirect()->route('teacher.profile.step', ['step' => 1]);
        }

        return redirect()->route('admin.dashboard');
    })->name('test.verify.otp');
}

// ─── Google OAuth (Students only) ────────────────────────────────────────────
Route::get('/auth/google',          [SocialiteController::class, 'redirectToGoogle'])->name('auth.google')->middleware('guest');
Route::get('/auth/google/callback', [SocialiteController::class, 'handleGoogleCallback'])->name('auth.google.callback');

// ─── Design Review Routes ────────────────────────────────────────────────────
Route::get('/design/student-dashboard', function () {
    return Inertia::render('Student/Dashboard');
});

Route::get('/design/teacher-dashboard', function () {
    return Inertia::render('Teacher/Dashboard');
});

Route::get('/design/teacher-availability', function () {
    return Inertia::render('Teacher/Availability');
});

Route::get('/design/admin-verifications', function () {
    return Inertia::render('Admin/Verifications');
});

// ─── Student Portal ──────────────────────────────────────────────────────────
Route::middleware(['auth', 'role:student'])->prefix('student')->name('student.')->group(function () {
    Route::get('/dashboard',   [StudentDashboardController::class, 'index'])->name('dashboard');
    Route::get('/onboarding',  [OnboardingController::class, 'show'])->name('onboarding');
    Route::post('/onboarding', [OnboardingController::class, 'complete'])->name('onboarding.complete');
    Route::get('/profile',     [StudentProfileController::class, 'show'])->name('profile');
    Route::patch('/profile',   [StudentProfileController::class, 'update'])->name('profile.update');
    Route::get('/settings', fn () => Inertia::render('Student/Settings'))->name('settings');
    Route::get('/saved-teachers', fn () => Inertia::render('Student/SavedTeachers'))->name('saved-teachers');
    Route::get('/chat', fn () => Inertia::render('Student/Chat'))->name('chat');
    Route::get('/bookings', fn () => Inertia::render('Student/MyBookings'))->name('bookings');
});

// ─── Payment Callback (PhonePe redirect-back) ────────────────────────────────
Route::middleware('auth')->get('/payment/callback', [PaymentController::class, 'callback'])->name('payment.callback');

// ─── PhonePe Webhook (server-to-server, no CSRF) ─────────────────────────────
Route::post('/api/webhooks/phonepe', [PaymentController::class, 'webhook'])
    ->withoutMiddleware([VerifyCsrfToken::class])
    ->name('webhooks.phonepe');

// ─── Review Page (Student) ───────────────────────────────────────────────────
Route::middleware(['auth', 'role:student'])->get('/reviews/{bookingId}', function (int $bookingId) {
    return Inertia::render('Student/ReviewPage', ['bookingId' => $bookingId]);
})->name('reviews.create');

// ─── Video Session Page (Shared) ─────────────────────────────────────────────
Route::middleware('auth')->get('/session/{bookingId}', function (int $bookingId) {
    return Inertia::render('VideoSession', ['bookingId' => $bookingId]);
})->name('session.show');

// ─── Group Session Page (Shared) ────────────────────────────────────────────
Route::middleware('auth')->get('/group-session/{conversationId}', function (int $conversationId) {
    return Inertia::render('GroupVideoSession', ['conversationId' => $conversationId]);
})->name('group-session.show');

// ─── Public Invite Link ───────────────────────────────────────────────────
Route::get('/join/{inviteCode}', function (string $inviteCode) {
    return Inertia::render('JoinClass', ['inviteCode' => $inviteCode]);
})->name('class.join');

// ─── Twilio Recording Webhook (no CSRF) ───────────────────────────────────
Route::post('/api/webhooks/twilio/recording-complete', [VideoSessionController::class, 'recordingWebhook'])
    ->withoutMiddleware([VerifyCsrfToken::class])
    ->name('webhooks.twilio.recording');

// ─── Teacher Portal ──────────────────────────────────────────────────────────
Route::middleware(['auth', 'role:teacher'])->prefix('teacher')->name('teacher.')->group(function () {
    Route::get('/', fn () => redirect()->route('teacher.dashboard'))->name('home');
    Route::get('/dashboard', [TeacherDashboardController::class, 'index'])->name('dashboard');

    Route::get('/profile', [TeacherProfileController::class, 'showProfile'])->name('profile.show');

    // Profile setup steps (5 separate pages — teachers can bookmark and return)
    Route::get('/profile/step/{step}',  [TeacherProfileController::class, 'showStep'])->name('profile.step')->where('step', '[1-5]');
    Route::post('/profile/step/1',      [TeacherProfileController::class, 'saveStep1'])->name('profile.step1.save');
    Route::post('/profile/step/2',      [TeacherProfileController::class, 'saveStep2'])->name('profile.step2.save');
    Route::post('/profile/step/3',      [TeacherProfileController::class, 'saveStep3'])->name('profile.step3.save');
    Route::post('/profile/step/4',      [TeacherProfileController::class, 'saveStep4'])->name('profile.step4.save');
    Route::post('/profile/step/5',      [TeacherProfileController::class, 'saveStep5'])->name('profile.step5.save');
    Route::get('/chat', fn () => Inertia::render('Teacher/Chat'))->name('chat');

    Route::get('/settings', function () {
        $preference = auth()->user()?->notificationPreferences;

        return Inertia::render('Teacher/Settings', [
            'preferences' => [
                'high_contrast' => (bool) ($preference?->high_contrast ?? false),
            ],
        ]);
    })->name('settings');

    // Availability settings
    Route::get('/availability', [AvailabilityController::class, 'index'])->name('availability');
    Route::post('/availability', [AvailabilityController::class, 'store'])->name('availability.store');

    // Sessions management
    Route::get('/sessions', fn () => Inertia::render('Teacher/MySessions'))->name('sessions');

    // Class Groups
    Route::get('/classes/create', fn () => Inertia::render('Teacher/CreateClass'))->name('classes.create');
    Route::get('/classes/{id}', function (int $id) {
        return Inertia::render('Teacher/ClassManage', ['groupId' => $id]);
    })->name('classes.manage');
});

// ─── Admin Portal ────────────────────────────────────────────────────────────
Route::middleware(['auth', 'role:admin'])->prefix('admin')->name('admin.')->group(function () {
    Route::get('/2fa', [AdminTwoFactorController::class, 'challenge'])->name('2fa.challenge');
    Route::post('/2fa', [AdminTwoFactorController::class, 'verify'])->name('2fa.verify');
});

Route::middleware(['auth', 'role:admin', 'admin.2fa'])->prefix('admin')->name('admin.')->group(function () {
    Route::get('/dashboard',                    [AdminDashboardController::class, 'index'])->name('dashboard');
    Route::get('/verifications',                [VerificationController::class, 'index'])->name('verifications');
    Route::get('/documents/{document}',         [VerificationController::class, 'showDocument'])->middleware('signed')->name('documents.show');
    Route::post('/verifications/{id}/approve',  [VerificationController::class, 'approve'])->name('verifications.approve');
    Route::post('/verifications/{id}/reject',   [VerificationController::class, 'reject'])->name('verifications.reject');

    // User Management
    Route::get('/users', fn () => Inertia::render('Admin/Users'))->name('users');
    Route::get('/bookings', fn () => Inertia::render('Admin/Disputes'))->name('bookings');
    Route::get('/settings/platform', fn () => Inertia::render('Admin/SettingsPlatform'))->name('settings.platform');
    Route::get('/settings/account', [AdminTwoFactorController::class, 'settings'])->name('settings.account');
    Route::post('/settings/account/2fa/enable', [AdminTwoFactorController::class, 'enable'])->name('settings.account.2fa.enable');
    Route::delete('/settings/account/2fa', [AdminTwoFactorController::class, 'disable'])->name('settings.account.2fa.disable');
    Route::get('/exports/download', [\App\Http\Controllers\Admin\AdminUserController::class, 'downloadExport'])
        ->middleware('signed')
        ->name('exports.download');

    // Reports
    Route::get('/reports', fn () => Inertia::render('Admin/Reports'))->name('reports');

    // Reviews
    Route::get('/reviews', fn () => Inertia::render('Admin/Reviews'))->name('reviews');

    // Analytics
    Route::get('/analytics', fn () => Inertia::render('Admin/Analytics'))->name('analytics');

    // Announcements
    Route::get('/announcements', fn () => Inertia::render('Admin/Announcements'))->name('announcements');

    // Disputes
    Route::get('/disputes', fn () => Inertia::render('Admin/Disputes'))->name('disputes');
});

// ─── Breeze Auth Routes (login, logout, password reset) ──────────────────────
require __DIR__ . '/auth.php';
