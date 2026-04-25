<?php

namespace App\Jobs;

use App\Mail\NewMessageMail;
use App\Models\Message;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Pusher\Pusher;
use Throwable;

class SendChatNotification implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public function __construct(public int $messageId) {}

    public function handle(): void
    {
        $message = Message::query()
            ->with([
                'sender:id,name,avatar',
                'conversation.participants:id,name,email',
            ])
            ->find($this->messageId);

        if (! $message) {
            return;
        }

        $onlineUserIds = $this->fetchPresenceUserIds($message->conversation_id);

        foreach ($message->conversation->participants as $participant) {
            if ((int) $participant->id === (int) $message->sender_id) {
                continue;
            }

            if (in_array((int) $participant->id, $onlineUserIds, true)) {
                continue;
            }

            if (! $participant->email) {
                continue;
            }

            Mail::to($participant->email)->send(new NewMessageMail(
                senderName: $message->sender?->name ?? 'Teacher',
                senderAvatar: $message->sender?->avatar,
                preview: $this->buildPreview($message->body, $message->type),
                conversationId: $message->conversation_id
            ));
        }
    }

    /**
     * @return array<int, int>
     */
    private function fetchPresenceUserIds(int $conversationId): array
    {
        if (config('broadcasting.default') !== 'pusher') {
            return [];
        }

        $key = config('broadcasting.connections.pusher.key');
        $secret = config('broadcasting.connections.pusher.secret');
        $appId = config('broadcasting.connections.pusher.app_id');

        if (! $key || ! $secret || ! $appId) {
            return [];
        }

        try {
            $pusher = new Pusher(
                $key,
                $secret,
                $appId,
                config('broadcasting.connections.pusher.options', [])
            );

            $channel = "presence-conversation.{$conversationId}";
            $response = $pusher->get("/channels/{$channel}/users");
            $payload = $this->normalizePusherResponse($response);

            $users = $payload['users'] ?? [];
            if (! is_array($users)) {
                return [];
            }

            return collect($users)
                ->pluck('id')
                ->map(fn ($id): int => (int) $id)
                ->filter(fn (int $id): bool => $id > 0)
                ->values()
                ->all();
        } catch (Throwable $exception) {
            Log::warning('Unable to fetch presence users for chat notification.', [
                'conversation_id' => $conversationId,
                'error' => $exception->getMessage(),
            ]);

            return [];
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function normalizePusherResponse(mixed $response): array
    {
        if (is_array($response)) {
            return $response;
        }

        if (is_object($response) && method_exists($response, 'getBody')) {
            $decoded = json_decode((string) $response->getBody(), true);

            return is_array($decoded) ? $decoded : [];
        }

        return [];
    }

    private function buildPreview(?string $body, string $type): string
    {
        if ($body) {
            return mb_substr($body, 0, 50);
        }

        return match ($type) {
            'image' => '[Image]',
            'file' => '[File]',
            default => '[Message]',
        };
    }
}
