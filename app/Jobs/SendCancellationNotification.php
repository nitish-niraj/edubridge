<?php

namespace App\Jobs;

use App\Models\Booking;
use App\Services\NotificationService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Throwable;

class SendCancellationNotification implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public int $timeout = 120;

    public function backoff(): array
    {
        return [30, 120, 300];
    }

    public function __construct(
        public Booking $booking,
        public float   $refundAmount = 0
    ) {}

    public function handle(NotificationService $notifications): void
    {
        $notifications->sendBookingCancelled($this->booking, $this->refundAmount);
    }

    public function failed(Throwable $exception): void
    {
        Log::error('SendCancellationNotification failed.', [
            'booking_id' => $this->booking->id,
            'refund_amount' => $this->refundAmount,
            'error' => $exception->getMessage(),
        ]);
    }
}
