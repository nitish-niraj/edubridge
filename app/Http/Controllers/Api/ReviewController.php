<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\ReviewStoreRequest;
use App\Models\Booking;
use App\Models\Review;
use App\Services\NotificationService;
use App\Services\ReviewRatingService;
use Illuminate\Http\JsonResponse;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;

class ReviewController extends Controller
{
    /**
     * POST /api/reviews
     *
     * Either party of a completed/no-show session may submit one review:
     * - Student reviews the teacher (reviewee = teacher).
     * - Teacher reviews the student (reviewee = student).
     */
    public function store(ReviewStoreRequest $request): JsonResponse
    {
        $validated = $request->validated();

        $booking = Booking::findOrFail($validated['booking_id']);
        $user    = auth()->user();

        $isStudent = $user->id === $booking->student_id;
        $isTeacher = $user->id === $booking->teacher_id;

        if (! $isStudent && ! $isTeacher) {
            return response()->json(['message' => 'Only participants of the session can leave a review.'], 403);
        }

        if (! in_array($booking->status, ['completed', 'no_show'], true)) {
            $sessionEndWithBuffer = $booking->end_at?->addMinutes(10);
            if (! $sessionEndWithBuffer || now()->lt($sessionEndWithBuffer)) {
                return response()->json(['message' => 'Can only review sessions after they have ended.'], 422);
            }
        }

        if (Review::where('booking_id', $booking->id)->where('reviewer_id', $user->id)->exists()) {
            return response()->json(['message' => 'You have already reviewed this session.'], 422);
        }

        $revieweeId = $isStudent ? $booking->teacher_id : $booking->student_id;

        try {
            $review = DB::transaction(function () use ($validated, $booking, $user, $revieweeId) {
                $review = Review::create([
                    'booking_id'  => $booking->id,
                    'reviewer_id' => $user->id,
                    'reviewee_id' => $revieweeId,
                    'rating'      => $validated['rating'],
                    'comment'     => $validated['comment'] ?? null,
                ]);

                app(ReviewRatingService::class)->recalculateForUser($revieweeId);
                app(NotificationService::class)->sendReviewReceived($review);

                return $review;
            });
        } catch (QueryException $exception) {
            if ((string) $exception->getCode() === '23000') {
                return response()->json(['message' => 'You have already reviewed this session.'], 422);
            }

            throw $exception;
        }

        return response()->json([
            'message' => 'Review submitted successfully.',
            'review'  => $review,
        ], 201);
    }
}
