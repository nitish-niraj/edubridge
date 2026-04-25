<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class VideoSession extends Model
{
    use HasFactory;

    protected $fillable = [
        'booking_id',
        'room_name',
        'twilio_room_sid',
        'started_at',
        'ended_at',
        'duration_minutes',
        'recording_url',
    ];

    protected $casts = [
        'started_at' => 'datetime',
        'ended_at'   => 'datetime',
    ];

    public function booking(): BelongsTo
    {
        return $this->belongsTo(Booking::class);
    }
}
