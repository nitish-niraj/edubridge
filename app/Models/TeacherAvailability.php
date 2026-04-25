<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TeacherAvailability extends Model
{
    use HasFactory;

    protected $table = 'teacher_availability';

    protected $fillable = [
        'teacher_id',
        'day_of_week',
        'start_time',
        'end_time',
        'is_recurring',
        'specific_date',
        'is_active',
    ];

    protected $casts = [
        'is_recurring' => 'boolean',
        'is_active'    => 'boolean',
        'specific_date' => 'date',
    ];

    public function teacher(): BelongsTo
    {
        return $this->belongsTo(User::class, 'teacher_id');
    }
}
