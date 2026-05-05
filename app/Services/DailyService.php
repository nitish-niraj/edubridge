<?php

namespace App\Services;

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
     */
    public function ensureRoom(string $roomName, int $expiryMinutes = 120): string
    {
        $this->validateConfig();

        // Check if room exists
        $response = Http::withToken($this->apiKey)
            ->get("{$this->baseUrl}/rooms/{$roomName}");

        if ($response->successful()) {
            return $response->json('url');
        }

        // Create room if not exists
        $createResponse = Http::withToken($this->apiKey)
            ->post("{$this->baseUrl}/rooms", [
                'name' => $roomName,
                'properties' => [
                    'exp' => $this->expiryTimestamp($expiryMinutes),
                    'enable_recording' => 'cloud',
                    'enable_screenshare' => true,
                    'start_video_off' => false,
                    'start_audio_off' => false,
                ]
            ]);

        if ($createResponse->successful()) {
            return $createResponse->json('url');
        }

        throw new RuntimeException('Failed to create Daily room: ' . $createResponse->body());
    }

    /**
     * Generate a meeting token for a specific room and identity.
     */
    public function generateMeetingToken(string $roomName, string $identity, bool $isOwner = false): string
    {
        $this->validateConfig();

        $response = Http::withToken($this->apiKey)
            ->post("{$this->baseUrl}/meeting-tokens", [
                'properties' => [
                    'room_name' => $roomName,
                    'user_name' => $identity,
                    'is_owner' => $isOwner,
                    'enable_recording' => 'cloud',
                ]
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

    private function expiryTimestamp(int $expiryMinutes): int
    {
        return max(now()->addMinutes($expiryMinutes)->timestamp, time() + ($expiryMinutes * 60));
    }
}
