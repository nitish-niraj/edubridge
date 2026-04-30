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
        $availabilities = TeacherAvailability::where('is_active', true)->get();

        $today = Carbon::today();
        $end   = $today->copy()->addDays(28);
        $count = 0;

        foreach ($availabilities as $avail) {
            if ($avail->is_recurring && $avail->day_of_week) {
                $date = $today->copy();
                while ($date->lte($end)) {
                    if (strtolower($date->format('l')) === $avail->day_of_week) {
                        if ($this->createSlotIfNotExists($avail->teacher_id, $date->toDateString(), $avail->start_time, $avail->end_time)) {
                            $count++;
                        }
                    }
                    $date->addDay();
                }
            } elseif (!$avail->is_recurring && $avail->specific_date) {
                $date = Carbon::parse($avail->specific_date);
                if ($date->between($today, $end)) {
                    if ($this->createSlotIfNotExists($avail->teacher_id, $date->toDateString(), $avail->start_time, $avail->end_time)) {
                        $count++;
                    }
                }
            }
        }

        $this->info("Generated {$count} new booking slots.");

        return Command::SUCCESS;
    }

    private function createSlotIfNotExists(int $teacherId, string $date, string $startTime, string $endTime): bool
    {
        // Check for booked slot overlap
        $bookedExists = BookingSlot::where('teacher_id', $teacherId)
            ->where('slot_date', $date)
            ->where('start_time', '<', $endTime)
            ->where('end_time', '>', $startTime)
            ->where('is_booked', true)
            ->exists();

        if ($bookedExists) return false;

        $slot = BookingSlot::firstOrCreate([
            'teacher_id' => $teacherId,
            'slot_date'  => $date,
            'start_time' => $startTime,
        ], [
            'end_time'         => $endTime,
            'duration_minutes' => Carbon::parse($startTime)->diffInMinutes(Carbon::parse($endTime)),
            'is_booked'        => false,
        ]);

        return $slot->wasRecentlyCreated;
    }
}
