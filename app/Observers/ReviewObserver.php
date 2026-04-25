<?php

namespace App\Observers;

use App\Models\Review;

class ReviewObserver
{
    public function created(Review $review): void
    {
        $reviewer = $review->reviewer;
        $reviewee = $review->reviewee;

        if (! $reviewer || ! $reviewee) return;

        $isFlagged = false;

        // Flag if reviewer account created < 24h before review
        if ($reviewer->created_at && $reviewer->created_at->diffInHours($review->created_at) < 24) {
            $isFlagged = true;
        }

        // Flag if reviewer and reviewee share the same IP
        if ($reviewer->last_login_ip && $reviewee->last_login_ip
            && $reviewer->last_login_ip === $reviewee->last_login_ip) {
            $isFlagged = true;
        }

        if ($isFlagged) {
            $review->updateQuietly(['is_flagged' => true]);
        }
    }
}
