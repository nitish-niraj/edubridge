<?php

use App\Http\Controllers\Api\BookingController;
use App\Http\Controllers\Api\FeedbackController;
use App\Http\Controllers\Api\ChatController;
use App\Http\Controllers\Api\GroupController;
use App\Http\Controllers\Api\HealthCheckController;
use App\Http\Controllers\Api\PaymentController;
use App\Http\Controllers\Api\ReviewController;
use App\Http\Controllers\Api\SavedTeacherController;
use App\Http\Controllers\Api\TeacherController;
use App\Http\Controllers\Api\TeacherSettingsController;
use App\Http\Controllers\Api\VideoSessionController;
use App\Http\Controllers\Admin\VerificationController;
use App\Http\Controllers\Api\TeacherAvailabilityController;
use App\Http\Controllers\Teacher\AvailabilityController;
use App\Http\Resources\UserResource;
use Illuminate\Http\Request;
use Illuminate\Routing\Middleware\ThrottleRequests;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
*/

Route::middleware('auth:sanctum')->get('/user', function (Request $request) {
    return new UserResource($request->user());
});

Route::get('/health', HealthCheckController::class)
    ->withoutMiddleware([ThrottleRequests::class]);

Route::get('/teachers', [TeacherController::class, 'index']);
Route::get('/teachers/search', [TeacherController::class, 'search']);
Route::get('/teachers/{teacher}', [TeacherController::class, 'show']);

// Public: teacher availability for booking
Route::get('/teachers/{id}/availability', [BookingController::class, 'teacherAvailability']);

// Public: group invite preview
Route::get('/groups/preview/{inviteCode}', [GroupController::class, 'preview']);

Route::middleware('auth:sanctum')->group(function (): void {
    Route::get('/students/saved-teachers', [SavedTeacherController::class, 'index']);
    Route::post('/students/saved-teachers/{teacher_id}', [SavedTeacherController::class, 'store']);
    Route::delete('/students/saved-teachers/{teacher_id}', [SavedTeacherController::class, 'destroy']);

    Route::get('/conversations', [ChatController::class, 'index']);
    Route::post('/conversations', [ChatController::class, 'store']);
    Route::get('/conversations/{conversation}/messages', [ChatController::class, 'messages']);
    Route::post('/conversations/{conversation}/messages', [ChatController::class, 'send'])->middleware('throttle:messages');
    Route::patch('/conversations/{conversation}/read', [ChatController::class, 'markRead']);
    Route::post('/conversations/{conversation}/typing', [ChatController::class, 'typing'])->middleware('throttle:60,1');

    // ─── Group Chat Extensions ─────────────────────────────────────────
    Route::post('/conversations/{conversation}/announcements', [ChatController::class, 'announcement']);
    Route::get('/conversations/{conversation}/pinned-announcement', [ChatController::class, 'pinnedAnnouncement']);
    Route::delete('/conversations/{conversation}/messages/{messageId}', [ChatController::class, 'deleteMessage']);

    // ─── Class Groups ─────────────────────────────────────────────────
    Route::get('/groups', [GroupController::class, 'index']);
    Route::post('/groups', [GroupController::class, 'store']);
    Route::post('/groups/join/{inviteCode}', [GroupController::class, 'join']);
    Route::post('/groups/{id}/join', [GroupController::class, 'joinById']);
    Route::post('/groups/{groupId}/add-member', [GroupController::class, 'addMember']);
    Route::post('/groups/{groupId}/start-session', [VideoSessionController::class, 'startGroupSessionFromGroup']);
    Route::get('/groups/{id}', [GroupController::class, 'show']);
    Route::post('/groups/{groupId}/members', [GroupController::class, 'addMember']);
    Route::delete('/groups/{groupId}/members/{userId}', [GroupController::class, 'removeMember']);
    Route::patch('/groups/{groupId}/members/{userId}/mute', [GroupController::class, 'toggleMute']);
    Route::patch('/groups/{groupId}/members/{userId}/draw', [GroupController::class, 'toggleDraw']);
    Route::patch('/groups/{groupId}/members/{userId}/draw-permission', [GroupController::class, 'toggleDraw']);

    // ─── Teacher Availability (API) ──────────────────────────────────────
    Route::get('/teacher/availability', [TeacherAvailabilityController::class, 'index']);
    Route::post('/teacher/availability', [TeacherAvailabilityController::class, 'store']);
    Route::patch('/teacher/availability/{availability}', [TeacherAvailabilityController::class, 'update']);
    Route::delete('/teacher/availability/{availability}', [TeacherAvailabilityController::class, 'destroy']);
    Route::get('/teacher/slots', [TeacherAvailabilityController::class, 'slots']);

    // ─── Bookings ────────────────────────────────────────────────────────
    Route::get('/bookings', [BookingController::class, 'index']);
    Route::post('/bookings', [BookingController::class, 'store']);
    Route::get('/bookings/{id}', [BookingController::class, 'show']);
    Route::patch('/bookings/{id}/cancel', [BookingController::class, 'cancel']);

    // ─── Payments ────────────────────────────────────────────────────────
    Route::post('/payments/initiate', [PaymentController::class, 'initiate']);
    Route::post('/payments/verify', [PaymentController::class, 'verify']);

    // ─── Video Sessions ──────────────────────────────────────────────────
    Route::post('/video-sessions/{bookingId}/token', [VideoSessionController::class, 'token']);
    Route::patch('/video-sessions/{bookingId}/start', [VideoSessionController::class, 'start']);
    Route::patch('/video-sessions/{bookingId}/end', [VideoSessionController::class, 'end']);

    // ─── Group Video Sessions ──────────────────────────────────────────
    Route::post('/video-sessions/group/{conversationId}/start', [VideoSessionController::class, 'startGroupSession']);
    Route::post('/video-sessions/group/{conversationId}/join', [VideoSessionController::class, 'joinGroupSession']);
    Route::post('/video-sessions/group/{groupId}/token', [VideoSessionController::class, 'groupToken']);
    Route::patch('/video-sessions/group/{sessionId}/end', [VideoSessionController::class, 'endGroupSession']);

    // ─── Whiteboard & Recording ──────────────────────────────────────────
    Route::post('/video-sessions/{sessionId}/whiteboard', [VideoSessionController::class, 'whiteboardSync']);
    Route::post('/video-sessions/{sessionId}/recording/consent', [VideoSessionController::class, 'requestRecordingConsent']);
    Route::get('/recordings/{sessionId}', [VideoSessionController::class, 'recording']);

    // ─── Reviews ─────────────────────────────────────────────────────────
    Route::post('/reviews', [ReviewController::class, 'store']);

    // ─── Reports (user-facing) ───────────────────────────────────────────
    Route::post('/reports', [\App\Http\Controllers\Api\ReportController::class, 'store']);

    // ─── Active Announcements (user-facing) ──────────────────────────────
    Route::get('/announcements/active', [\App\Http\Controllers\Admin\AdminAnnouncementController::class, 'active']);

    // ─── Teacher settings/preferences ───────────────────────────────────
    Route::middleware('role:teacher')->group(function (): void {
        Route::patch('/teacher/preferences', [TeacherSettingsController::class, 'updatePreferences']);
        Route::patch('/teacher/profile', [TeacherSettingsController::class, 'updateProfile']);
    });
});

Route::post('/feedback', [FeedbackController::class, 'store'])->middleware('throttle:10,1');

Route::post('/webhooks/payment', [PaymentController::class, 'webhook']);

// ─── Admin API Routes ────────────────────────────────────────────────────────
Route::middleware(['auth:sanctum', 'role:admin', 'admin.2fa'])->prefix('admin')->group(function () {
    // Dashboard
    Route::get('/dashboard/summary', [\App\Http\Controllers\Admin\DashboardController::class, 'summary']);

    // Users
    Route::get('/users', [\App\Http\Controllers\Admin\AdminUserController::class, 'index']);
    Route::get('/users/{id}', [\App\Http\Controllers\Admin\AdminUserController::class, 'show']);
    Route::post('/users/{id}/suspend', [\App\Http\Controllers\Admin\AdminUserController::class, 'suspend']);
    Route::post('/users/{id}/activate', [\App\Http\Controllers\Admin\AdminUserController::class, 'activate']);
    Route::delete('/users/{id}', [\App\Http\Controllers\Admin\AdminUserController::class, 'destroy']);
    Route::post('/users/bulk-suspend', [\App\Http\Controllers\Admin\AdminUserController::class, 'bulkSuspend']);
    Route::post('/users/export', [\App\Http\Controllers\Admin\AdminUserController::class, 'export']);

    // Reports
    Route::get('/reports', [\App\Http\Controllers\Admin\AdminReportController::class, 'index']);
    Route::get('/reports/{id}', [\App\Http\Controllers\Admin\AdminReportController::class, 'show']);
    Route::post('/reports/{id}/warn', [\App\Http\Controllers\Admin\AdminReportController::class, 'warn']);
    Route::post('/reports/{id}/remove-content', [\App\Http\Controllers\Admin\AdminReportController::class, 'removeContent']);
    Route::post('/reports/{id}/suspend-user', [\App\Http\Controllers\Admin\AdminReportController::class, 'suspendUser']);
    Route::post('/reports/{id}/dismiss', [\App\Http\Controllers\Admin\AdminReportController::class, 'dismiss']);

    // Reviews
    Route::get('/reviews', [\App\Http\Controllers\Admin\AdminReviewController::class, 'index']);
    Route::patch('/reviews/{id}/visibility', [\App\Http\Controllers\Admin\AdminReviewController::class, 'toggleVisibility']);

    // Analytics
    Route::get('/analytics/overview', [\App\Http\Controllers\Admin\AnalyticsController::class, 'overview']);
    Route::get('/analytics/users', [\App\Http\Controllers\Admin\AnalyticsController::class, 'users']);
    Route::get('/analytics/revenue', [\App\Http\Controllers\Admin\AnalyticsController::class, 'revenue']);
    Route::get('/analytics/sessions', [\App\Http\Controllers\Admin\AnalyticsController::class, 'sessions']);

    // Announcements
    Route::get('/announcements', [\App\Http\Controllers\Admin\AdminAnnouncementController::class, 'index']);
    Route::post('/announcements', [\App\Http\Controllers\Admin\AdminAnnouncementController::class, 'store']);
    Route::delete('/announcements/{id}', [\App\Http\Controllers\Admin\AdminAnnouncementController::class, 'destroy']);

    // Disputes
    Route::get('/disputes', [\App\Http\Controllers\Admin\AdminDisputeController::class, 'index']);
    Route::get('/disputes/{id}', [\App\Http\Controllers\Admin\AdminDisputeController::class, 'show']);
    Route::post('/disputes/{id}/full-refund', [\App\Http\Controllers\Admin\AdminDisputeController::class, 'fullRefund']);
    Route::post('/disputes/{id}/partial-refund', [\App\Http\Controllers\Admin\AdminDisputeController::class, 'partialRefund']);
    Route::post('/disputes/{id}/release', [\App\Http\Controllers\Admin\AdminDisputeController::class, 'releaseToTeacher']);
    Route::post('/disputes/{id}/close', [\App\Http\Controllers\Admin\AdminDisputeController::class, 'close']);

    // Verification documents
    Route::get('/documents/{id}/view', [VerificationController::class, 'viewDocument']);
});
