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
        'conversation_id',
        'is_group',
        'host_id',
        'room_name',
        'room_type',
        'twilio_room_sid',
        'started_at',
        'ended_at',
        'duration_minutes',
        'recording_url',
        'composition_sid',
    ];

    protected $casts = [
        'started_at' => 'datetime',
        'ended_at'   => 'datetime',
        'is_group'   => 'boolean',
    ];

    public function booking(): BelongsTo
    {
        return $this->belongsTo(Booking::class);
    }
}
