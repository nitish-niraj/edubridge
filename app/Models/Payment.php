<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use InvalidArgumentException;

class Payment extends Model
{
    use HasFactory;

    public const STATUS_PENDING = 'pending';
    public const STATUS_HELD = 'held';
    public const STATUS_RELEASED = 'released';
    public const STATUS_REFUNDED = 'refunded';
    public const STATUS_FAILED = 'failed';

    public const ALLOWED_TRANSITIONS = [
        self::STATUS_PENDING => [self::STATUS_HELD, self::STATUS_FAILED],
        self::STATUS_HELD => [self::STATUS_RELEASED, self::STATUS_REFUNDED],
        self::STATUS_RELEASED => [],
        self::STATUS_REFUNDED => [],
        self::STATUS_FAILED => [],
    ];

    protected $fillable = [
        'booking_id',
        'payer_id',
        'amount',
        'amount_paise',
        'platform_fee',
        'teacher_payout',
        'gateway',
        'gateway_order_id',
        'gateway_payment_id',
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

    public function canTransitionTo(string $status): bool
    {
        return in_array($status, self::ALLOWED_TRANSITIONS[$this->status] ?? [], true);
    }

    public function transitionTo(string $status, array $attributes = []): void
    {
        if ($this->status === $status) {
            if ($attributes !== []) {
                $this->forceFill($attributes)->save();
            }

            return;
        }

        if (! $this->canTransitionTo($status)) {
            throw new InvalidArgumentException("Invalid payment transition from {$this->status} to {$status}.");
        }

        $this->forceFill(array_merge($attributes, ['status' => $status]))->save();
    }

    public function getMerchantOrderIdAttribute(): ?string
    {
        return $this->gateway_order_id;
    }

    public function setMerchantOrderIdAttribute(?string $value): void
    {
        $this->attributes['gateway_order_id'] = $value;
    }

    public function getPhonepeOrderIdAttribute(): ?string
    {
        return $this->gateway_payment_id;
    }

    public function setPhonepeOrderIdAttribute(?string $value): void
    {
        $this->attributes['gateway_payment_id'] = $value;
    }
}
