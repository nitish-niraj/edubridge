<?php

namespace App\Http\Controllers\Teacher;

use App\Http\Controllers\Controller;
use App\Models\BookingSlot;
use App\Models\TeacherAvailability;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class AvailabilityController extends Controller
{
    /**
     * Show the availability settings page.
     */
    public function index(): Response
    {
        $availability = auth()->user()
            ->teacherAvailability()
            ->where('is_recurring', true)
            ->where('is_active', true)
            ->get()
            ->keyBy('day_of_week');

        return Inertia::render('Teacher/Availability', [
            'availability' => $availability,
        ]);
    }

    /**
     * Store / update availability for all 7 days.
     */
    public function store(Request $request): JsonResponse
    {
        $days = ['monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday', 'sunday'];

        $request->validate([
            'days'               => 'required|array',
            'days.*.enabled'     => 'required|boolean',
            'days.*.day_of_week' => ['required', Rule::in($days)],
            'days.*.start_time'  => 'required_if:days.*.enabled,true|nullable|date_format:H:i',
            'days.*.end_time'    => 'required_if:days.*.enabled,true|nullable|date_format:H:i',
        ]);

        $teacher = auth()->user();

        // Validate end_time > start_time on enabled days
        foreach ($request->input('days') as $i => $day) {
            if ($day['enabled'] && $day['start_time'] && $day['end_time']) {
                if ($day['end_time'] <= $day['start_time']) {
                    return response()->json([
                        'errors' => ["days.{$i}.end_time" => ["End time must be after start time for {$day['day_of_week']}."]]
                    ], 422);
                }
            }
        }

        // Delete all existing recurring availability
        $teacher->teacherAvailability()
            ->where('is_recurring', true)
            ->delete();

        // Insert fresh for enabled days
        foreach ($request->input('days') as $day) {
            if ($day['enabled'] && $day['start_time'] && $day['end_time']) {
                TeacherAvailability::create([
                    'teacher_id'  => $teacher->id,
                    'day_of_week' => $day['day_of_week'],
                    'start_time'  => $day['start_time'],
                    'end_time'    => $day['end_time'],
                    'is_recurring' => true,
                    'is_active'   => true,
                ]);
            }
        }

        // Keep booking slots in sync so students can book immediately.
        // We only delete future unbooked slots; booked slots remain intact.
        $today = Carbon::today();
        BookingSlot::where('teacher_id', $teacher->id)
            ->whereDate('slot_date', '>=', $today->toDateString())
            ->where('is_booked', false)
            ->delete();

        $availabilities = TeacherAvailability::where('teacher_id', $teacher->id)
            ->where('is_active', true)
            ->where('is_recurring', true)
            ->get();

        $end = $today->copy()->addDays(28);
        foreach ($availabilities as $avail) {
            $date = $today->copy();

            while ($date->lte($end)) {
                if (strtolower($date->format('l')) === $avail->day_of_week) {
                    BookingSlot::firstOrCreate([
                        'teacher_id'  => $avail->teacher_id,
                        'slot_date'   => $date->toDateString(),
                        'start_time'  => $avail->start_time,
                    ], [
                        'end_time'         => $avail->end_time,
                        'duration_minutes' => Carbon::parse($avail->start_time)->diffInMinutes(Carbon::parse($avail->end_time)),
                        'is_booked'        => false,
                    ]);
                }

                $date->addDay();
            }
        }

        return response()->json(['message' => 'Availability saved successfully.']);
    }
}
