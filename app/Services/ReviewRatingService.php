<?php

namespace App\Services;

use App\Models\Review;
use App\Models\StudentProfile;
use App\Models\TeacherProfile;
use App\Models\User;

class ReviewRatingService
{
    public function recalculateForTeacher(int $teacherId): void
    {
        $this->recalculateForUser($teacherId);
    }

    public function recalculateForUser(int $userId): void
    {
        $visibleReviews = Review::query()
            ->where('reviewee_id', $userId)
            ->where('is_visible', true);

        $avg   = round((float) $visibleReviews->avg('rating'), 2);
        $count = (clone $visibleReviews)->count();

        $user = User::find($userId);
        if (! $user) {
            return;
        }

        if ($user->isTeacher()) {
            TeacherProfile::where('user_id', $userId)->update([
                'rating_avg'    => $avg,
                'total_reviews' => $count,
            ]);
            return;
        }

        if ($user->isStudent()) {
            StudentProfile::where('user_id', $userId)->update([
                'rating_avg'    => $avg,
                'total_reviews' => $count,
            ]);
        }
    }
}
