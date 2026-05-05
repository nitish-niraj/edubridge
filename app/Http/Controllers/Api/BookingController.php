<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\BookingIndexRequest;
use App\Http\Requests\Api\BookingStoreRequest;
use App\Http\Requests\Api\TeacherAvailabilityPublicRequest;
use App\Jobs\SendBookingConfirmationNotification;
use App\Models\Booking;
use App\Models\BookingSlot;
use App\Models\TeacherAvailability;
use App\Models\TeacherEarning;
use App\Models\TeacherProfile;
use App\Models\User;
use App\Services\BookingService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class BookingController extends Controller
{
    public function __construct(
        protected BookingService $bookingService
    ) {}

    /**
     * GET /api/teachers/{id}/availability?month=YYYY-MM
     */
    public function teacherAvailability(int $id, TeacherAvailabilityPublicRequest $request): JsonResponse
    {
        $validated = $request->validated();
        $month = $validated['month'] ?? now()->format('Y-m');
        $start = Carbon::createFromFormat('Y-m', $month)->startOfMonth();
        $end   = $start->copy()->endOfMonth();
        $teacherProfile = TeacherProfile::where('user_id', $id)->first();

        $this->refreshBookableSlotsForMonth($id, $start, $end);

        $slots = BookingSlot::where('teacher_id', $id)
            ->whereBetween('slot_date', [$start->toDateString(), $end->toDateString()])
            ->where('is_booked', false)
            ->whereNull('booking_id')
            ->orderBy('slot_date')
            ->orderBy('start_time')
            ->get();

        $grouped = $slots->groupBy(fn ($s) => $s->slot_date->format('Y-m-d'));

        $hourlyRate = (float) ($teacherProfile?->hourly_rate ?? 0);
        $isFree = (bool) ($teacherProfile?->is_free ?? $hourlyRate <= 0);

        return response()->json([
            'available_dates' => $grouped->keys()->values(),
            'slots'           => $grouped->map(fn ($group) => $group->map(fn ($s) => [
                'id'               => $s->id,
                'start_time'       => substr($s->start_time, 0, 5),
                'end_time'         => substr($s->end_time, 0, 5),
                'duration_minutes' => $s->duration_minutes,
                'hourly_rate'      => $isFree ? 0 : $hourlyRate,
                'price'            => $isFree ? 0 : round($hourlyRate * ((int) $s->duration_minutes / 60), 2),
                'platform_fee'     => $isFree ? 0 : round(($hourlyRate * ((int) $s->duration_minutes / 60)) * 0.12, 2),
                'is_free'          => $isFree,
            ])->values()),
        ]);
    }

    /**
     * POST /api/bookings
     */
    public function store(BookingStoreRequest $request): JsonResponse
    {
        $validated = $request->validated();

        $student = auth()->user();

        if ($student->status !== 'active') {
            return response()->json(['message' => 'Your account is not active.'], 403);
        }

        try {
            $bookingData = DB::transaction(function () use ($validated, $student) {
                // 1. Lock the slot for update
                $slot = BookingSlot::where('id', $validated['slot_id'])->lockForUpdate()->firstOrFail();

                // 2. Check if already booked (confirmed) or reserved (pending booking)
                if ($slot->is_booked) {
                    abort(422, 'This slot is already booked.');
                }

                if ($slot->booking_id) {
                    $existingBooking = Booking::query()->whereKey($slot->booking_id)->first();
                    if ($existingBooking && ! in_array($existingBooking->status, ['cancelled', 'no_show'], true)) {
                        abort(422, 'This slot is already booked.');
                    }
                }

                if ($student->id === $slot->teacher_id) {
                    abort(422, 'You cannot book your own slot.');
                }

                if (! $student->isStudent()) {
                    abort(403, 'Only students can create bookings.');
                }

                // Teacher Validations
                $teacher = User::findOrFail($slot->teacher_id);
                if ($teacher->status !== 'active') {
                    abort(422, 'The selected teacher is not currently active.');
                }

                $teacherProfile = TeacherProfile::where('user_id', $slot->teacher_id)->firstOrFail();
                if (!$teacherProfile->is_verified) {
                    abort(422, 'The selected teacher is not verified.');
                }

                // Check for duplicate booking at the same time
                $duplicateExists = Booking::where('student_id', $student->id)
                    ->where('start_at', '<', $slot->slot_date->format('Y-m-d') . ' ' . $slot->end_time)
                    ->where('end_at', '>', $slot->slot_date->format('Y-m-d') . ' ' . $slot->start_time)
                    ->whereNotIn('status', ['cancelled', 'no_show'])
                    ->exists();

                if ($duplicateExists) {
                    abort(422, 'You already have a booking overlapping with this time.');
                }

                $isFree = $teacherProfile->is_free;
                $durationHours = max(1, (int) $slot->duration_minutes) / 60;
                $price  = $isFree ? 0 : round((float) $teacherProfile->hourly_rate * $durationHours, 2);
                $platformFee   = round($price * 0.12, 2);
                $teacherPayout = round($price * 0.88, 2);

                $booking = Booking::create([
                    'student_id'     => $student->id,
                    'teacher_id'     => $slot->teacher_id,
                    'slot_id'        => $slot->id,
                    'start_at'       => $slot->slot_date->format('Y-m-d') . ' ' . $slot->start_time,
                    'end_at'         => $slot->slot_date->format('Y-m-d') . ' ' . $slot->end_time,
                    'status'         => $isFree ? 'confirmed' : 'pending',
                    'session_type'   => $validated['session_type'] ?? 'solo',
                    'subject'        => $validated['subject'] ?? null,
                    'notes'          => $validated['notes'] ?? null,
                    'price'          => $price,
                    'platform_fee'   => $platformFee,
                    'teacher_payout' => $teacherPayout,
                    'payment_status' => 'unpaid',
                ]);

                // Reserve slot for this booking; only mark is_booked=true once confirmed/paid.
                $slot->update([
                    'booking_id' => $booking->id,
                    'is_booked' => $isFree,
                ]);

                return [
                    'booking'          => $booking,
                    'requires_payment' => ! $isFree,
                    'amount'           => $isFree ? 0 : $price,
                ];
            });

            $bookingData['booking']->load([
                'student:id,name,avatar,role,status',
                'teacher:id,name,avatar,role,status',
                'slot:id,teacher_id,slot_date,start_time,end_time,duration_minutes,is_booked',
            ]);

            Log::info('Booking created.', [
                'booking_id' => $bookingData['booking']->id,
                'student_id' => $student->id,
                'teacher_id' => $bookingData['booking']->teacher_id,
                'requires_payment' => (bool) $bookingData['requires_payment'],
            ]);

            if (! $bookingData['requires_payment']) {
                SendBookingConfirmationNotification::dispatch($bookingData['booking']);
            }

            return response()->json($bookingData, 201);
        } catch (\Exception $e) {
            $status = $e instanceof \Symfony\Component\HttpKernel\Exception\HttpException ? $e->getStatusCode() : 500;
            Log::warning('Booking creation failed.', [
                'student_id' => $student->id ?? null,
                'slot_id' => $validated['slot_id'] ?? null,
                'status' => $status,
                'error' => $e->getMessage(),
            ]);
            return response()->json(['message' => $e->getMessage()], $status);
        }
    }

    /**
     * GET /api/bookings
     */
    public function index(BookingIndexRequest $request): JsonResponse
    {
        $validated = $request->validated();
        $user  = auth()->user();
        $query = Booking::query();

        if ($user->isStudent()) {
            $query->where('student_id', $user->id);
        } else {
            $query->where('teacher_id', $user->id);
        }

        if (! empty($validated['status'])) {
            $query->where('status', $validated['status']);
        }

        $bookings = $query->with([
            'student:id,name,avatar,role,status',
            'teacher:id,name,avatar,role,status',
            'slot:id,teacher_id,slot_date,start_time,end_time,duration_minutes,is_booked',
            'videoSession:id,booking_id,started_at,ended_at,duration_minutes,recording_url',
            'review:id,booking_id,reviewer_id,reviewee_id,rating,comment,is_visible,is_flagged,created_at',
        ])
            ->orderByDesc('start_at')
            ->paginate(20);

        $payload = $bookings->toArray();

        if ($user->isTeacher()) {
            $payload['earnings_summary'] = [
                'this_month' => (float) TeacherEarning::query()
                    ->where('teacher_id', $user->id)
                    ->where('status', 'released')
                    ->whereBetween('payout_date', [now()->startOfMonth(), now()->endOfMonth()])
                    ->sum('net_amount'),
                'total' => (float) TeacherEarning::query()
                    ->where('teacher_id', $user->id)
                    ->where('status', 'released')
                    ->sum('net_amount'),
                'pending' => (float) TeacherEarning::query()
                    ->where('teacher_id', $user->id)
                    ->where('status', 'pending')
                    ->sum('net_amount'),
            ];
        }

        return response()->json($payload);
    }

    /**
     * PATCH /api/bookings/{id}/cancel
     */
    public function cancel(int $id): JsonResponse
    {
        $booking = Booking::findOrFail($id);
        $user    = auth()->user();

        if ($user->id !== $booking->student_id && $user->id !== $booking->teacher_id) {
            return response()->json(['message' => 'Unauthorized.'], 403);
        }

        if (in_array($booking->status, ['cancelled', 'completed', 'no_show'])) {
            return response()->json(['message' => 'Booking cannot be cancelled from its current state.'], 422);
        }

        $result = $this->bookingService->cancelBooking($booking, $user);
        Log::info('Booking cancelled.', [
            'booking_id' => $booking->id,
            'cancelled_by' => $user->id,
            'refund_amount' => (float) ($result['refund_amount'] ?? 0),
            'refunded' => (bool) ($result['refunded'] ?? false),
        ]);

        return response()->json([
            'message'       => 'Booking cancelled successfully.',
            'refund_amount' => $result['refund_amount'],
            'refunded'      => $result['refunded'],
        ]);
    }

    /**
     * GET /api/bookings/{id}
     */
    public function show(int $id): JsonResponse
    {
        $booking = Booking::with([
            'student:id,name,avatar,role,status',
            'teacher:id,name,avatar,role,status',
            'slot:id,teacher_id,slot_date,start_time,end_time,duration_minutes,is_booked',
            'videoSession:id,booking_id,started_at,ended_at,duration_minutes,recording_url',
            'review:id,booking_id,reviewer_id,reviewee_id,rating,comment,is_visible,is_flagged,created_at',
            'payment:id,booking_id,status,amount,amount_paise,platform_fee,teacher_payout,paid_at,released_at',
        ])
            ->findOrFail($id);

        $user = auth()->user();
        if ($user->id !== $booking->student_id && $user->id !== $booking->teacher_id) {
            return response()->json(['message' => 'Unauthorized.'], 403);
        }

        $now = now();
        $tooEarly = true;
        $sessionExpired = true;

        if ($booking->start_at) {
            $joinStart = $booking->start_at->copy()->subMinutes(15);
            $joinEnd = $booking->start_at->copy()->addMinutes(30);

            $tooEarly = $now->isBefore($joinStart);
            $sessionExpired = $now->isAfter($joinEnd);
        }

        $response = $booking->toArray();
        $response['too_early'] = $tooEarly;
        $response['session_expired'] = $sessionExpired;

        return response()->json($response);
    }

    private function refreshBookableSlotsForMonth(int $teacherId, Carbon $start, Carbon $end): void
    {
        BookingSlot::query()
            ->where('teacher_id', $teacherId)
            ->whereBetween('slot_date', [$start->toDateString(), $end->toDateString()])
            ->where('is_booked', false)
            ->whereNull('booking_id')
            ->where('duration_minutes', '>', 60)
            ->delete();

        $availabilities = TeacherAvailability::query()
            ->where('teacher_id', $teacherId)
            ->where('is_active', true)
            ->get();

        foreach ($availabilities as $availability) {
            $date = $start->copy();

            while ($date->lte($end)) {
                $matchesDate = $availability->is_recurring
                    ? strtolower($date->format('l')) === $availability->day_of_week
                    : $availability->specific_date && $date->isSameDay(Carbon::parse($availability->specific_date));

                if ($matchesDate && $date->gte(Carbon::today())) {
                    $slotStart = Carbon::parse($date->toDateString() . ' ' . $availability->start_time);
                    $slotEnd = Carbon::parse($date->toDateString() . ' ' . $availability->end_time);

                    while ($slotStart->copy()->addMinutes(60)->lte($slotEnd)) {
                        $chunkEnd = $slotStart->copy()->addMinutes(60);

                        $reserved = BookingSlot::query()
                            ->where('teacher_id', $teacherId)
                            ->where('slot_date', $date->toDateString())
                            ->where('start_time', '<', $chunkEnd->format('H:i:s'))
                            ->where('end_time', '>', $slotStart->format('H:i:s'))
                            ->where(function ($query): void {
                                $query->where('is_booked', true)->orWhereNotNull('booking_id');
                            })
                            ->exists();

                        if (! $reserved) {
                            BookingSlot::firstOrCreate([
                                'teacher_id' => $teacherId,
                                'slot_date' => $date->toDateString(),
                                'start_time' => $slotStart->format('H:i:s'),
                            ], [
                                'end_time' => $chunkEnd->format('H:i:s'),
                                'duration_minutes' => 60,
                                'is_booked' => false,
                            ]);
                        }

                        $slotStart = $chunkEnd;
                    }
                }

                $date->addDay();
            }
        }
    }
}
