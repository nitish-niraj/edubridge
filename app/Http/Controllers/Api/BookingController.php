<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Booking;
use App\Models\BookingSlot;
use App\Models\TeacherProfile;
use App\Services\BookingService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class BookingController extends Controller
{
    public function __construct(
        protected BookingService $bookingService
    ) {}

    /**
     * GET /api/teachers/{id}/availability?month=YYYY-MM
     */
    public function teacherAvailability(int $id, Request $request): JsonResponse
    {
        $month = $request->query('month', now()->format('Y-m'));
        $start = Carbon::createFromFormat('Y-m', $month)->startOfMonth();
        $end   = $start->copy()->endOfMonth();

        $slots = BookingSlot::where('teacher_id', $id)
            ->whereBetween('slot_date', [$start->toDateString(), $end->toDateString()])
            ->where('is_booked', false)
            ->orderBy('slot_date')
            ->orderBy('start_time')
            ->get();

        $grouped = $slots->groupBy(fn ($s) => $s->slot_date->format('Y-m-d'));

        return response()->json([
            'available_dates' => $grouped->keys()->values(),
            'slots'           => $grouped->map(fn ($group) => $group->map(fn ($s) => [
                'id'               => $s->id,
                'start_time'       => substr($s->start_time, 0, 5),
                'end_time'         => substr($s->end_time, 0, 5),
                'duration_minutes' => $s->duration_minutes,
            ])->values()),
        ]);
    }

    /**
     * POST /api/bookings
     */
    public function store(Request $request): JsonResponse
    {
        $request->validate([
            'slot_id'      => 'required|exists:booking_slots,id',
            'subject'      => 'nullable|string|max:100',
            'notes'        => 'nullable|string|max:1000',
            'session_type' => 'nullable|in:solo,group',
        ]);

        $slot = BookingSlot::findOrFail($request->slot_id);
        $student = auth()->user();

        if ($slot->is_booked) {
            return response()->json(['message' => 'This slot is already booked.'], 422);
        }

        if ($student->id === $slot->teacher_id) {
            return response()->json(['message' => 'You cannot book your own slot.'], 422);
        }

        $teacherProfile = TeacherProfile::where('user_id', $slot->teacher_id)->firstOrFail();
        $isFree = $teacherProfile->is_free;
        $price  = $isFree ? 0 : (float) $teacherProfile->hourly_rate;
        $platformFee   = round($price * 0.12, 2);
        $teacherPayout = round($price * 0.88, 2);

        $booking = DB::transaction(function () use ($request, $slot, $student, $price, $platformFee, $teacherPayout, $isFree) {
            $booking = Booking::create([
                'student_id'     => $student->id,
                'teacher_id'     => $slot->teacher_id,
                'slot_id'        => $slot->id,
                'start_at'       => $slot->slot_date->format('Y-m-d') . ' ' . $slot->start_time,
                'end_at'         => $slot->slot_date->format('Y-m-d') . ' ' . $slot->end_time,
                'status'         => $isFree ? 'confirmed' : 'pending',
                'session_type'   => $request->input('session_type', 'solo'),
                'subject'        => $request->subject,
                'notes'          => $request->notes,
                'price'          => $price,
                'platform_fee'   => $platformFee,
                'teacher_payout' => $teacherPayout,
                'payment_status' => 'unpaid',
            ]);

            if ($isFree) {
                $slot->update(['is_booked' => true, 'booking_id' => $booking->id]);
            }

            return $booking;
        });

        $booking->load('student', 'teacher', 'slot');

        return response()->json([
            'booking'          => $booking,
            'requires_payment' => ! $isFree,
            'amount'           => $isFree ? 0 : $price,
        ], 201);
    }

    /**
     * GET /api/bookings
     */
    public function index(Request $request): JsonResponse
    {
        $user  = auth()->user();
        $query = Booking::query();

        if ($user->isStudent()) {
            $query->where('student_id', $user->id);
        } else {
            $query->where('teacher_id', $user->id);
        }

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        $bookings = $query->with(['student', 'teacher', 'slot', 'videoSession', 'review'])
            ->orderByDesc('start_at')
            ->paginate(20);

        return response()->json($bookings);
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

        if (in_array($booking->status, ['cancelled', 'completed'])) {
            return response()->json(['message' => 'Booking cannot be cancelled.'], 422);
        }

        $result = $this->bookingService->cancelBooking($booking, $user);

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
        $booking = Booking::with(['student', 'teacher', 'slot', 'videoSession', 'review', 'payment'])
            ->findOrFail($id);

        $user = auth()->user();
        if ($user->id !== $booking->student_id && $user->id !== $booking->teacher_id) {
            return response()->json(['message' => 'Unauthorized.'], 403);
        }

        return response()->json($booking);
    }
}
