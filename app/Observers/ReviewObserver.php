<?php

namespace App\Observers;

use App\Models\Review;
use Illuminate\Cache\TaggableStore;
use Illuminate\Support\Facades\Cache;

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

        $this->invalidateTeachersCache();
    }

    public function updated(Review $review): void
    {
        if ($review->wasChanged(['is_visible', 'rating', 'comment'])) {
            $this->invalidateTeachersCache();
        }
    }

    public function deleted(Review $review): void
    {
        $this->invalidateTeachersCache();
    }

    private function invalidateTeachersCache(): void
    {
        if (Cache::getStore() instanceof TaggableStore) {
            Cache::tags(['teachers'])->flush();
        }

        $version = (int) Cache::get('teachers:cache_version', 1);
        Cache::forever('teachers:cache_version', $version + 1);
    }
}
