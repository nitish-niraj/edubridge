<?php

namespace App\Services;

use App\Models\Review;
use App\Models\TeacherProfile;

class ReviewRatingService
{
    public function recalculateForTeacher(int $teacherId): void
    {
        $visibleReviews = Review::query()
            ->where('reviewee_id', $teacherId)
            ->where('is_visible', true);

        TeacherProfile::where('user_id', $teacherId)->update([
            'rating_avg' => round((float) $visibleReviews->avg('rating'), 2),
            'total_reviews' => (clone $visibleReviews)->count(),
        ]);
    }
}
