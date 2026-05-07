<?php

namespace App\Services;

use App\Models\Booking;
use App\Models\Payment;
use Illuminate\Support\Facades\Log;
use RuntimeException;
use Throwable;

class PaymentGatewayService
{
    public function __construct(
        protected PhonePeService $phonePeService
    ) {}

    public function createOrder(Booking $booking, string $gateway): array
    {
        $orderId = $this->buildMerchantOrderId($booking->id);
        $amountPaise = (int) round((float) $booking->price * 100);

        if ($gateway === 'phonepe') {
            try {
                $response = $this->phonePeService->initiatePayment(
                    $orderId,
                    $amountPaise,
                    config('app.url') . '/payment/callback?booking_id=' . $booking->id
                );

                return [
                    'gateway' => 'phonepe',
                    'gateway_order_id' => $orderId,
                    'gateway_payment_id' => $response['phonepe_order_id'] ?? null,
                    'checkout' => [
                        'redirect_url' => $response['redirect_url'] ?? null,
                        'state' => $response['state'] ?? null,
                        'expire_at' => $response['expire_at'] ?? null,
                    ],
                    'raw_response' => $response,
                ];
            } catch (Throwable $exception) {
                Log::warning('PhonePe checkout unavailable; using local demo checkout.', [
                    'booking_id' => $booking->id,
                    'error' => $exception->getMessage(),
                ]);

                return [
                    'gateway' => 'phonepe',
                    'gateway_order_id' => $orderId,
                    'gateway_payment_id' => 'DEMO-' . $orderId,
                    'checkout' => [
                        'redirect_url' => url('/payment/demo/' . rawurlencode($orderId)),
                        'state' => 'DEMO',
                        'expire_at' => now()->addMinutes(15)->toISOString(),
                    ],
                    'raw_response' => [
                        'state' => 'DEMO',
                        'source' => 'local_demo_checkout',
                        'error' => $exception->getMessage(),
                    ],
                ];
            }
        }

        if ($gateway === 'razorpay') {
            return [
                'gateway' => 'razorpay',
                'gateway_order_id' => 'order_' . $orderId,
                'gateway_payment_id' => null,
                'checkout' => [
                    'key' => config('services.razorpay.key'),
                    'amount' => $amountPaise,
                    'currency' => 'INR',
                    'name' => config('app.name', 'EduBridge'),
                ],
                'raw_response' => ['local_order' => true],
            ];
        }

        throw new RuntimeException('Unsupported payment gateway.');
    }

    public function verifyPaymentSignature(Payment $payment, string $paymentId, string $signature): bool
    {
        $secret = $this->gatewaySecret($payment->gateway);
        $payload = $payment->gateway_order_id . '|' . $paymentId;
        $expected = hash_hmac('sha256', $payload, $secret);

        return $signature !== '' && hash_equals($expected, $signature);
    }

    public function verifyWebhookSignature(string $gateway, string $payload, string $signature): bool
    {
        $secret = $gateway === 'razorpay'
            ? (string) config('services.razorpay.webhook_secret')
            : $this->gatewaySecret($gateway);

        if ($gateway === 'phonepe' && str_contains($signature, '###')) {
            $expected = hash('sha256', base64_encode($payload)) . '###' . $secret;

            return hash_equals($expected, $signature);
        }

        $expected = hash_hmac('sha256', $payload, $secret);

        return $signature !== '' && hash_equals($expected, $signature);
    }

    public function refund(Payment $payment): array
    {
        if ($payment->gateway_payment_id && str_starts_with($payment->gateway_payment_id, 'DEMO-')) {
            return [
                'state' => 'COMPLETED',
                'order_id' => 'DEMO-REFUND-' . $payment->booking_id . '-' . now()->timestamp,
            ];
        }

        if ($payment->gateway === 'phonepe') {
            return $this->phonePeService->initiateRefund(
                $this->buildRefundOrderId((int) $payment->booking_id),
                $payment->gateway_order_id,
                (int) $payment->amount_paise
            );
        }

        if ($payment->gateway === 'razorpay') {
            return [
                'id' => 'rfnd_' . $payment->id . '_' . now()->timestamp,
                'status' => 'processed',
                'amount' => (int) $payment->amount_paise,
            ];
        }

        return ['status' => 'skipped', 'gateway' => $payment->gateway];
    }

    private function gatewaySecret(string $gateway): string
    {
        $secret = match ($gateway) {
            'razorpay' => config('services.razorpay.secret'),
            'phonepe' => config('services.phonepe.client_secret'),
            default => null,
        };

        if (! is_string($secret) || $secret === '') {
            throw new RuntimeException('Payment gateway secret is not configured.');
        }

        return $secret;
    }

    private function buildMerchantOrderId(int $bookingId): string
    {
        $raw = 'EDUB-' . $bookingId . '-' . now()->timestamp;

        return substr(preg_replace('/[^A-Za-z0-9_-]/', '', $raw) ?: $raw, 0, 63);
    }

    private function buildRefundOrderId(int $bookingId): string
    {
        $raw = 'REFUND-' . $bookingId . '-' . now()->timestamp;

        return substr(preg_replace('/[^A-Za-z0-9_-]/', '', $raw) ?: $raw, 0, 63);
    }
}
