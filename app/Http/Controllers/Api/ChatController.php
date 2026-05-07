<?php

namespace App\Http\Controllers\Api;

use App\Helpers\UploadSecurity;
use App\Http\Controllers\Controller;
use App\Events\MessageSent;
use App\Events\UserTyping;
use App\Http\Requests\Api\ConversationIndexRequest;
use App\Http\Requests\Api\ConversationMessagesRequest;
use App\Http\Requests\Api\AnnouncementStoreRequest;
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
use Illuminate\Support\Facades\Cache;
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
            ->where(function ($query) use ($userId): void {
                $query->where(function ($directQuery) use ($userId): void {
                    $directQuery->where('is_group', false)
                        ->whereHas('participants', function ($participantQuery) use ($userId): void {
                            $participantQuery->where('users.id', $userId)
                                ->whereNull('conversation_participants.left_at');
                        });
                })->orWhere(function ($groupQuery) use ($userId): void {
                    $groupQuery->where('is_group', true)
                        ->whereHas('activeClassMembers', function ($memberQuery) use ($userId): void {
                            $memberQuery->where('user_id', $userId);
                        });
                });
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
        $mutedIds = [];

        $query = Message::query()
            ->where('conversation_id', $conversation->id)
            ->with([
                'sender:id,name,avatar',
                'conversation:id,is_group,teacher_id',
            ]);

        // For group chats: filter out muted student messages for non-teacher users
        if ($conversation->is_group) {
            $mutedIds = ClassMember::where('conversation_id', $conversation->id)
                ->where('is_muted', true)
                ->pluck('user_id')
                ->map(fn ($id): int => (int) $id)
                ->all();

            if ($conversation->teacher_id !== $userId && ! empty($mutedIds)) {
                $query->whereNotIn('sender_id', $mutedIds);
            }
        }

        $messages = $query->orderByDesc('id')->cursorPaginate(20);
        $mutedLookup = array_flip($mutedIds);

        $messages->setCollection(
            $messages->getCollection()->map(function (Message $message) use ($conversation, $mutedLookup, $userId): Message {
                $isTeacher = $conversation->is_group && (int) $conversation->teacher_id === (int) $message->sender_id;
                $isMuted = isset($mutedLookup[(int) $message->sender_id]);
                $isTeacherViewer = $conversation->is_group && $userId === (int) $conversation->teacher_id;

                $message->setAttribute('is_teacher', $isTeacher);
                $message->setAttribute('is_muted', $isMuted);
                $message->setAttribute('muted_label', $isMuted && $isTeacherViewer ? '[MUTED]' : null);

                return $message;
            })
        );

        return MessageResource::collection($messages);
    }

    public function send(
        SendMessageRequest $request,
        Conversation $conversation
    ): MessageResource {
        $validated = $request->validated();
        $user = $request->user();
        $this->assertParticipant($conversation, $user->id);

        // Rule 5.2: Only students can initiate 1:1, but teachers can reply to existing ones.
        // The initiation is handled in store(), this is for ongoing conversations.
        if (! $conversation->is_group && $user->isTeacher() && $conversation->messages()->count() === 0) {
            throw new HttpException(403, 'Teachers cannot initiate 1:1 conversations.');
        }

        $fileUrl = null;
        if ($request->hasFile('attachment')) {
            $allowedMimes = $validated['type'] === 'image'
                ? ['image/jpeg', 'image/png', 'image/webp']
                : ['application/pdf']; // Rule 5.3: PDF only for 'file' type

            $maxBytes = $validated['type'] === 'image'
                ? (5 * 1024 * 1024)
                : (10 * 1024 * 1024);

            $path = UploadSecurity::storeValidatedFile(
                $request->file('attachment'),
                'public',
                'chat-files',
                'attachment',
                $allowedMimes,
                $maxBytes
            );
            $fileUrl = '/storage/' . $path;
        }

        $message = $conversation->messages()->create([
            'sender_id' => $user->id,
            'body' => $validated['body'] ?? null,
            'type' => $validated['type'],
            'file_url' => $fileUrl,
        ]);

        $message->load('sender:id,name,avatar');

        if (! $this->isMutedGroupStudentMessage($conversation, $request->user()->id)) {
            broadcast(new MessageSent($message))->toOthers();
            SendChatNotification::dispatch($message->id);
        }

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
        $throttleKey = "typing:{$conversation->id}:{$user->id}";

        if (! Cache::add($throttleKey, now()->timestamp, now()->addSeconds(2))) {
            return response()->json([
                'message' => 'Typing event throttled.',
            ]);
        }

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
    public function announcement(AnnouncementStoreRequest $request, Conversation $conversation): MessageResource
    {
        $validated = $request->validated();
        $userId = $request->user()->id;
        $this->assertParticipant($conversation, $userId);

        if (! $conversation->is_group || $conversation->teacher_id !== $userId) {
            throw new HttpException(403, 'Only the class teacher can post announcements.');
        }

        $message = $conversation->messages()->create([
            'sender_id' => $userId,
            'body'      => $validated['body'],
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
     * Delete a message (soft delete).
     * Rule 5.2: Individual messages can be deleted by sender within 10 minutes.
     * Rule 5.7: Teacher can delete any message in group chat.
     */
    public function deleteMessage(Request $request, Conversation $conversation, int $messageId): JsonResponse
    {
        $userId = $request->user()->id;
        $this->assertParticipant($conversation, $userId);

        $message = Message::where('conversation_id', $conversation->id)->findOrFail($messageId);

        // Only teacher of a group or the message sender can delete.
        $isTeacher = $conversation->is_group && $conversation->teacher_id === $userId;
        $isSender = $message->sender_id === $userId;

        if (! $isTeacher && ! $isSender) {
            throw new HttpException(403, 'You cannot delete this message.');
        }

        // Rule 5.2: 10 minute limit for non-teachers (applies to 1:1 and students in groups)
        if (! $isTeacher && $message->created_at->lt(now()->subMinutes(10))) {
            throw new HttpException(403, 'Messages can only be deleted within 10 minutes of sending.');
        }

        $message->delete(); // soft delete

        return response()->json(['message' => 'Message deleted.']);
    }

    private function assertParticipant(Conversation $conversation, int $userId): void
    {
        if ($conversation->is_group) {
            $isMember = ClassMember::query()
                ->where('conversation_id', $conversation->id)
                ->where('user_id', $userId)
                ->whereNull('left_at')
                ->exists();

            if (! $isMember) {
                throw new HttpException(403, 'You are not a participant in this conversation.');
            }

            return;
        }

        $isParticipant = $conversation->participants()
            ->where('users.id', $userId)
            ->wherePivotNull('left_at')
            ->exists();

        if (! $isParticipant) {
            throw new HttpException(403, 'You are not a participant in this conversation.');
        }
    }

    private function isMutedGroupStudentMessage(Conversation $conversation, int $userId): bool
    {
        if (! $conversation->is_group || $conversation->teacher_id === $userId) {
            return false;
        }

        return ClassMember::query()
            ->where('conversation_id', $conversation->id)
            ->where('user_id', $userId)
            ->where('role', 'student')
            ->where('is_muted', true)
            ->whereNull('left_at')
            ->exists();
    }
}
