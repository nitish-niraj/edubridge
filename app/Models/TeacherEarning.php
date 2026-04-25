<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TeacherEarning extends Model
{
    use HasFactory;

    protected $fillable = [
        'teacher_id',
        'payment_id',
        'booking_id',
        'gross_amount',
        'platform_fee',
        'net_amount',
        'status',
        'payout_date',
    ];

    protected $casts = [
        'gross_amount' => 'decimal:2',
        'platform_fee' => 'decimal:2',
        'net_amount'   => 'decimal:2',
        'payout_date'  => 'date',
    ];

    public function teacher(): BelongsTo
    {
        return $this->belongsTo(User::class, 'teacher_id');
    }

    public function payment(): BelongsTo
    {
        return $this->belongsTo(Payment::class);
    }

    public function booking(): BelongsTo
    {
        return $this->belongsTo(Booking::class);
    }
}
