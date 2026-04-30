<?php

namespace App\Jobs;

use App\Models\Booking;
use App\Services\NotificationService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class SendSessionReminders implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public function handle(NotificationService $notifications): void
    {
        foreach ([1440, 60, 15] as $minutesBefore) {
            $windowStart = now()->copy()->addMinutes($minutesBefore);
            $windowEnd = $windowStart->copy()->addMinutes(59);

            Booking::query()
                ->with(['student.notificationPreferences', 'teacher.notificationPreferences'])
                ->where('status', 'confirmed')
                ->whereBetween('start_at', [$windowStart, $windowEnd])
                ->orderBy('start_at')
                ->chunkById(100, function ($bookings) use ($notifications, $minutesBefore): void {
                    foreach ($bookings as $booking) {
                        $notifications->sendSessionReminder($booking, $minutesBefore);
                    }
                });
        }
    }
}
