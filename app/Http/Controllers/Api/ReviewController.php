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
     */
    public function store(ReviewStoreRequest $request): JsonResponse
    {
        $validated = $request->validated();

        $booking = Booking::findOrFail($validated['booking_id']);
        $user    = auth()->user();

        if ($user->id !== $booking->student_id) {
            return response()->json(['message' => 'Only the student can leave a review.'], 403);
        }

        if (! in_array($booking->status, ['completed', 'no_show'], true)) {
            // Rule: if the session time has passed by more than 10 minutes, allow review anyway
            $sessionEndWithBuffer = $booking->end_at?->addMinutes(10);
            if (! $sessionEndWithBuffer || now()->lt($sessionEndWithBuffer)) {
                return response()->json(['message' => 'Can only review sessions after they have ended.'], 422);
            }
        }

        // Check for existing review
        if (Review::where('booking_id', $booking->id)->exists()) {
            return response()->json(['message' => 'You have already reviewed this session.'], 422);
        }

        try {
            $review = DB::transaction(function () use ($request, $booking, $user) {
                $review = Review::create([
                    'booking_id'  => $booking->id,
                    'reviewer_id' => $user->id,
                    'reviewee_id' => $booking->teacher_id,
                    'rating'      => $request->validated('rating'),
                    'comment'     => $request->validated('comment'),
                ]);

                app(ReviewRatingService::class)->recalculateForTeacher($booking->teacher_id);
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
