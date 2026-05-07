<?php

namespace App\Services;

use Carbon\Carbon;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class DailyService
{
    protected string $apiKey;
    protected string $baseUrl = 'https://api.daily.co/v1';

    public function __construct()
    {
        $this->apiKey = (string) config('services.daily.api_key', '');
    }

    /**
     * Ensure a Daily room exists. Returns the room URL.
     *
     * @param  string       $roomName
     * @param  Carbon|null  $expiresAt  Exact UTC expiry for the room (defaults to now + 2 hours).
     * @return string  The Daily room URL.
     */
    public function ensureRoom(string $roomName, ?Carbon $expiresAt = null): string
    {
        $this->validateConfig();

        $exp = $this->resolveExpiry($expiresAt);

        // Check if room already exists
        $response = $this->client()->get("{$this->baseUrl}/rooms/{$roomName}");

        if ($response->successful()) {
            // Update room expiry to match the booking's end time, in case it
            // was created previously with a different (or default) expiry.
            $this->client()->post("{$this->baseUrl}/rooms/{$roomName}", [
                'properties' => ['exp' => $exp],
            ]);

            return $response->json('url');
        }

        // Create room if it does not yet exist
        $createResponse = $this->client()->post("{$this->baseUrl}/rooms", [
            'name'       => $roomName,
            'properties' => [
                'exp' => $exp,
            ],
        ]);

        if ($createResponse->successful()) {
            return $createResponse->json('url');
        }

        throw new RuntimeException('Failed to create Daily room: ' . $createResponse->body());
    }

    /**
     * Generate a meeting token for a specific room and identity.
     *
     * @param  string       $roomName
     * @param  string       $identity
     * @param  bool         $isOwner
     * @param  Carbon|null  $expiresAt  Token expiry (defaults to now + 2 hours).
     * @return string  The meeting token.
     */
    public function generateMeetingToken(
        string $roomName,
        string $identity,
        bool $isOwner = false,
        ?Carbon $expiresAt = null
    ): string {
        $this->validateConfig();

        $tokenProperties = [
            'room_name' => $roomName,
            'user_name' => $identity,
            'is_owner'  => $isOwner,
            'exp'       => $this->resolveExpiry($expiresAt),
        ];

        $response = $this->client()->post("{$this->baseUrl}/meeting-tokens", [
            'properties' => $tokenProperties,
        ]);

        if ($response->successful()) {
            return $response->json('token');
        }

        throw new RuntimeException('Failed to generate Daily meeting token: ' . $response->body());
    }

    protected function validateConfig(): void
    {
        if (empty($this->apiKey)) {
            throw new RuntimeException('Daily.co API Key is missing. Please set DAILY_API_KEY in your .env file.');
        }
    }

    /**
     * Build a pre-configured HTTP client with the Daily API token.
     * SSL verification is disabled on local environments (XAMPP on Windows
     * ships without a CA bundle, causing cURL error 60).
     */
    protected function client()
    {
        $http = Http::withToken($this->apiKey)
            ->timeout(15)
            ->acceptJson();

        if (app()->environment('local')) {
            $http = $http->withoutVerifying();
        }

        return $http;
    }

    /**
     * Resolve a Carbon expiry to a Unix timestamp.
     * Falls back to now() + 2 hours when no expiry is provided.
     */
    private function resolveExpiry(?Carbon $expiresAt): int
    {
        return $expiresAt
            ? $expiresAt->utc()->timestamp
            : now()->utc()->addHours(2)->timestamp;
    }
}
