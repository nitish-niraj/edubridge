<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Payment extends Model
{
    use HasFactory;

    protected $fillable = [
        'booking_id',
        'payer_id',
        'amount',
        'amount_paise',
        'platform_fee',
        'teacher_payout',
        'gateway',
        'merchant_order_id',
        'phonepe_order_id',
        'status',
        'paid_at',
        'released_at',
        'raw_response',
    ];

    protected $casts = [
        'amount'         => 'decimal:2',
        'platform_fee'   => 'decimal:2',
        'teacher_payout' => 'decimal:2',
        'paid_at'        => 'datetime',
        'released_at'    => 'datetime',
        'raw_response'   => 'array',
    ];

    public function booking(): BelongsTo
    {
        return $this->belongsTo(Booking::class);
    }

    public function payer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'payer_id');
    }
}
