<?php

namespace App\Services;

use PhonePe\payments\v2\standardCheckout\StandardCheckoutClient;
use PhonePe\payments\v2\models\request\builders\StandardCheckoutPayRequestBuilder;
use PhonePe\Env;
use RuntimeException;

class PhonePeService
{
    private ?StandardCheckoutClient $client = null;

    public function __construct()
    {
        // Keep the app bootable in local/dev environments where PhonePe SDK is not installed.
        if (! class_exists(StandardCheckoutClient::class)) {
            return;
        }

        $this->client = StandardCheckoutClient::getInstance(
            config('services.phonepe.client_id'),
            config('services.phonepe.client_version'),
            config('services.phonepe.client_secret'),
            Env::PRODUCTION
        );
    }

    /**
     * Initiate a payment — returns the PhonePe redirect URL.
     */
    public function initiatePayment(
        string $merchantOrderId,
        int    $amountPaise,
        string $redirectUrl
    ): array {
        $client = $this->client();

        $payRequest = StandardCheckoutPayRequestBuilder::builder()
            ->merchantOrderId($merchantOrderId)
            ->amount($amountPaise)
            ->redirectUrl($redirectUrl)
            ->build();

        $response = $client->pay($payRequest);

        return [
            'state'            => $response->getState(),
            'redirect_url'     => $response->getRedirectUrl(),
            'phonepe_order_id' => $response->getOrderId(),
            'expire_at'        => $response->getExpireAt(),
        ];
    }

    /**
     * Check order status after user returns from PhonePe.
     */
    public function getOrderStatus(string $merchantOrderId): array
    {
        $response = $this->client()->getOrderStatus($merchantOrderId);

        return [
            'state'             => $response->getState(),
            'merchant_order_id' => $response->getMerchantOrderId(),
            'phonepe_order_id'  => $response->getOrderId(),
            'amount'            => $response->getAmount(),
            'raw'               => $response,
        ];
    }

    /**
     * Initiate a refund.
     */
    public function initiateRefund(
        string $refundMerchantOrderId,
        string $originalMerchantOrderId,
        int    $amountPaise
    ): array {
        $response = $this->client()->refund(
            $refundMerchantOrderId,
            $originalMerchantOrderId,
            $amountPaise
        );

        return [
            'state'    => $response->getState(),
            'order_id' => $response->getOrderId(),
        ];
    }

    private function client(): StandardCheckoutClient
    {
        if (! $this->client) {
            throw new RuntimeException('PhonePe SDK is not available. Install phonepe/pg-php-sdk-v2 before using payment actions.');
        }

        return $this->client;
    }
}
