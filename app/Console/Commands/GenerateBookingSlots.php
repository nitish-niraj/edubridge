<?php

namespace App\Console\Commands;

use App\Models\BookingSlot;
use App\Models\TeacherAvailability;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;

class GenerateBookingSlots extends Command
{
    protected $signature = 'slots:generate';
    protected $description = 'Generate booking slots for the next 28 days based on teacher availability';

    public function handle(): int
    {
        $availabilities = TeacherAvailability::where('is_active', true)
            ->where('is_recurring', true)
            ->get();

        $today = Carbon::today();
        $end   = $today->copy()->addDays(28);
        $count = 0;

        foreach ($availabilities as $avail) {
            $date = $today->copy();

            while ($date->lte($end)) {
                if (strtolower($date->format('l')) === $avail->day_of_week) {
                    $exists = BookingSlot::where('teacher_id', $avail->teacher_id)
                        ->where('slot_date', $date->toDateString())
                        ->where('start_time', $avail->start_time)
                        ->exists();

                    if (! $exists) {
                        BookingSlot::create([
                            'teacher_id'      => $avail->teacher_id,
                            'slot_date'        => $date->toDateString(),
                            'start_time'       => $avail->start_time,
                            'end_time'         => $avail->end_time,
                            'duration_minutes' => Carbon::parse($avail->start_time)->diffInMinutes(Carbon::parse($avail->end_time)),
                        ]);
                        $count++;
                    }
                }

                $date->addDay();
            }
        }

        $this->info("Generated {$count} new booking slots.");

        return Command::SUCCESS;
    }
}
