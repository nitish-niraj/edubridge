<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\ReviewStoreRequest;
use App\Models\Booking;
use App\Models\Review;
use App\Services\ReviewRatingService;
use Illuminate\Http\JsonResponse;
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

        if ($booking->status !== 'completed') {
            return response()->json(['message' => 'Can only review completed sessions.'], 422);
        }

        // Check for existing review
        if (Review::where('booking_id', $booking->id)->exists()) {
            return response()->json(['message' => 'You have already reviewed this session.'], 422);
        }

        $review = DB::transaction(function () use ($request, $booking, $user) {
            $review = Review::create([
                'booking_id'  => $booking->id,
                'reviewer_id' => $user->id,
                'reviewee_id' => $booking->teacher_id,
                'rating'      => $request->validated('rating'),
                'comment'     => $request->validated('comment'),
            ]);

            app(ReviewRatingService::class)->recalculateForTeacher($booking->teacher_id);

            return $review;
        });

        return response()->json([
            'message' => 'Review submitted successfully.',
            'review'  => $review,
        ], 201);
    }
}
