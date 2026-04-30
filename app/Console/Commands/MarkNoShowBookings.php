<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Booking;

class MarkNoShowBookings extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'bookings:mark-no-show';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Mark confirmed bookings as no_show if the session did not start within 15 minutes of start_at';

    /**
     * Execute the console command.
     */
    public function handle(): void
    {
        $cutoffTime = now()->subMinutes(15);

        $bookingsToUpdate = Booking::where('status', 'confirmed')
            ->where('start_at', '<=', $cutoffTime)
            ->whereDoesntHave('videoSession', function ($query) {
                $query->whereNotNull('started_at');
            })
            ->get();

        $count = 0;
        foreach ($bookingsToUpdate as $booking) {
            $booking->update(['status' => 'no_show']);
            // If the session was paid, it's held. We don't release to teacher until admin dispute or completion phase,
            // or maybe the teacher is paid for no-show. The prompt doesn't specify no-show payment logic yet, 
            // Phase 3C does payment. So we just update the status.
            $count++;
        }

        $this->info("Marked {$count} bookings as no_show.");
    }
}
