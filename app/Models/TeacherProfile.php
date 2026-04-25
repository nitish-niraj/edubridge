<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Laravel\Scout\Searchable;

class TeacherProfile extends Model
{
    use HasFactory;
    use Searchable;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'user_id',
        'bio',
        'experience_years',
        'previous_school',
        'hourly_rate',
        'is_free',
        'is_verified',
        'rating_avg',
        'total_reviews',
        'subjects',
        'languages',
        'gender',
        'availability',
        'onboarding_step',
        'tour_completed',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'subjects'      => 'array',
        'languages'     => 'array',
        'availability'  => 'array',
        'is_free'       => 'boolean',
        'is_verified'  => 'boolean',
        'hourly_rate'  => 'decimal:2',
        'rating_avg'   => 'decimal:2',
        'tour_completed' => 'boolean',
    ];

    // -------------------------------------------------------------------------
    // Relationships
    // -------------------------------------------------------------------------

    /**
     * Get the user that owns this teacher profile.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get all documents uploaded for verification.
     */
    public function documents(): HasMany
    {
        return $this->hasMany(TeacherDocument::class, 'teacher_id');
    }

    /**
     * Convert this model instance to an array for Scout indexing.
     */
    public function toSearchableArray(): array
    {
        return [
            'id' => $this->id,
            'name' => $this->user?->name ?? '',
            'bio' => $this->bio ?? '',
            'subjects' => implode(' ', $this->subjects ?? []),
            'languages' => implode(' ', $this->languages ?? []),
        ];
    }

    /**
     * Only active and verified teacher profiles should be indexed.
     */
    public function shouldBeSearchable(): bool
    {
        return $this->is_verified && $this->user?->status === 'active';
    }

    /**
     * A profile can be decided by admin once all required documents are uploaded
     * and not currently rejected.
     */
    public function isFullyVerifiable(): bool
    {
        $requiredTypes = ['degree', 'service_record'];

        foreach ($requiredTypes as $type) {
            $exists = $this->documents()
                ->where('type', $type)
                ->whereIn('status', ['pending', 'approved'])
                ->exists();

            if (! $exists) {
                return false;
            }
        }

        return true;
    }

}
