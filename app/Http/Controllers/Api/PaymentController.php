<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Jobs\SendBookingConfirmationNotification;
use App\Models\Booking;
use App\Models\BookingSlot;
use App\Models\Payment;
use App\Services\PaymentGatewayService;
use App\Services\PhonePeService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Throwable;

class PaymentController extends Controller
{
    public function __construct(
        protected PaymentGatewayService $paymentGateway,
        protected PhonePeService $phonePeService
    ) {}

    public function initiate(Request $request): JsonResponse
    {
        $data = $request->validate([
            'booking_id' => 'required|exists:bookings,id',
            'gateway' => 'nullable|in:phonepe,razorpay',
        ]);

        $booking = Booking::with('payment')->lockForUpdate()->findOrFail($data['booking_id']);
        $user = $request->user();

        if ($booking->student_id !== $user->id) {
            return response()->json(['message' => 'Unauthorized.'], 403);
        }

        if ($booking->status !== 'pending' || $booking->payment_status !== 'unpaid') {
            return response()->json(['message' => 'Booking is not payable.'], 422);
        }

        if ((float) $booking->price <= 0) {
            return response()->json(['message' => 'This booking does not require payment.'], 422);
        }

        $gateway = $data['gateway'] ?? 'phonepe';
        $order = $this->paymentGateway->createOrder($booking, $gateway);
        $amountPaise = (int) round((float) $booking->price * 100);

        $payment = Payment::updateOrCreate(
            ['booking_id' => $booking->id],
            [
                'payer_id' => $user->id,
                'amount' => $booking->price,
                'amount_paise' => $amountPaise,
                'platform_fee' => round((float) $booking->price * 0.12, 2),
                'teacher_payout' => round((float) $booking->price * 0.88, 2),
                'gateway' => $order['gateway'],
                'gateway_order_id' => $order['gateway_order_id'],
                'gateway_payment_id' => $order['gateway_payment_id'],
                'status' => Payment::STATUS_PENDING,
                'raw_response' => $order['raw_response'],
            ]
        );

        return response()->json([
            'payment_id' => $payment->id,
            'gateway' => $payment->gateway,
            'gateway_order_id' => $payment->gateway_order_id,
            'gateway_payment_id' => $payment->gateway_payment_id,
            'merchant_order_id' => $payment->gateway_order_id,
            'redirect_url' => $order['checkout']['redirect_url'] ?? null,
            'checkout' => $order['checkout'],
        ]);
    }

    public function verify(Request $request): JsonResponse
    {
        $data = $request->validate([
            'gateway_order_id' => 'required|string',
            'gateway_payment_id' => 'required|string',
            'signature' => 'required|string',
        ]);

        $payment = Payment::with('booking')
            ->where('gateway_order_id', $data['gateway_order_id'])
            ->firstOrFail();

        if (! $this->paymentGateway->verifyPaymentSignature($payment, $data['gateway_payment_id'], $data['signature'])) {
            return response()->json(['message' => 'Invalid payment signature.'], 422);
        }

        if ($payment->booking->status === 'cancelled') {
            return response()->json(['message' => 'Booking was cancelled before payment completed.'], 422);
        }

        $this->markPaymentHeld($payment, [
            'gateway_payment_id' => $data['gateway_payment_id'],
            'verification' => 'api',
        ]);

        return response()->json([
            'message' => 'Payment verified and held.',
            'payment_status' => Payment::STATUS_HELD,
            'booking_status' => 'confirmed',
        ]);
    }

    public function callback(Request $request): RedirectResponse
    {
        $booking = Booking::with('payment')->findOrFail($request->query('booking_id'));

        if ($booking->student_id !== auth()->id()) {
            abort(403);
        }

        $payment = $booking->payment;

        if (! $payment) {
            return redirect('/student/bookings?payment=failed&booking=' . $booking->id);
        }

        $status = $this->phonePeService->getOrderStatus($payment->gateway_order_id);

        if (($status['state'] ?? null) === 'COMPLETED') {
            $this->markPaymentHeld($payment, [
                'gateway_payment_id' => $status['phonepe_order_id'] ?? null,
                'raw' => $status,
                'verification' => 'phonepe_callback',
            ]);

            return redirect('/student/bookings?payment=success&booking=' . $booking->id);
        }

        if (($status['state'] ?? null) === 'FAILED') {
            $this->markPaymentFailed($payment, $status);

            return redirect('/student/bookings?payment=failed&booking=' . $booking->id);
        }

        return redirect('/student/bookings?payment=pending&booking=' . $booking->id);
    }

    public function webhook(Request $request): JsonResponse
    {
        $payload = $request->getContent();
        $gateway = $request->query('gateway', $request->header('X-Payment-Gateway', 'phonepe'));
        $signature = $request->header('X-Razorpay-Signature')
            ?: $request->header('X-Webhook-Signature')
            ?: $request->header('X-VERIFY', '');

        try {
            if ($this->isReplay($request)) {
                return response()->json(['code' => 'IGNORED']);
            }

            if (! $this->paymentGateway->verifyWebhookSignature($gateway, $payload, (string) $signature)) {
                return response()->json(['code' => 'INVALID_SIGNATURE']);
            }

            $data = json_decode($payload, true) ?: [];
            $orderId = $this->extractOrderId($data);

            if (! $orderId) {
                return response()->json(['code' => 'MISSING_ORDER_ID']);
            }

            $payment = Payment::with('booking')->where('gateway_order_id', $orderId)->first();

            if (! $payment) {
                return response()->json(['code' => 'ORDER_NOT_FOUND']);
            }

            $state = $this->extractState($data);

            if ($state === 'completed') {
                $this->markPaymentHeld($payment, [
                    'gateway_payment_id' => $this->extractPaymentId($data),
                    'raw' => $data,
                    'verification' => 'webhook',
                ]);
            } elseif ($state === 'failed') {
                $this->markPaymentFailed($payment, $data);
            }
        } catch (Throwable) {
            return response()->json(['code' => 'IGNORED']);
        }

        return response()->json(['code' => 'SUCCESS']);
    }

    private function markPaymentHeld(Payment $payment, array $context = []): void
    {
        $payment->refresh();

        if ($payment->status !== Payment::STATUS_PENDING) {
            return;
        }

        $held = false;

        DB::transaction(function () use ($payment, $context, &$held) {
            $lockedPayment = Payment::whereKey($payment->id)->lockForUpdate()->firstOrFail();
            $booking = Booking::whereKey($lockedPayment->booking_id)->lockForUpdate()->firstOrFail();

            if ($lockedPayment->status !== Payment::STATUS_PENDING || $booking->status === 'cancelled') {
                return;
            }

            $lockedPayment->transitionTo(Payment::STATUS_HELD, [
                'gateway_payment_id' => $context['gateway_payment_id'] ?? $lockedPayment->gateway_payment_id,
                'paid_at' => now(),
                'raw_response' => $context['raw'] ?? array_filter($context),
            ]);

            $booking->update([
                'status' => 'confirmed',
                'payment_status' => 'held',
            ]);

            BookingSlot::where('id', $booking->slot_id)
                ->update(['is_booked' => true, 'booking_id' => $booking->id]);

            $held = true;
        });

        if ($held) {
            dispatch(new SendBookingConfirmationNotification($payment->booking));
        }
    }

    private function markPaymentFailed(Payment $payment, array $raw): void
    {
        $payment->refresh();

        if ($payment->status !== Payment::STATUS_PENDING) {
            return;
        }

        $payment->transitionTo(Payment::STATUS_FAILED, ['raw_response' => $raw]);
    }

    private function extractOrderId(array $data): ?string
    {
        return $data['gateway_order_id']
            ?? $data['merchantOrderId']
            ?? $data['payload']['payment']['entity']['order_id']
            ?? $data['payload']['order']['entity']['id']
            ?? null;
    }

    private function extractPaymentId(array $data): ?string
    {
        return $data['gateway_payment_id']
            ?? $data['orderId']
            ?? $data['payload']['payment']['entity']['id']
            ?? null;
    }

    private function extractState(array $data): string
    {
        $state = strtoupper((string) ($data['state'] ?? $data['event'] ?? ''));

        return match (true) {
            in_array($state, ['COMPLETED', 'PAYMENT.CAPTURED', 'CAPTURED', 'SUCCESS'], true) => 'completed',
            in_array($state, ['FAILED', 'PAYMENT.FAILED'], true) => 'failed',
            default => 'pending',
        };
    }

    private function isReplay(Request $request): bool
    {
        $timestamp = $request->header('X-Webhook-Timestamp');

        return is_numeric($timestamp) && abs(now()->timestamp - (int) $timestamp) > 300;
    }
}
