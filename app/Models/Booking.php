<?php

namespace App\Models;

use App\Jobs\ReleasePayment;
use App\Jobs\SendReviewReminder;
use App\Services\NotificationService;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

class Booking extends Model
{
    use HasFactory;
    use SoftDeletes;

    protected $fillable = [
        'student_id',
        'teacher_id',
        'slot_id',
        'start_at',
        'end_at',
        'status',
        'session_type',
        'subject',
        'notes',
        'price',
        'platform_fee',
        'teacher_payout',
        'payment_status',
    ];

    protected $casts = [
        'start_at'       => 'datetime',
        'end_at'         => 'datetime',
        'price'          => 'decimal:2',
        'platform_fee'   => 'decimal:2',
        'teacher_payout' => 'decimal:2',
    ];

    protected static function booted(): void
    {
        static::updated(function (Booking $booking): void {
            if (
                $booking->wasChanged('status')
                && $booking->status === 'completed'
                && $booking->payment_status === 'held'
            ) {
                ReleasePayment::dispatch($booking->id)->delay(now()->addHours(24));
            }

            if ($booking->wasChanged('status') && $booking->status === 'completed') {
                app(NotificationService::class)->sendSessionCompleted($booking->fresh(['student.notificationPreferences', 'teacher.notificationPreferences']));
                SendReviewReminder::dispatch($booking->id)->delay(now()->addMinutes(10));
            }
        });
    }

    // ── Relationships ───────────────────────────────────────────────────────

    public function student(): BelongsTo
    {
        return $this->belongsTo(User::class, 'student_id');
    }

    public function teacher(): BelongsTo
    {
        return $this->belongsTo(User::class, 'teacher_id');
    }

    public function slot(): BelongsTo
    {
        return $this->belongsTo(BookingSlot::class, 'slot_id');
    }

    public function payment(): HasOne
    {
        return $this->hasOne(Payment::class);
    }

    public function videoSession(): HasOne
    {
        return $this->hasOne(VideoSession::class);
    }

    public function review(): HasOne
    {
        return $this->hasOne(Review::class);
    }

    public function reports(): HasMany
    {
        return $this->hasMany(Report::class);
    }

    public function events(): HasMany
    {
        return $this->hasMany(BookingEvent::class);
    }
}
