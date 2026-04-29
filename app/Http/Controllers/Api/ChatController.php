<?php

namespace App\Http\Controllers\Api;

use App\Helpers\UploadSecurity;
use App\Http\Controllers\Controller;
use App\Events\MessageSent;
use App\Events\UserTyping;
use App\Http\Requests\Api\ConversationIndexRequest;
use App\Http\Requests\Api\ConversationMessagesRequest;
use App\Http\Requests\Api\MarkConversationReadRequest;
use App\Http\Requests\Api\SendMessageRequest;
use App\Http\Requests\Api\StartConversationRequest;
use App\Http\Requests\Api\TypingRequest;
use App\Http\Resources\ConversationResource;
use App\Http\Resources\MessageResource;
use App\Jobs\SendChatNotification;
use App\Models\ClassMember;
use App\Models\Conversation;
use App\Models\Message;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpKernel\Exception\HttpException;

class ChatController extends Controller
{
    public function index(ConversationIndexRequest $request): AnonymousResourceCollection
    {
        $validated = $request->validated();
        $perPage = (int) ($validated['per_page'] ?? 20);
        $userId = $request->user()->id;

        $conversations = Conversation::query()
            ->whereHas('participants', function ($query) use ($userId): void {
                $query->where('users.id', $userId);
            })
            ->with([
                'participants:id,name,avatar,role',
                'lastMessage.sender:id,name,avatar',
            ])
            ->withCount([
                'messages as unread_count' => function ($query) use ($userId): void {
                    $query->whereNull('read_at')
                        ->where('sender_id', '!=', $userId);
                },
            ])
            ->withMax('messages', 'created_at')
            ->orderByDesc('messages_max_created_at')
            ->orderByDesc('updated_at')
            ->paginate($perPage)
            ->withQueryString();

        return ConversationResource::collection($conversations);
    }

    public function store(StartConversationRequest $request): ConversationResource
    {
        $validated = $request->validated();
        $studentId = $request->user()->id;
        $teacherId = (int) $validated['teacher_id'];

        [$conversation, $message] = DB::transaction(function () use ($studentId, $teacherId, $validated): array {
            $existing = Conversation::query()
                ->where('is_group', false)
                ->where('direct_student_id', $studentId)
                ->where('teacher_id', $teacherId)
                ->first();

            if (! $existing) {
                $existing = Conversation::query()
                    ->where('is_group', false)
                    ->whereHas('participants', function ($query) use ($studentId): void {
                        $query->where('users.id', $studentId);
                    })
                    ->whereHas('participants', function ($query) use ($teacherId): void {
                        $query->where('users.id', $teacherId);
                    })
                    ->has('participants', '=', 2)
                    ->first();
            }

            if (! $existing) {
                $existing = Conversation::query()->firstOrCreate(
                    [
                        'direct_student_id' => $studentId,
                        'teacher_id' => $teacherId,
                        'is_group' => false,
                    ],
                    [
                        'created_by' => $studentId,
                    ]
                );
            } elseif (! $existing->direct_student_id || ! $existing->teacher_id) {
                $existing->forceFill([
                    'direct_student_id' => $studentId,
                    'teacher_id' => $teacherId,
                ])->save();
            }

            $existing->participants()->syncWithoutDetaching([
                $studentId => ['joined_at' => now()],
                $teacherId => ['joined_at' => now()],
            ]);

            $message = $existing->messages()->create([
                'sender_id' => $studentId,
                'body' => $validated['message'],
                'type' => 'text',
            ]);

            return [$existing, $message];
        });

        $message->load('sender:id,name,avatar');
        broadcast(new MessageSent($message))->toOthers();
        SendChatNotification::dispatch($message->id);

        $conversation->load([
            'participants:id,name,avatar,role',
            'lastMessage.sender:id,name,avatar',
        ])->loadCount([
            'messages as unread_count' => function ($query) use ($studentId): void {
                $query->whereNull('read_at')
                    ->where('sender_id', '!=', $studentId);
            },
        ]);

        return new ConversationResource($conversation);
    }

    public function messages(
        ConversationMessagesRequest $request,
        Conversation $conversation
    ): AnonymousResourceCollection {
        $request->validated();
        $userId = $request->user()->id;
        $this->assertParticipant($conversation, $userId);

        $query = Message::query()
            ->where('conversation_id', $conversation->id)
            ->with('sender:id,name,avatar');

        // For group chats: filter out muted student messages for non-teacher users
        if ($conversation->is_group && $conversation->teacher_id !== $userId) {
            $mutedIds = ClassMember::where('conversation_id', $conversation->id)
                ->where('is_muted', true)
                ->pluck('user_id')
                ->toArray();

            if (! empty($mutedIds)) {
                $query->whereNotIn('sender_id', $mutedIds);
            }
        }

        $messages = $query->orderByDesc('id')->cursorPaginate(20);

        return MessageResource::collection($messages);
    }

    public function send(
        SendMessageRequest $request,
        Conversation $conversation
    ): MessageResource {
        $validated = $request->validated();
        $this->assertParticipant($conversation, $request->user()->id);

        $fileUrl = null;
        if ($request->hasFile('attachment')) {
            $allowedMimes = $validated['type'] === 'image'
                ? ['image/jpeg', 'image/png', 'image/webp']
                : [
                    'image/jpeg',
                    'image/png',
                    'image/webp',
                    'application/pdf',
                    'text/plain',
                    'application/msword',
                    'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
                ];

            $path = UploadSecurity::storeValidatedFile(
                $request->file('attachment'),
                'public',
                'chat-files',
                'attachment',
                $allowedMimes
            );
            $fileUrl = '/storage/' . $path;
        }

        $message = $conversation->messages()->create([
            'sender_id' => $request->user()->id,
            'body' => $validated['body'] ?? null,
            'type' => $validated['type'],
            'file_url' => $fileUrl,
        ]);

        $message->load('sender:id,name,avatar');
        broadcast(new MessageSent($message))->toOthers();
        SendChatNotification::dispatch($message->id);

        return new MessageResource($message);
    }

    public function markRead(
        MarkConversationReadRequest $request,
        Conversation $conversation
    ): JsonResponse {
        $request->validated();
        $userId = $request->user()->id;
        $this->assertParticipant($conversation, $userId);

        $updatedCount = Message::query()
            ->where('conversation_id', $conversation->id)
            ->where('sender_id', '!=', $userId)
            ->whereNull('read_at')
            ->update(['read_at' => now()]);

        return response()->json([
            'updated' => $updatedCount,
            'message' => 'Conversation marked as read.',
        ]);
    }

    public function typing(TypingRequest $request, Conversation $conversation): JsonResponse
    {
        $request->validated();
        $user = $request->user();
        $this->assertParticipant($conversation, $user->id);

        broadcast(new UserTyping(
            conversationId: $conversation->id,
            userId: $user->id,
            name: $user->name
        ))->toOthers();

        return response()->json([
            'message' => 'Typing event broadcast.',
        ]);
    }

    /**
     * Post an announcement (teacher only, group only).
     */
    public function announcement(Request $request, Conversation $conversation): MessageResource
    {
        $request->validate(['body' => 'required|string|max:2000']);
        $userId = $request->user()->id;
        $this->assertParticipant($conversation, $userId);

        if (! $conversation->is_group || $conversation->teacher_id !== $userId) {
            throw new HttpException(403, 'Only the class teacher can post announcements.');
        }

        $message = $conversation->messages()->create([
            'sender_id' => $userId,
            'body'      => $request->input('body'),
            'type'      => 'announcement',
        ]);

        $message->load('sender:id,name,avatar');
        broadcast(new MessageSent($message))->toOthers();
        SendChatNotification::dispatch($message->id);

        return new MessageResource($message);
    }

    /**
     * Get the pinned announcement for a group conversation.
     */
    public function pinnedAnnouncement(Request $request, Conversation $conversation): JsonResponse
    {
        $this->assertParticipant($conversation, $request->user()->id);

        if (! $conversation->is_group) {
            return response()->json(null);
        }

        $announcement = $conversation->pinnedAnnouncement()?->with('sender:id,name,avatar')->first();

        return response()->json($announcement);
    }

    /**
     * Delete a message (teacher only in groups, soft delete).
     */
    public function deleteMessage(Request $request, Conversation $conversation, int $messageId): JsonResponse
    {
        $userId = $request->user()->id;
        $this->assertParticipant($conversation, $userId);

        $message = Message::where('conversation_id', $conversation->id)->findOrFail($messageId);

        // Only teacher of a group or the message sender can delete
        $isTeacher = $conversation->is_group && $conversation->teacher_id === $userId;
        $isSender = $message->sender_id === $userId;

        if (! $isTeacher && ! $isSender) {
            throw new HttpException(403, 'You cannot delete this message.');
        }

        $message->delete(); // soft delete

        return response()->json(['message' => 'Message deleted.']);
    }

    private function assertParticipant(Conversation $conversation, int $userId): void
    {
        $isParticipant = $conversation->participants()
            ->where('users.id', $userId)
            ->exists();

        if (! $isParticipant) {
            throw new HttpException(403, 'You are not a participant in this conversation.');
        }
    }
}
