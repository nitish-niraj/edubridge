<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BookingSlot extends Model
{
    use HasFactory;

    protected $fillable = [
        'teacher_id',
        'slot_date',
        'start_time',
        'end_time',
        'duration_minutes',
        'is_booked',
        'booking_id',
    ];

    protected $casts = [
        'slot_date'  => 'date',
        'is_booked'  => 'boolean',
    ];

    public function teacher(): BelongsTo
    {
        return $this->belongsTo(User::class, 'teacher_id');
    }

    public function booking(): BelongsTo
    {
        return $this->belongsTo(Booking::class);
    }
}
