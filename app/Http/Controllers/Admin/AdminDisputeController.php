<?php

namespace App\Http\Controllers\Admin;

use App\Helpers\AuditLogger;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\DisputeActionRequest;
use App\Http\Requests\Admin\DisputeIndexRequest;
use App\Http\Requests\Admin\DisputePartialRefundRequest;
use App\Http\Resources\BookingResource;
use App\Mail\DisputeResolvedMail;
use App\Models\Booking;
use App\Models\BookingEvent;
use App\Models\Payment;
use App\Models\TeacherEarning;
use App\Services\PhonePeService;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Log;
use Illuminate\Http\JsonResponse;

class AdminDisputeController extends Controller
{
    public function __construct(protected PhonePeService $phonePeService) {}

    public function index(DisputeIndexRequest $request): JsonResponse
    {
        $validated = $request->validated();
        $search = trim((string) ($validated['search'] ?? ''));
        $status = $validated['status'] ?? 'all';
        $type = $validated['type'] ?? 'all';

        $disputes = Booking::query()
            ->with(['student:id,name,avatar', 'teacher:id,name,avatar'])
            ->withCount('reports')
            ->where(function ($query) use ($type): void {
                if ($type === 'all' || $type === 'cancellation') {
                    $query->orWhere(function ($cancelledQuery): void {
                    $cancelledQuery->where('status', 'cancelled')
                        ->whereHas('payment', function ($paymentQuery): void {
                            $paymentQuery->whereIn('status', ['held', 'paid']);
                        });
                    });
                }

                if ($type === 'all' || $type === 'report') {
                    $query->orWhereHas('reports');
                }

                if ($type === 'all' || $type === 'no_show') {
                    $query->orWhere('status', 'no_show');
                }
            });

        if ($status !== 'all') {
            if (in_array($status, ['cancelled', 'no_show'], true)) {
                $disputes->where('status', $status);
            } else {
                $disputes->whereHas('reports', fn ($query) => $query->where('status', $status));
            }
        }

        if (! empty($validated['date_from'])) {
            $disputes->whereDate('start_at', '>=', $validated['date_from']);
        }

        if (! empty($validated['date_to'])) {
            $disputes->whereDate('start_at', '<=', $validated['date_to']);
        }

        if ($search !== '') {
            $disputes->where(function ($query) use ($search): void {
                $query->where('id', 'like', "%{$search}%")
                    ->orWhere('subject', 'like', "%{$search}%")
                    ->orWhereHas('student', function ($q) use ($search): void {
                        $q->where('name', 'like', "%{$search}%")
                            ->orWhere('email', 'like', "%{$search}%");
                    })
                    ->orWhereHas('teacher', function ($q) use ($search): void {
                        $q->where('name', 'like', "%{$search}%")
                            ->orWhere('email', 'like', "%{$search}%");
                    });
            });
        }

        $disputes = $disputes
            ->orderByDesc('updated_at')
            ->paginate(20);

        return BookingResource::collection($disputes)->response();
    }

    public function show(int $id): JsonResponse
    {
        $booking = Booking::with([
            'student:id,name,email,avatar',
            'teacher:id,name,email,avatar',
            'payment',
            'reports.reporter:id,name,avatar',
            'reports.reportedUser:id,name,avatar',
            'reports.review.reviewer:id,name,avatar',
            'reports.review.reviewee:id,name,avatar',
            'reports.message:id,conversation_id,sender_id,body,created_at',
            'reports.message.sender:id,name,avatar',
            'events.creator:id,name,avatar',
        ])->withCount('reports')->findOrFail($id);

        $events = BookingEvent::with('creator:id,name,avatar')
            ->where('booking_id', $id)
            ->orderBy('created_at')
            ->get();

        $conversationId = $booking->reports
            ->map(fn ($report) => $report->message?->conversation_id)
            ->filter()
            ->first();

        $booking->setAttribute('conversation_id', $conversationId);
        $booking->setRelation('events', $events);

        return response()->json([
            'booking' => (new BookingResource($booking))->resolve(),
            'events' => \App\Http\Resources\BookingEventResource::collection($events)->resolve(),
            'reports' => \App\Http\Resources\ReportResource::collection($booking->reports)->resolve(),
        ]);
    }

    public function fullRefund(DisputeActionRequest $request, int $id): JsonResponse
    {
        $booking = Booking::with('payment')->findOrFail($id);
        $payment = $booking->payment;

        if (! $payment) {
            return response()->json(['message' => 'No payment found.'], 404);
        }

        if ($payment->status === 'refunded' || $booking->payment_status === 'refunded') {
            return response()->json(['message' => 'This dispute is already refunded.'], 422);
        }

        if ($payment->status === 'released' || $booking->payment_status === 'released') {
            return response()->json(['message' => 'Payment is already released to the teacher for this dispute.'], 422);
        }

        if (empty($payment->merchant_order_id)) {
            return response()->json(['message' => 'Payment reference is missing. Unable to process refund.'], 422);
        }

        $refundOrderId = 'REFUND-' . $payment->merchant_order_id . '-' . time();

        $refundResponse = null;

        try {
            $refundResponse = $this->phonePeService->initiateRefund(
                $refundOrderId,
                $payment->merchant_order_id,
                $payment->amount_paise
            );
        } catch (\Throwable $exception) {
            if (! app()->environment(['local', 'testing'])) {
                throw $exception;
            }

            Log::warning('Simulating full refund in local/testing environment due payment gateway error.', [
                'booking_id' => $booking->id,
                'payment_id' => $payment->id,
                'error' => $exception->getMessage(),
            ]);
        }

        $payment->transitionTo(Payment::STATUS_REFUNDED, [
            'raw_response' => array_merge($payment->raw_response ?? [], [
                'admin_refund' => [
                    'type' => 'full',
                    'amount' => (float) $payment->amount,
                    'refund_order_id' => $refundOrderId,
                    'response' => $refundResponse,
                    'admin_id' => auth()->id(),
                    'processed_at' => now()->toIso8601String(),
                ],
            ]),
        ]);
        $booking->update(['payment_status' => 'refunded']);

        $this->logEvent($id, 'full_refund', ['amount' => $payment->amount, 'note' => $request->input('note')]);
        AuditLogger::log('dispute.full_refund', 'Booking', $id, ['amount' => $payment->amount]);
        $this->notifyResolution($booking, 'Full refund processed.', (float) $payment->amount);

        return response()->json(['message' => 'Full refund processed.']);
    }

    public function partialRefund(DisputePartialRefundRequest $request, int $id): JsonResponse
    {
        $booking = Booking::with('payment')->findOrFail($id);
        $payment = $booking->payment;

        if (! $payment) {
            return response()->json(['message' => 'No payment found.'], 404);
        }

        if ($payment->status === 'refunded' || $booking->payment_status === 'refunded') {
            return response()->json(['message' => 'This dispute is already refunded.'], 422);
        }

        if ($payment->status === 'released' || $booking->payment_status === 'released') {
            return response()->json(['message' => 'Payment is already released to the teacher for this dispute.'], 422);
        }

        if (empty($payment->merchant_order_id)) {
            return response()->json(['message' => 'Payment reference is missing. Unable to process refund.'], 422);
        }

        $amount = (float) $request->input('amount');
        if ($amount > (float) $payment->amount) {
            return response()->json(['message' => 'Partial refund amount cannot exceed paid amount.'], 422);
        }

        $amountPaise = (int) ($amount * 100);
        $refundOrderId = 'REFUND-' . $payment->merchant_order_id . '-' . time();

        $refundResponse = null;

        try {
            $refundResponse = $this->phonePeService->initiateRefund($refundOrderId, $payment->merchant_order_id, $amountPaise);
        } catch (\Throwable $exception) {
            if (! app()->environment(['local', 'testing'])) {
                throw $exception;
            }

            Log::warning('Simulating partial refund in local/testing environment due payment gateway error.', [
                'booking_id' => $booking->id,
                'payment_id' => $payment->id,
                'amount' => $amount,
                'error' => $exception->getMessage(),
            ]);
        }

        $payment->transitionTo(Payment::STATUS_REFUNDED, [
            'raw_response' => array_merge($payment->raw_response ?? [], [
                'admin_refund' => [
                    'type' => 'partial',
                    'amount' => $amount,
                    'refund_order_id' => $refundOrderId,
                    'response' => $refundResponse,
                    'admin_id' => auth()->id(),
                    'processed_at' => now()->toIso8601String(),
                ],
            ]),
        ]);
        $booking->update(['payment_status' => 'refunded']);

        $this->logEvent($id, 'partial_refund', ['amount' => $request->input('amount'), 'note' => $request->input('note')]);
        AuditLogger::log('dispute.partial_refund', 'Booking', $id, ['amount' => $request->input('amount')]);
        $this->notifyResolution($booking, 'Partial refund processed.', $amount);

        return response()->json(['message' => 'Partial refund processed.']);
    }

    public function releaseToTeacher(DisputeActionRequest $request, int $id): JsonResponse
    {
        $booking = Booking::with('payment')->findOrFail($id);
        $payment = $booking->payment;

        if (! $payment) {
            return response()->json(['message' => 'No payment found.'], 404);
        }

        if ($payment->status === 'refunded' || $booking->payment_status === 'refunded') {
            return response()->json(['message' => 'Cannot release payout because this dispute is already refunded.'], 422);
        }

        if ($payment->status === 'released' || $booking->payment_status === 'released') {
            return response()->json(['message' => 'Payment is already released to the teacher.'], 422);
        }

        $payment->transitionTo(Payment::STATUS_RELEASED, [
            'released_at' => now(),
        ]);
        $booking->update(['payment_status' => 'released']);

        TeacherEarning::create([
            'teacher_id'  => $booking->teacher_id,
            'booking_id'  => $booking->id,
            'amount'      => $payment->teacher_payout,
            'type'        => 'session',
            'released_at' => now(),
        ]);

        $this->logEvent($id, 'released_to_teacher', ['amount' => $payment->teacher_payout, 'note' => $request->input('note')]);
        AuditLogger::log('dispute.released', 'Booking', $id);
        $this->notifyResolution($booking, 'Payment released to teacher.', 0);

        return response()->json(['message' => 'Payment released to teacher.']);
    }

    public function close(DisputeActionRequest $request, int $id): JsonResponse
    {
        $this->logEvent($id, 'dispute_closed', ['note' => $request->input('note')]);
        AuditLogger::log('dispute.closed', 'Booking', $id);

        $booking = Booking::with(['student', 'teacher'])->findOrFail($id);
        $this->notifyResolution($booking, 'Dispute closed.', 0);

        return response()->json(['message' => 'Dispute closed.']);
    }

    private function logEvent(int $bookingId, string $event, array $data = []): void
    {
        BookingEvent::create([
            'booking_id' => $bookingId,
            'event'      => $event,
            'data'       => $data,
            'created_by' => auth()->id(),
            'created_at' => now(),
        ]);
    }

    private function notifyResolution(Booking $booking, string $resolution, float $refundAmount): void
    {
        $booking->loadMissing(['student', 'teacher']);

        foreach ([$booking->student, $booking->teacher] as $user) {
            if ($user?->email) {
                Mail::to($user->email)->send(new DisputeResolvedMail($booking, $resolution, $refundAmount, $user->role));
            }
        }
    }
}
