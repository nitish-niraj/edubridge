<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Booking;
use App\Models\Review;
use App\Models\TeacherProfile;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ReviewController extends Controller
{
    /**
     * POST /api/reviews
     */
    public function store(Request $request): JsonResponse
    {
        $request->validate([
            'booking_id' => 'required|exists:bookings,id',
            'rating'     => 'required|integer|min:1|max:5',
            'comment'    => 'nullable|string|max:2000',
        ]);

        $booking = Booking::findOrFail($request->booking_id);
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
                'rating'      => $request->rating,
                'comment'     => $request->comment,
            ]);

            // Update teacher profile aggregates
            $avgRating    = Review::where('reviewee_id', $booking->teacher_id)
                ->where('is_visible', true)
                ->avg('rating');
            $totalReviews = Review::where('reviewee_id', $booking->teacher_id)
                ->where('is_visible', true)
                ->count();

            TeacherProfile::where('user_id', $booking->teacher_id)->update([
                'rating_avg'    => round($avgRating, 2),
                'total_reviews' => $totalReviews,
            ]);

            return $review;
        });

        return response()->json([
            'message' => 'Review submitted successfully.',
            'review'  => $review,
        ], 201);
    }
}
