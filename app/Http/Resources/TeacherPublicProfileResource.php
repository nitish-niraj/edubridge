<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class TeacherPublicProfileResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $availability = $this->availability ?? [];
        $latestReviews = collect($this->latest_reviews ?? [])->map(function (array $review): array {
            return [
                'id' => $review['id'] ?? null,
                'rating' => (float) ($review['rating'] ?? 0),
                'student_name' => $review['student_name'] ?? null,
                'comment' => $review['comment'] ?? null,
                'date' => $review['date'] ?? null,
            ];
        })->values();

        return [
            'id' => $this->id,
            'teacher_id' => $this->user_id,
            'name' => $this->user?->name,
            'avatar' => $this->user?->avatar,
            'is_verified' => (bool) $this->is_verified,
            'bio' => $this->bio,
            'subjects' => $this->subjects ?? [],
            'languages' => $this->languages ?? [],
            'rating_avg' => (float) $this->rating_avg,
            'total_reviews' => (int) $this->total_reviews,
            'experience_years' => $this->experience_years,
            'is_free' => (bool) $this->is_free,
            'hourly_rate' => $this->is_free ? null : (float) $this->hourly_rate,
            'gender' => $this->gender,
            'availability' => $availability,
            'availability_summary' => $this->availability_summary
                ?? $this->buildAvailabilitySummary($availability),
            'is_saved' => (bool) ($this->is_saved ?? false),
            'latest_reviews' => $latestReviews,
        ];
    }

    private function buildAvailabilitySummary(array $availability): string
    {
        if ($availability === []) {
            return 'Availability not provided yet.';
        }

        $activeDays = collect($availability)
            ->filter(function ($day): bool {
                return (bool) ($day['enabled'] ?? $day['on'] ?? false);
            })
            ->keys()
            ->values()
            ->all();

        if ($activeDays === []) {
            return 'Currently not accepting time slots.';
        }

        return 'Available on ' . implode(', ', $activeDays) . '.';
    }
}
