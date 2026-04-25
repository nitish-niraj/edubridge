<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UserNotificationPreference extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'new_message_email',
        'new_message_sms',
        'booking_confirmed_email',
        'booking_confirmed_sms',
        'session_reminder_email',
        'session_reminder_sms',
        'booking_cancelled_email',
        'review_received_email',
        'high_contrast',
    ];

    protected $casts = [
        'new_message_email'        => 'boolean',
        'new_message_sms'          => 'boolean',
        'booking_confirmed_email'  => 'boolean',
        'booking_confirmed_sms'    => 'boolean',
        'session_reminder_email'   => 'boolean',
        'session_reminder_sms'     => 'boolean',
        'booking_cancelled_email'  => 'boolean',
        'review_received_email'    => 'boolean',
        'high_contrast'            => 'boolean',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
