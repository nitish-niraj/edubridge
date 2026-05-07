<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\PaymentInitiateRequest;
use App\Http\Requests\Api\PaymentVerifyRequest;
use App\Jobs\SendBookingConfirmationNotification;
use App\Models\Booking;
use App\Models\BookingSlot;
use App\Models\Payment;
use App\Services\PaymentGatewayService;
use App\Services\PhonePeService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

class PaymentController extends Controller
{
    public function __construct(
        protected PaymentGatewayService $paymentGateway,
        protected PhonePeService $phonePeService
    ) {}

    public function initiate(PaymentInitiateRequest $request): JsonResponse
    {
        $data = $request->validated();

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

        Log::info('Payment initiated.', [
            'payment_id' => $payment->id,
            'booking_id' => $booking->id,
            'student_id' => $user->id,
            'gateway' => $payment->gateway,
            'amount' => (float) $payment->amount,
        ]);

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

    public function verify(PaymentVerifyRequest $request): JsonResponse
    {
        $data = $request->validated();

        $payment = Payment::with('booking')
            ->where('gateway_order_id', $data['gateway_order_id'])
            ->firstOrFail();

        if (! $this->paymentGateway->verifyPaymentSignature($payment, $data['gateway_payment_id'], $data['signature'])) {
            return response()->json(['message' => 'Invalid payment signature.'], 422);
        }

        if ($payment->booking->status === 'cancelled') {
            return response()->json(['message' => 'Booking was cancelled before payment completed.'], 422);
        }

        if ($payment->status !== Payment::STATUS_PENDING) {
            return response()->json(['message' => 'Payment is already processed.'], 409);
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

    public function demoCheckout(string $gatewayOrderId): Response
    {
        $payment = Payment::with('booking.teacher:id,name')
            ->where('gateway_order_id', $gatewayOrderId)
            ->firstOrFail();

        abort_unless($payment->booking?->student_id === auth()->id(), 403);

        $amount = number_format((float) $payment->amount, 2);
        $teacherName = e($payment->booking->teacher?->name ?? 'Teacher');
        $completeUrl = e(url('/payment/demo/' . rawurlencode($payment->gateway_order_id) . '/complete'));
        $cancelUrl = e(url('/student/bookings?payment=pending&booking=' . $payment->booking_id));
        $csrf = csrf_field();

        return response(<<<HTML
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>PhonePe Payment Gateway</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;800&display=swap" rel="stylesheet">
    <style>
        body { margin: 0; min-height: 100vh; display: grid; place-items: center; background: #EAE6F0; font-family: 'Inter', sans-serif; color: #1f2937; }
        .wrapper { width: 100%; display: flex; flex-direction: column; align-items: center; justify-content: center; padding: 20px; box-sizing: border-box; }
        main { width: min(440px, 100%); background: #fff; border-radius: 24px; padding: 32px; box-shadow: 0 12px 40px rgba(95, 37, 159, 0.12); box-sizing: border-box; position: relative; overflow: hidden; }
        main::before { content: ''; position: absolute; top: 0; left: 0; right: 0; height: 6px; background: linear-gradient(90deg, #5f259f, #9b51e0); }
        .header { display: flex; align-items: center; justify-content: space-between; margin-bottom: 24px; }
        .brand-logo { font-size: 26px; font-weight: 800; color: #5f259f; letter-spacing: -0.5px; display: flex; align-items: center; gap: 8px; }
        .brand-logo svg { width: 28px; height: 28px; }
        .env-badge { background: #F3E8FF; color: #7E22CE; padding: 4px 10px; border-radius: 999px; font-size: 11px; font-weight: 800; text-transform: uppercase; letter-spacing: 1px; }
        .amount-container { text-align: center; padding: 32px 0; border-bottom: 1px dashed #e2e8f0; margin-bottom: 24px; }
        .amount-label { color: #64748b; font-size: 14px; font-weight: 600; text-transform: uppercase; letter-spacing: 1px; margin-bottom: 8px; display: block; }
        .amount { font-size: 48px; font-weight: 800; color: #0f172a; margin: 0; line-height: 1; }
        .details-box { background: #f8fafc; border-radius: 16px; padding: 20px; margin-bottom: 24px; }
        .row { display: flex; justify-content: space-between; padding: 8px 0; }
        .row span { color: #64748b; font-size: 14px; }
        .row strong { color: #334155; font-size: 14px; font-weight: 600; text-align: right; }
        .actions { display: flex; flex-direction: column; gap: 12px; }
        button, a.btn-cancel { width: 100%; min-height: 52px; border-radius: 14px; border: 0; display: inline-flex; align-items: center; justify-content: center; text-decoration: none; font-size: 16px; font-weight: 600; cursor: pointer; transition: all 0.2s; }
        button { background: #5f259f; color: #fff; box-shadow: 0 4px 12px rgba(95, 37, 159, 0.25); }
        button:hover { background: #4a1d7d; transform: translateY(-1px); box-shadow: 0 6px 16px rgba(95, 37, 159, 0.3); }
        button:active { transform: translateY(0); box-shadow: none; }
        a.btn-cancel { background: transparent; color: #64748b; border: 2px solid transparent; }
        a.btn-cancel:hover { background: #f1f5f9; color: #475569; }
        .footer-note { text-align: center; font-size: 12px; color: #94a3b8; margin-top: 24px; }
    </style>
</head>
<body>
    <div class="wrapper">
        <main>
            <div class="header">
                <div class="brand-logo">
                    <svg viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                        <path d="M12 2C6.48 2 2 6.48 2 12C2 17.52 6.48 22 12 22C17.52 22 22 17.52 22 12C22 6.48 17.52 2 12 2ZM15.29 14.71L12.41 11.83C12.02 11.44 11.46 11.23 10.93 11.35C10.45 11.45 10 11.9 9.89 12.38C9.77 12.91 9.98 13.47 10.37 13.86L13.25 16.74C13.66 17.15 14.33 17.15 14.74 16.74C15.15 16.33 15.15 15.66 14.74 15.25L15.29 14.71ZM13.86 10.37L10.98 7.49C10.57 7.08 9.9 7.08 9.49 7.49C9.08 7.9 9.08 8.57 9.49 8.98L12.37 11.86C12.76 12.25 13.32 12.46 13.85 12.34C14.33 12.24 14.78 11.79 14.89 11.31C15.01 10.78 14.8 10.22 14.41 9.83L13.86 10.37Z" fill="currentColor"/>
                    </svg>
                    PhonePe
                </div>
                <div class="env-badge">Local Sandbox</div>
            </div>
            
            <div class="amount-container">
                <span class="amount-label">You are paying</span>
                <h1 class="amount">₹{$amount}</h1>
            </div>

            <div class="details-box">
                <div class="row">
                    <span>Pay to</span>
                    <strong>EduBridge Inc.</strong>
                </div>
                <div class="row">
                    <span>Teacher</span>
                    <strong>{$teacherName}</strong>
                </div>
                <div class="row">
                    <span>Transaction ID</span>
                    <strong style="font-family: monospace; letter-spacing: -0.5px;">{$payment->gateway_order_id}</strong>
                </div>
            </div>

            <form method="post" action="{$completeUrl}" class="actions">
                {$csrf}
                <button type="submit">Pay Securely</button>
                <a href="{$cancelUrl}" class="btn-cancel">Cancel and Return</a>
            </form>

            <div class="footer-note">
                Secured by PhonePe Demo Simulator
            </div>
        </main>
    </div>
</body>
</html>
HTML);
    }

    public function demoComplete(string $gatewayOrderId): RedirectResponse
    {
        $payment = Payment::with('booking')
            ->where('gateway_order_id', $gatewayOrderId)
            ->firstOrFail();

        abort_unless($payment->booking?->student_id === auth()->id(), 403);

        $this->markPaymentHeld($payment, [
            'gateway_payment_id' => $payment->gateway_payment_id ?: ('DEMO-' . $payment->gateway_order_id),
            'raw' => ['state' => 'COMPLETED', 'source' => 'local_demo_checkout'],
            'verification' => 'local_demo_checkout',
        ]);

        return redirect('/student/bookings?payment=success&booking=' . $payment->booking_id);
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
            Log::info('Payment held and booking confirmed.', [
                'payment_id' => $payment->id,
                'booking_id' => $payment->booking_id,
                'gateway_payment_id' => $context['gateway_payment_id'] ?? null,
                'verification' => $context['verification'] ?? 'unknown',
            ]);
            dispatch(new SendBookingConfirmationNotification($payment->booking));
        }
    }

    private function markPaymentFailed(Payment $payment, array $raw): void
    {
        $payment->refresh();

        if ($payment->status !== Payment::STATUS_PENDING) {
            return;
        }

        DB::transaction(function () use ($payment, $raw): void {
            $lockedPayment = Payment::whereKey($payment->id)->lockForUpdate()->firstOrFail();

            if ($lockedPayment->status !== Payment::STATUS_PENDING) {
                return;
            }

            $booking = Booking::whereKey($lockedPayment->booking_id)->lockForUpdate()->first();
            $lockedPayment->transitionTo(Payment::STATUS_FAILED, ['raw_response' => $raw]);

            if (! $booking) {
                return;
            }

            // If the booking was never confirmed/paid, cancel and release the slot reservation.
            if ($booking->status === 'pending' && $booking->payment_status === 'unpaid') {
                $booking->update(['status' => 'cancelled']);

                BookingSlot::where('id', $booking->slot_id)
                    ->update(['is_booked' => false, 'booking_id' => null]);
            }
        });

        Log::warning('Payment failed.', [
            'payment_id' => $payment->id,
            'booking_id' => $payment->booking_id,
            'gateway' => $payment->gateway,
        ]);
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
