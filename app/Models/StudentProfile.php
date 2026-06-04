<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StudentProfile extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'user_id',
        'class_grade',
        'school_name',
        'subjects_needed',
        'preferred_language',
        'rating_avg',
        'total_reviews',
        'onboarding_completed',
    ];

    protected $casts = [
        'subjects_needed'      => 'array',
        'rating_avg'           => 'decimal:2',
        'total_reviews'        => 'integer',
        'onboarding_completed' => 'boolean',
    ];

    // -------------------------------------------------------------------------
    // Relationships
    // -------------------------------------------------------------------------

    /**
     * Get the user that owns this student profile.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
