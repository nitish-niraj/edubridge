<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\BookingSlot;
use App\Models\TeacherAvailability;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;

class TeacherAvailabilityController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $availabilities = $request->user()->teacherAvailability()
            ->where('is_active', true)
            ->orderBy('day_of_week')
            ->orderBy('start_time')
            ->get();

        return response()->json(['data' => $availabilities]);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $this->validateAvailability($request);
        $this->ensureNoOverlap($request->user(), $validated);

        $availability = $request->user()->teacherAvailability()->create($validated);

        $this->regenerateSlots($request->user());

        return response()->json(['data' => $availability, 'message' => 'Availability created successfully.'], 201);
    }

    public function update(Request $request, TeacherAvailability $availability): JsonResponse
    {
        if ($availability->teacher_id !== $request->user()->id) {
            abort(403);
        }

        $validated = $this->validateAvailability($request);
        $this->ensureNoOverlap($request->user(), $validated, $availability->id);

        $availability->update($validated);

        $this->regenerateSlots($request->user());

        return response()->json(['data' => $availability, 'message' => 'Availability updated successfully.']);
    }

    public function destroy(Request $request, TeacherAvailability $availability): JsonResponse
    {
        if ($availability->teacher_id !== $request->user()->id) {
            abort(403);
        }

        $availability->update(['is_active' => false]);
        $availability->delete();

        $this->regenerateSlots($request->user());

        return response()->json(['message' => 'Availability removed.']);
    }

    public function slots(Request $request): JsonResponse
    {
        $slots = BookingSlot::where('teacher_id', $request->user()->id)
            ->whereDate('slot_date', '>=', Carbon::today())
            ->orderBy('slot_date')
            ->orderBy('start_time')
            ->get();

        return response()->json(['data' => $slots]);
    }

    private function validateAvailability(Request $request): array
    {
        return $request->validate([
            'day_of_week'   => ['nullable', Rule::in(['monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday', 'sunday'])],
            'start_time'    => ['required', 'date_format:H:i'],
            'end_time'      => ['required', 'date_format:H:i', 'after:start_time'],
            'is_recurring'  => ['required', 'boolean'],
            'specific_date' => ['nullable', 'date', 'after_or_equal:today'],
        ]);
    }

    private function ensureNoOverlap(User $teacher, array $data, ?int $ignoreId = null): void
    {
        $existingAvailabilities = $teacher->teacherAvailability()
            ->where('is_active', true)
            ->when($ignoreId, fn ($q) => $q->where('id', '!=', $ignoreId))
            ->get();

        $newStart = Carbon::parse($data['start_time']);
        $newEnd = Carbon::parse($data['end_time']);
        
        $newIsRecurring = !empty($data['is_recurring']);
        $newDayOfWeek = $data['day_of_week'] ?? null;
        $newSpecificDate = !empty($data['specific_date']) ? Carbon::parse($data['specific_date']) : null;

        foreach ($existingAvailabilities as $avail) {
            // Check time overlap first
            $existStart = Carbon::parse($avail->start_time);
            $existEnd = Carbon::parse($avail->end_time);

            if ($existStart->lt($newEnd) && $existEnd->gt($newStart)) {
                // Time overlaps, now check if days overlap
                $daysOverlap = false;

                if ($newIsRecurring && $avail->is_recurring) {
                    if ($newDayOfWeek === $avail->day_of_week) {
                        $daysOverlap = true;
                    }
                } elseif (!$newIsRecurring && !$avail->is_recurring) {
                    if ($newSpecificDate && $avail->specific_date && $newSpecificDate->toDateString() === Carbon::parse($avail->specific_date)->toDateString()) {
                        $daysOverlap = true;
                    }
                } elseif ($newIsRecurring && !$avail->is_recurring) {
                    if ($avail->specific_date && strtolower(Carbon::parse($avail->specific_date)->format('l')) === $newDayOfWeek) {
                        $daysOverlap = true;
                    }
                } elseif (!$newIsRecurring && $avail->is_recurring) {
                    if ($newSpecificDate && strtolower($newSpecificDate->format('l')) === $avail->day_of_week) {
                        $daysOverlap = true;
                    }
                }

                if ($daysOverlap) {
                    abort(422, 'This availability overlaps with an existing time slot.');
                }
            }
        }
    }

    private function regenerateSlots(User $teacher): void
    {
        $today = Carbon::today();

        // 1. Delete future unbooked slots
        BookingSlot::where('teacher_id', $teacher->id)
            ->whereDate('slot_date', '>=', $today)
            ->where('is_booked', false)
            ->delete();

        // 2. Fetch all active availabilities
        $availabilities = $teacher->teacherAvailability()
            ->where('is_active', true)
            ->get();

        $end = $today->copy()->addDays(28);

        // 3. Generate slots
        foreach ($availabilities as $avail) {
            if ($avail->is_recurring && $avail->day_of_week) {
                $date = $today->copy();
                while ($date->lte($end)) {
                    if (strtolower($date->format('l')) === $avail->day_of_week) {
                        $this->createSlotIfNotExists($teacher->id, $date->toDateString(), $avail->start_time, $avail->end_time);
                    }
                    $date->addDay();
                }
            } elseif (!$avail->is_recurring && $avail->specific_date) {
                $date = Carbon::parse($avail->specific_date);
                if ($date->between($today, $end)) {
                    $this->createSlotIfNotExists($teacher->id, $date->toDateString(), $avail->start_time, $avail->end_time);
                }
            }
        }
    }

    private function createSlotIfNotExists(int $teacherId, string $date, string $startTime, string $endTime): void
    {
        // Don't create if a booked slot already exists for this exact time
        $bookedExists = BookingSlot::where('teacher_id', $teacherId)
            ->where('slot_date', $date)
            ->where('start_time', '<', $endTime)
            ->where('end_time', '>', $startTime)
            ->where('is_booked', true)
            ->exists();

        if ($bookedExists) return;

        BookingSlot::firstOrCreate([
            'teacher_id' => $teacherId,
            'slot_date'  => $date,
            'start_time' => $startTime,
        ], [
            'end_time'         => $endTime,
            'duration_minutes' => Carbon::parse($startTime)->diffInMinutes(Carbon::parse($endTime)),
            'is_booked'        => false,
        ]);
    }
}
