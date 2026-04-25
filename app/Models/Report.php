<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Report extends Model
{
    protected $fillable = [
        'reporter_id', 'reported_user_id', 'reported_message_id', 'reported_review_id', 'booking_id',
        'type', 'reason', 'status', 'admin_notes', 'resolved_by', 'resolved_at',
    ];

    protected $casts = [
        'resolved_at' => 'datetime',
    ];

    public function reporter(): BelongsTo { return $this->belongsTo(User::class, 'reporter_id'); }
    public function reportedUser(): BelongsTo { return $this->belongsTo(User::class, 'reported_user_id'); }
    public function resolvedByAdmin(): BelongsTo { return $this->belongsTo(User::class, 'resolved_by'); }
    public function booking(): BelongsTo { return $this->belongsTo(Booking::class); }
    public function message(): BelongsTo { return $this->belongsTo(Message::class, 'reported_message_id'); }
    public function review(): BelongsTo { return $this->belongsTo(Review::class, 'reported_review_id'); }
}
