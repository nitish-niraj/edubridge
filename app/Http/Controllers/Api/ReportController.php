<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\ReportStoreRequest;
use App\Http\Resources\ReportResource;
use App\Models\Booking;
use App\Models\Message;
use App\Models\Report;
use App\Models\Review;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\RateLimiter;
use Symfony\Component\HttpKernel\Exception\HttpException;

class ReportController extends Controller
{
    public function store(ReportStoreRequest $request): JsonResponse
    {
        $validated = $request->validated();
        $userId = $request->user()->id;
        $key = 'reports:' . $userId;

        if (RateLimiter::tooManyAttempts($key, 5)) {
            $retryAfter = RateLimiter::availableIn($key);

            return response()->json([
                'message' => "Too many reports. Try again in {$retryAfter} seconds.",
            ], 429);
        }

        RateLimiter::hit($key, 3600);

        $booking = null;
        if (! empty($validated['booking_id'])) {
            $booking = Booking::with(['student', 'teacher'])->findOrFail($validated['booking_id']);
            $this->assertCanAccessBooking($request->user()->id, $booking);
        }

        if (! empty($validated['reported_message_id'])) {
            $message = Message::with('conversation.participants')->findOrFail($validated['reported_message_id']);
            $this->assertCanAccessConversation($request->user()->id, $message->conversation);
        }

        if (! empty($validated['reported_review_id'])) {
            $review = Review::with('booking.student', 'booking.teacher')->findOrFail($validated['reported_review_id']);
            $this->assertCanAccessBooking($request->user()->id, $review->booking);

            if ($booking && $review->booking_id !== $booking->id) {
                throw new HttpException(422, 'Selected booking does not match the reported review.');
            }
        }

        if (! empty($validated['reported_user_id']) && $booking) {
            $reportedUserId = (int) $validated['reported_user_id'];
            $bookingParticipantIds = [(int) $booking->student_id, (int) $booking->teacher_id];

            if (! in_array($reportedUserId, $bookingParticipantIds, true)) {
                throw new HttpException(422, 'Selected booking does not include the reported user.');
            }
        }

        if (! empty($validated['reported_user_id']) && ! $booking && empty($validated['reported_message_id']) && empty($validated['reported_review_id'])) {
            $reportedUser = User::query()->findOrFail((int) $validated['reported_user_id']);
            if (($validated['type'] ?? null) === 'profile' && $reportedUser->role !== 'teacher') {
                throw new HttpException(422, 'Only teacher profiles can be reported using profile type.');
            }
        }

        $report = Report::create([
            'reporter_id' => $userId,
            'reported_user_id' => $validated['reported_user_id'] ?? null,
            'reported_message_id' => $validated['reported_message_id'] ?? null,
            'reported_review_id' => $validated['reported_review_id'] ?? null,
            'booking_id' => $validated['booking_id'] ?? null,
            'type' => $validated['type'],
            'reason' => $validated['reason'],
        ]);

        return response()->json([
            'message' => 'Report submitted.',
            'report' => (new ReportResource($report))->resolve(),
        ], 201);
    }

    private function assertCanAccessBooking(int $userId, Booking $booking): void
    {
        if ((int) $booking->student_id !== $userId && (int) $booking->teacher_id !== $userId) {
            throw new HttpException(403, 'You cannot report this booking.');
        }
    }

    private function assertCanAccessConversation(int $userId, $conversation): void
    {
        $isParticipant = $conversation?->participants?->contains('id', $userId) ?? false;

        if (! $isParticipant) {
            throw new HttpException(403, 'You cannot report this message.');
        }
    }
}
