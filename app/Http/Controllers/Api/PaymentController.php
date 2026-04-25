<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Jobs\SendBookingConfirmationNotification;
use App\Models\Booking;
use App\Models\BookingSlot;
use App\Models\Payment;
use App\Services\PhonePeService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class PaymentController extends Controller
{
    public function __construct(
        protected PhonePeService $phonePeService
    ) {}

    /**
     * POST /api/payments/initiate
     */
    public function initiate(Request $request): JsonResponse
    {
        $request->validate([
            'booking_id' => 'required|exists:bookings,id',
        ]);

        $booking = Booking::findOrFail($request->booking_id);
        $user = auth()->user();

        if ($booking->student_id !== $user->id) {
            return response()->json(['message' => 'Unauthorized.'], 403);
        }

        if ($booking->payment_status !== 'unpaid') {
            return response()->json(['message' => 'Payment already initiated or completed.'], 422);
        }

        if ($booking->price <= 0) {
            return response()->json(['message' => 'This is a free session.'], 422);
        }

        $merchantOrderId = 'EDUB-' . $booking->id . '-' . time();
        $amountPaise = (int) ($booking->price * 100);
        $redirectUrl = config('app.url') . '/payment/callback?booking_id=' . $booking->id;

        $response = $this->phonePeService->initiatePayment($merchantOrderId, $amountPaise, $redirectUrl);

        Payment::create([
            'booking_id'        => $booking->id,
            'payer_id'          => $user->id,
            'amount'            => $booking->price,
            'amount_paise'      => $amountPaise,
            'platform_fee'      => $booking->platform_fee,
            'teacher_payout'    => $booking->teacher_payout,
            'merchant_order_id' => $merchantOrderId,
            'phonepe_order_id'  => $response['phonepe_order_id'] ?? null,
            'status'            => 'pending',
        ]);

        return response()->json([
            'redirect_url'      => $response['redirect_url'],
            'merchant_order_id' => $merchantOrderId,
        ]);
    }

    /**
     * GET /payment/callback (web route — PhonePe redirect back)
     */
    public function callback(Request $request): RedirectResponse
    {
        $bookingId = $request->query('booking_id');
        $booking   = Booking::with('payment')->findOrFail($bookingId);

        if ($booking->student_id !== auth()->id()) {
            abort(403);
        }

        $payment = $booking->payment;

        if (! $payment) {
            return redirect('/student/bookings?payment=failed&booking=' . $booking->id);
        }

        $status = $this->phonePeService->getOrderStatus($payment->merchant_order_id);

        if ($status['state'] === 'COMPLETED') {
            DB::transaction(function () use ($booking, $payment, $status) {
                $payment->update([
                    'status'           => 'held',
                    'phonepe_order_id' => $status['phonepe_order_id'],
                    'paid_at'          => now(),
                    'raw_response'     => $status['raw'],
                ]);
                $booking->update([
                    'status'         => 'confirmed',
                    'payment_status' => 'held',
                ]);
                BookingSlot::where('id', $booking->slot_id)
                    ->update(['is_booked' => true, 'booking_id' => $booking->id]);
            });

            dispatch(new SendBookingConfirmationNotification($booking));

            return redirect('/student/bookings?payment=success&booking=' . $booking->id);

        } elseif ($status['state'] === 'FAILED') {
            $payment->update(['status' => 'failed', 'raw_response' => $status['raw']]);
            return redirect('/student/bookings?payment=failed&booking=' . $booking->id);

        } else {
            // PENDING — payment still processing
            return redirect('/student/bookings?payment=pending&booking=' . $booking->id);
        }
    }

    /**
     * POST /api/webhooks/phonepe (server-to-server callback)
     */
    public function webhook(Request $request): JsonResponse
    {
        // Verify X-VERIFY header
        $payload    = $request->getContent();
        $xVerify    = (string) $request->header('X-VERIFY', '');
        $computed   = hash('sha256', base64_encode($payload)) . '###' . config('services.phonepe.client_secret');

        if ($xVerify === '' || ! hash_equals($computed, $xVerify)) {
            return response()->json(['code' => 'INVALID_SIGNATURE'], 400);
        }

        $data = json_decode($payload, true);
        $merchantOrderId = $data['merchantOrderId'] ?? null;

        if (! $merchantOrderId) {
            return response()->json(['code' => 'MISSING_ORDER_ID'], 400);
        }

        $payment = Payment::where('merchant_order_id', $merchantOrderId)->first();

        if (! $payment) {
            return response()->json(['code' => 'ORDER_NOT_FOUND'], 404);
        }

        $booking = $payment->booking;
        $state   = $data['state'] ?? '';

        if ($state === 'COMPLETED' && $payment->status === 'pending') {
            DB::transaction(function () use ($booking, $payment, $data) {
                $payment->update([
                    'status'           => 'held',
                    'phonepe_order_id' => $data['orderId'] ?? null,
                    'paid_at'          => now(),
                    'raw_response'     => $data,
                ]);
                $booking->update([
                    'status'         => 'confirmed',
                    'payment_status' => 'held',
                ]);
                BookingSlot::where('id', $booking->slot_id)
                    ->update(['is_booked' => true, 'booking_id' => $booking->id]);
            });

            dispatch(new SendBookingConfirmationNotification($booking));

        } elseif ($state === 'FAILED' && $payment->status === 'pending') {
            $payment->update(['status' => 'failed', 'raw_response' => $data]);
        }

        return response()->json(['code' => 'SUCCESS']);
    }
}
