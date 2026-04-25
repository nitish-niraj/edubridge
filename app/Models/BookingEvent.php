<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BookingEvent extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'booking_id', 'event', 'data', 'created_by', 'created_at',
    ];

    protected $casts = [
        'data'       => 'array',
        'created_at' => 'datetime',
    ];

    public function booking(): BelongsTo { return $this->belongsTo(Booking::class); }
    public function creator(): BelongsTo { return $this->belongsTo(User::class, 'created_by'); }
}
