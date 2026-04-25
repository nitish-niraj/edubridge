<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Str;

class TeacherCardResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $subjectTags = $this->subjects ?? [];
        $visibleSubjects = array_slice($subjectTags, 0, 3);

        return [
            'id' => $this->id,
            'teacher_id' => $this->user_id,
            'name' => $this->user?->name,
            'avatar' => $this->user?->avatar,
            'bio' => $this->bio,
            'bio_preview' => Str::limit((string) $this->bio, 140),
            'experience_years' => $this->experience_years,
            'rating_avg' => (float) $this->rating_avg,
            'total_reviews' => (int) $this->total_reviews,
            'subjects' => $subjectTags,
            'subjects_visible' => $visibleSubjects,
            'subjects_extra_count' => max(0, count($subjectTags) - count($visibleSubjects)),
            'languages' => $this->languages ?? [],
            'gender' => $this->gender,
            'is_free' => (bool) $this->is_free,
            'hourly_rate' => $this->is_free ? null : (float) $this->hourly_rate,
            'price_label' => $this->is_free
                ? 'FREE'
                : '₹' . ((int) round((float) $this->hourly_rate)) . '/hr',
            'availability' => $this->availability ?? [],
            'is_verified' => (bool) $this->is_verified,
            'is_saved' => (bool) ($this->is_saved ?? false),
        ];
    }
}
