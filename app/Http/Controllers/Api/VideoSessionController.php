<?php

namespace App\Http\Controllers\Api;

use App\Events\GroupSessionStarted;
use App\Events\GroupSessionEnded;
use App\Events\GroupHandRaised;
use App\Events\RecordingConsentRequest;
use App\Events\WhiteboardUpdate;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\RecordingConsentRequest as RecordingConsentFormRequest;
use App\Http\Requests\Api\WhiteboardSyncRequest;
use App\Models\Booking;
use App\Models\ClassMember;
use App\Models\Conversation;
use App\Models\User;
use App\Models\VideoSession;
use App\Services\NotificationService;
use Firebase\JWT\JWT;
use Firebase\JWT\Key;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Sentry\Breadcrumb;

class VideoSessionController extends Controller
{
    /**
     * POST /api/video-sessions/{bookingId}/token
     * Works for both 1:1 bookings and group sessions.
     */
    public function token(int $bookingId): JsonResponse
    {
        $booking = Booking::findOrFail($bookingId);
        $user    = auth()->user();

        if ($user->id !== $booking->student_id && $user->id !== $booking->teacher_id) {
            return $this->unauthorizedResponse();
        }

        if ($booking->status !== 'confirmed') {
            return $this->unconfirmedResponse($booking);
        }

        if ($windowResponse = $this->joinWindowErrorResponse($booking)) {
            return $windowResponse;
        }

        // Ensure a VideoSession row exists and carries a stable jitsi_room_token.
        // The token is generated once and reused so both teacher and student
        // always land in the exact same Jitsi room.
        $videoSession = VideoSession::firstOrCreate(
            ['booking_id' => $booking->id],
            [
                'room_name'        => $this->roomName($bookingId),
                'room_type'        => 'peer-to-peer',
                'is_group'         => false,
                'host_id'          => $booking->teacher_id,
                'jitsi_room_token' => \Illuminate\Support\Str::random(12),
            ]
        );

        // Build a URL-safe Jitsi room name unique to this booking.
        $jitsiRoom = 'EduBridge-' . $bookingId . '-' . $videoSession->jitsi_room_token;
        $legacyRoom = $this->roomName($bookingId);

        $identity = ($user->id === $booking->student_id ? 'student-' : 'teacher-') . $user->id;
        $isOwner  = $user->id === $booking->teacher_id;

        $this->addSentryBreadcrumb('video.token.generated', [
            'booking_id' => $bookingId,
            'user_id'    => $user->id,
            'identity'   => $identity,
            'provider'   => 'jitsi',
        ]);

        $jwt = $this->generateJitsiJwt($user, $isOwner, $jitsiRoom);

        return response()->json([
            'provider'        => 'jitsi',
            'room_name'       => $legacyRoom,
            'jaas_room_name'  => $jitsiRoom,
            'identity'        => $identity,
            'display_name'    => $user->name,
            'is_owner'        => $isOwner,
            'too_early'       => false,
            'session_expired' => false,
            'jwt'             => $jwt,
            'token'           => $jwt,
        ]);
    }

    /**
     * POST /api/video-sessions/group/{conversationId}/start
     * Teacher starts a group video session.
     */
    public function startGroupSession(int $conversationId, Request $request): JsonResponse
    {
        $user = $request->user();
        $conversation = Conversation::findOrFail($conversationId);

        if (! $conversation->is_group || $conversation->teacher_id !== $user->id) {
            return response()->json(['message' => 'Only the class teacher can start a group session.'], 403);
        }

        if (! (bool) $user->teacherProfile?->is_verified) {
            return response()->json(['message' => 'Only verified teachers can start group sessions.'], 403);
        }

        if ($conversation->activeClassMembers()->where('role', 'student')->count() > 30) {
            return response()->json(['message' => 'Group sessions support a maximum of 30 students.'], 422);
        }

        // Close stale sessions before starting/joining
        VideoSession::where('is_group', true)
            ->whereNull('ended_at')
            ->where('updated_at', '<', now()->subMinutes(10))
            ->update([
                'ended_at' => now(),
                'duration_minutes' => \Illuminate\Support\Facades\DB::raw('TIMESTAMPDIFF(MINUTE, started_at, updated_at)')
            ]);

        // Find or create video session
        $videoSession = VideoSession::query()
            ->where('conversation_id', $conversation->id)
            ->where('is_group', true)
            ->whereNull('ended_at')
            ->latest()
            ->first();

        if (! $videoSession) {
            $videoSession = VideoSession::create([
                'booking_id'      => null,
                'conversation_id' => $conversation->id,
                'is_group'       => true,
                'host_id'        => $user->id,
                'room_name'      => $this->groupRoomName($conversationId),
                'room_type'      => 'group',
                'started_at'     => now(),
                'jitsi_room_token' => \Illuminate\Support\Str::random(12),
            ]);
        }

        $identity = 'teacher-' . $user->id;
        $isOwner = true;

        $this->addSentryBreadcrumb('video.group.session.started', [
            'conversation_id' => $conversationId,
            'user_id'         => $user->id,
            'session_id'      => $videoSession->id,
            'provider'        => 'jitsi',
        ]);

        $jwt = $this->generateJitsiJwt($user, $isOwner, $videoSession->room_name);
        $videoSession->touch();

        // Broadcast to all group members
        broadcast(new GroupSessionStarted(
            $conversationId,
            $user->name,
            $videoSession->id,
            $videoSession->room_name
        ));

        $memberIds = ClassMember::query()
            ->where('conversation_id', $conversationId)
            ->whereNull('left_at')
            ->where('user_id', '!=', $user->id)
            ->pluck('user_id');

        if ($memberIds->isNotEmpty()) {
            $recipients = User::query()->whereIn('id', $memberIds)->get();
            $notifications = app(NotificationService::class);
            foreach ($recipients as $recipient) {
                $notifications->sendGroupSessionStarted($recipient, $user->name, $conversationId);
            }
        }

        return response()->json([
            'provider'         => 'jitsi',
            'jwt'              => $jwt,
            'token'            => $jwt,
            'room_name'        => $videoSession->room_name,
            'identity'         => $identity,
            'display_name'     => $user->name,
            'is_owner'         => $isOwner,
            'video_session_id' => $videoSession->id,
        ]);
    }

    public function startGroupSessionFromGroup(int $groupId, Request $request): JsonResponse
    {
        return $this->startGroupSession($groupId, $request);
    }

    /**
     * POST /api/video-sessions/group/{conversationId}/join
     * Student joins a group video session.
     */
    public function joinGroupSession(int $conversationId, Request $request): JsonResponse
    {
        $user = $request->user();

        // Check membership
        $member = ClassMember::where('conversation_id', $conversationId)
            ->where('user_id', $user->id)
            ->whereNull('left_at')
            ->first();

        if (! $member) {
            return response()->json(['message' => 'You are not a member of this class.'], 403);
        }

        if ($member->role === 'student' && ! $user->isStudent()) {
            return response()->json(['message' => 'Only student members can join as students.'], 403);
        }

        if ($member->role === 'teacher' && ! (bool) $user->teacherProfile?->is_verified) {
            return response()->json(['message' => 'Only verified teacher members can join as teachers.'], 403);
        }

        // Close stale sessions
        VideoSession::where('is_group', true)
            ->whereNull('ended_at')
            ->where('updated_at', '<', now()->subMinutes(10))
            ->get()
            ->each(function ($session) {
                $session->update([
                    'ended_at' => now(),
                    'duration_minutes' => $session->started_at ? $session->started_at->diffInMinutes($session->updated_at) : 0
                ]);
            });

        // Find the active group session
        $videoSession = VideoSession::where('conversation_id', $conversationId)
            ->where('is_group', true)
            ->whereNotNull('started_at')
            ->whereNull('ended_at')
            ->latest()
            ->first();

        if (! $videoSession) {
            $endedSessionExists = VideoSession::where('conversation_id', $conversationId)
                ->where('is_group', true)
                ->whereNotNull('ended_at')
                ->exists();

            if ($endedSessionExists) {
                return response()->json(['message' => 'Session has ended.'], 410);
            }

            return response()->json(['message' => 'No active session right now.'], 404);
        }

        $activeCount = ClassMember::where('conversation_id', $conversationId)
            ->whereNull('left_at')
            ->count();
        if ($activeCount > 31) {
            return response()->json(['message' => 'Session is full. Please contact your teacher.'], 422);
        }

        $isOwner = (int) $videoSession->host_id === (int) $user->id;
        $identity = ($isOwner ? 'teacher-' : 'student-') . $user->id;

        $this->addSentryBreadcrumb('video.group.session.joined', [
            'conversation_id' => $conversationId,
            'user_id'         => $user->id,
            'session_id'      => $videoSession->id,
            'provider'        => 'jitsi',
        ]);

        $jwt = $this->generateJitsiJwt($user, $isOwner, $videoSession->room_name);
        $videoSession->touch();

        return response()->json([
            'provider'         => 'jitsi',
            'jwt'              => $jwt,
            'token'            => $jwt,
            'room_name'        => $videoSession->room_name,
            'identity'         => $identity,
            'display_name'     => $user->name,
            'is_owner'         => $isOwner,
            'video_session_id' => $videoSession->id,
        ]);
    }

    public function groupToken(int $groupId, Request $request): JsonResponse
    {
        return $this->joinGroupSession($groupId, $request);
    }

    public function raiseHand(int $conversationId, Request $request): JsonResponse
    {
        $user = $request->user();
        $validated = $request->validate([
            'raised' => ['required', 'boolean'],
        ]);

        $member = ClassMember::query()
            ->where('conversation_id', $conversationId)
            ->where('user_id', $user->id)
            ->whereNull('left_at')
            ->first();

        if (! $member) {
            return response()->json(['message' => 'You are not a member of this class.'], 403);
        }

        if ($member->role !== 'student') {
            return response()->json(['message' => 'Only students can raise a hand.'], 403);
        }

        broadcast(new GroupHandRaised(
            conversationId: $conversationId,
            userId: $user->id,
            name: $user->name,
            raised: (bool) $validated['raised']
        ))->toOthers();

        return response()->json([
            'raised' => (bool) $validated['raised'],
            'message' => $validated['raised'] ? 'Hand raised.' : 'Hand lowered.',
        ]);
    }

    /**
     * PATCH /api/video-sessions/{bookingId}/start
     */
    public function start(int $bookingId): JsonResponse
    {
        $booking = Booking::findOrFail($bookingId);
        $user    = auth()->user();

        if ($user->id !== $booking->student_id && $user->id !== $booking->teacher_id) {
            return $this->unauthorizedResponse();
        }

        if ($booking->status !== 'confirmed') {
            return $this->unconfirmedResponse($booking);
        }

        if ($windowResponse = $this->joinWindowErrorResponse($booking)) {
            return $windowResponse;
        }

        $videoSession = $this->ensureVideoSession($booking, $this->roomName($bookingId));

        if (! $videoSession->started_at) {
            $videoSession->update(['started_at' => now()]);
            $videoSession->refresh();
        }

        $this->addSentryBreadcrumb('video.session.started', [
            'booking_id' => $bookingId,
            'user_id' => $user->id,
            'session_id' => $videoSession->id,
        ]);

        return response()->json(['message' => 'Session started.', 'started_at' => $videoSession->started_at]);
    }

    /**
     * PATCH /api/video-sessions/{bookingId}/end
     */
    public function end(int $bookingId): JsonResponse
    {
        $booking = Booking::findOrFail($bookingId);
        $user    = auth()->user();

        if ($user->id !== $booking->teacher_id) {
            return $this->unauthorizedResponse('Only the teacher can end the session.');
        }

        $videoSession = VideoSession::where('booking_id', $bookingId)->first();

        if ($videoSession?->ended_at) {
            $videoSession = $this->normalizeVideoSession($videoSession, $booking, $this->roomName($bookingId));

            return response()->json([
                'message'          => 'Session ended.',
                'duration_minutes' => $videoSession->duration_minutes,
                'booking_status'   => $booking->status,
            ]);
        }

        if ($booking->status !== 'confirmed') {
            return $this->unconfirmedResponse($booking);
        }

        $videoSession = $videoSession
            ? $this->normalizeVideoSession($videoSession, $booking, $this->roomName($bookingId))
            : $this->ensureVideoSession($booking, $this->roomName($bookingId));

        $endedAt = now();
        $duration = $videoSession->started_at
            ? (int) $videoSession->started_at->diffInMinutes($endedAt)
            : 0;

        $videoSession->update([
            'ended_at'         => $endedAt,
            'duration_minutes' => $duration,
        ]);

        $booking->transitionTo($duration >= 5 ? Booking::STATUS_COMPLETED : Booking::STATUS_NO_SHOW);

        $videoSession->refresh();
        $booking->refresh();

        return response()->json([
            'message'          => 'Session ended.',
            'duration_minutes' => $videoSession->duration_minutes,
            'booking_status'   => $booking->status,
        ]);
    }

    /**
     * PATCH /api/video-sessions/group/{sessionId}/end
     * End a group session (teacher only).
     */
    public function endGroupSession(int $sessionId, Request $request): JsonResponse
    {
        $user = $request->user();
        $videoSession = VideoSession::findOrFail($sessionId);

        $conversationId = (int) $videoSession->conversation_id;
        if (! $conversationId) {
            preg_match('/edubridge-group-(\d+)-/', $videoSession->room_name, $matches);
            $conversationId = (int) ($matches[1] ?? 0);
        }

        $conversation = Conversation::find($conversationId);
        if (! $conversation || $conversation->teacher_id !== $user->id) {
            return response()->json(['message' => 'Unauthorized.'], 403);
        }

        $videoSession->update([
            'ended_at'         => now(),
            'duration_minutes' => $videoSession->started_at
                ? (int) $videoSession->started_at->diffInMinutes(now())
                : 0,
        ]);

        broadcast(new GroupSessionEnded($conversation->id, $videoSession->id));

        return response()->json([
            'message'          => 'Group session ended.',
            'duration_minutes' => $videoSession->duration_minutes,
        ]);
    }

    /**
     * POST /api/video-sessions/{sessionId}/whiteboard
     * Broadcast whiteboard elements to all participants.
     */
    public function whiteboardSync(int $sessionId, WhiteboardSyncRequest $request): JsonResponse
    {
        $validated = $request->validated();

        $videoSession = VideoSession::query()
            ->where('id', $sessionId)
            ->whereNotNull('started_at')
            ->whereNull('ended_at')
            ->firstOrFail();

        if ($videoSession->is_group) {
            if ((int) $videoSession->conversation_id !== (int) $validated['conversation_id']) {
                return response()->json(['message' => 'Invalid conversation for this session.'], 422);
            }

            $conversation = Conversation::findOrFail($videoSession->conversation_id);
            $member = ClassMember::query()
                ->where('conversation_id', $conversation->id)
                ->where('user_id', $request->user()->id)
                ->whereNull('left_at')
                ->first();

            if (! $member) {
                return response()->json(['message' => 'You are not a member of this class.'], 403);
            }

            if ($conversation->teacher_id !== $request->user()->id && ! $member->can_draw) {
                return response()->json(['message' => 'You do not have draw permission.'], 403);
            }
        } else {
            // 1:1 session
            $booking = Booking::findOrFail($videoSession->booking_id);
            if ($request->user()->id !== $booking->student_id && $request->user()->id !== $booking->teacher_id) {
                return response()->json(['message' => 'Unauthorized.'], 403);
            }

            // Find or create 1:1 conversation between these two
            $conversation = Conversation::query()
                ->where('is_group', false)
                ->where('direct_student_id', $booking->student_id)
                ->where('teacher_id', $booking->teacher_id)
                ->first();

            if (! $conversation) {
                $conversation = Conversation::firstOrCreate([
                    'is_group' => false,
                    'direct_student_id' => $booking->student_id,
                    'teacher_id' => $booking->teacher_id,
                ], [
                    'created_by' => $booking->student_id,
                    'direct_status' => 'accepted',
                ]);
            }

            if ((int) $conversation->id !== (int) $validated['conversation_id']) {
                return response()->json(['message' => 'Invalid conversation for this session.'], 422);
            }
        }

        broadcast(new WhiteboardUpdate(
            $validated['conversation_id'],
            $validated['elements'],
            $request->user()->id
        ))->toOthers();

        return response()->json(['message' => 'Synced.']);
    }

    /**
     * POST /api/video-sessions/{sessionId}/recording/consent
     * Teacher requests recording consent from all participants.
     */
    public function requestRecordingConsent(int $sessionId, RecordingConsentFormRequest $request): JsonResponse
    {
        $validated = $request->validated();

        $user = $request->user();
        $conversationId = $validated['conversation_id'];

        $conversation = Conversation::find($conversationId);
        if (! $conversation || $conversation->teacher_id !== $user->id) {
            return response()->json(['message' => 'Only the teacher can request recording.'], 403);
        }

        broadcast(new RecordingConsentRequest(
            $conversationId,
            $user->name,
            $sessionId
        ));

        return response()->json(['message' => 'Consent request sent.']);
    }

    /**
     * POST /api/webhooks/daily/recording-ready
     * Daily.co webhook for recording completion.
     */
    public function recordingWebhook(Request $request): JsonResponse
    {
        $event = $request->input('event');
        $payload = $request->input('payload');

        if ($event !== 'recording.ready') {
            return response()->json(['message' => 'Ignored.']);
        }

        $roomName = $payload['room_name'] ?? null;
        $downloadUrl = $payload['download_url'] ?? null;
        $recordingId = $payload['recording_id'] ?? null;

        if (! $roomName || ! $downloadUrl) {
            return response()->json(['message' => 'Invalid payload.'], 400);
        }

        // Find video session by room name
        $videoSession = VideoSession::where('room_name', $roomName)
            ->whereNull('ended_at')
            ->latest()
            ->first();

        if (! $videoSession) {
            // Fallback: search by room name only
            $videoSession = VideoSession::where('room_name', $roomName)->latest()->first();
        }

        if (! $videoSession) {
            return response()->json(['message' => 'Session not found.'], 404);
        }

        // Download from Daily and store to S3
        $s3Path = "recordings/{$videoSession->id}/session.mp4";

        try {
            $response = Http::get($downloadUrl);

            if ($response->successful()) {
                Storage::disk('s3')->put($s3Path, $response->body(), 'private');
                $videoSession->update([
                    'recording_url'   => $s3Path,
                    'composition_sid' => $recordingId, // Repurposing field for recording ID
                ]);
            }
        } catch (\Throwable $e) {
            report($e);
        }

        return response()->json(['message' => 'Recording processed.']);
    }

    /**
     * GET /api/recordings/{sessionId}
     * Generate signed S3 URL for recording download.
     */
    public function recording(int $sessionId, Request $request): JsonResponse
    {
        $videoSession = VideoSession::findOrFail($sessionId);
        $user = $request->user();

        // Auth check: must be teacher or student of the booking, or class member
        $booking = $videoSession->booking_id > 0 ? Booking::find($videoSession->booking_id) : null;

        if ($booking) {
            if ($user->id !== $booking->student_id && $user->id !== $booking->teacher_id) {
                return response()->json(['message' => 'Unauthorized.'], 403);
            }
        } else {
            $conversationId = (int) $videoSession->conversation_id;
            if (! $conversationId) {
                preg_match('/edubridge-group-(\d+)-/', $videoSession->room_name, $matches);
                $conversationId = (int) ($matches[1] ?? 0);
            }

            if (! ClassMember::where('conversation_id', $conversationId)->where('user_id', $user->id)->whereNull('left_at')->exists()) {
                return response()->json(['message' => 'Unauthorized.'], 403);
            }
        }

        if (! $videoSession->recording_url) {
            return response()->json(['message' => 'No recording available.'], 404);
        }

        $signedUrl = Storage::disk('s3')->temporaryUrl(
            $videoSession->recording_url,
            now()->addHours(2),
            ['ResponseContentDisposition' => 'attachment; filename="session.mp4"']
        );

        return response()->json(['url' => $signedUrl, 'expires_in' => 7200]);
    }

    private function addSentryBreadcrumb(string $message, array $data = []): void
    {
        if (! app()->bound('sentry')) {
            return;
        }

        app('sentry')->addBreadcrumb(new Breadcrumb(
            Breadcrumb::LEVEL_INFO,
            Breadcrumb::TYPE_DEFAULT,
            'video',
            $message,
            $data,
        ));
    }

    private function ensureVideoSession(Booking $booking, string $roomName): VideoSession
    {
        $videoSession = VideoSession::firstOrCreate(
            ['booking_id' => $booking->id],
            [
                'room_name' => $roomName,
                'room_type' => 'peer-to-peer',
                'is_group' => false,
                'host_id' => $booking->teacher_id,
            ]
        );

        return $this->normalizeVideoSession($videoSession, $booking, $roomName);
    }

    private function normalizeVideoSession(VideoSession $videoSession, Booking $booking, string $roomName): VideoSession
    {
        $updates = [];

        if ($videoSession->room_name !== $roomName) {
            $updates['room_name'] = $roomName;
        }

        if ($videoSession->room_type !== 'peer-to-peer') {
            $updates['room_type'] = 'peer-to-peer';
        }

        if ($videoSession->host_id !== $booking->teacher_id) {
            $updates['host_id'] = $booking->teacher_id;
        }

        if ((bool) $videoSession->is_group !== false) {
            $updates['is_group'] = false;
        }

        if ($updates !== []) {
            $videoSession->update($updates);
            $videoSession->refresh();
        }

        return $videoSession;
    }

    private function roomName(int $bookingId): string
    {
        return 'edubridge-' . $bookingId;
    }

    private function groupRoomName(int $groupId): string
    {
        return 'edubridge-group-' . $groupId . '-' . now()->format('Ymd');
    }

    private function generateJitsiJwt($user, bool $isOwner, string $roomName): ?string
    {
        $appId = env('VITE_JITSI_APP_ID');
        $apiKeyId = env('JITSI_API_KEY_ID');
        $privateKey = env('JITSI_PRIVATE_KEY');

        if (!$appId || !$apiKeyId || !$privateKey) {
            return null;
        }

        // Clean up the private key newlines if they are literal \n
        $privateKey = str_replace('\n', "\n", $privateKey);

        $payload = [
            'aud' => 'jitsi',
            'iss' => 'chat',
            'iat' => time(),
            'exp' => time() + 3600,
            'nbf' => time(),
            'sub' => $appId,
            'room' => $roomName, // Specifying the room name for security
            'context' => [
                'user' => [
                    'name' => $user->name,
                    'email' => $user->email,
                    'id' => (string) $user->id,
                    'moderator' => $isOwner ? true : false,
                ],
                'features' => [
                    'livestreaming' => false,
                    'recording' => true,
                    'transcription' => false,
                    'outbound-call' => false,
                ],
            ]
        ];

        try {
            // Using RS256 with the private key string directly for JaaS
            return JWT::encode($payload, $privateKey, 'RS256', $apiKeyId);
        } catch (\Throwable $e) {
            report($e);
            return null;
        }
    }

    private function videoProviderUnavailableResponse(\Throwable $exception): JsonResponse
    {
        report($exception);

        return response()->json([
            'message' => app()->environment('local')
                ? $exception->getMessage()
                : 'Video service is temporarily unavailable. Please try again shortly.',
            'video_unavailable' => true,
        ], 503);
    }

    private function joinWindowErrorResponse(Booking $booking): ?JsonResponse
    {
        $now = now();

        // The join window opens 15 minutes before the slot starts …
        $opensAt = $booking->start_at->copy()->subMinutes(15);

        // Late join support remains available up to 30 minutes after start_at.
        $expiresAt = $booking->start_at->copy()->addMinutes(30);

        if ($now->lt($opensAt)) {
            return response()->json([
                'message'           => 'Session is not open yet.',
                'too_early'         => true,
                'session_expired'   => false,
                'starts_at'         => $booking->start_at,
                'available_at'      => $opensAt,
                'starts_in_minutes' => (int) $now->diffInMinutes($booking->start_at),
            ], 200);
        }

        if ($now->gt($expiresAt)) {
            return response()->json([
                'message'         => 'Session join window has expired.',
                'too_early'       => false,
                'session_expired' => true,
                'starts_at'       => $booking->start_at,
                'ends_at'         => $booking->end_at,
                'expired_at'      => $expiresAt,
            ], 410);
        }

        return null;
    }

    private function unauthorizedResponse(string $message = 'Unauthorized.'): JsonResponse
    {
        return response()->json([
            'message' => $message,
            'unauthorized' => true,
            'too_early' => false,
            'session_expired' => false,
        ], 403);
    }

    private function unconfirmedResponse(Booking $booking): JsonResponse
    {
        return response()->json([
            'message' => 'Booking is not confirmed.',
            'unconfirmed' => true,
            'too_early' => false,
            'session_expired' => false,
            'booking_status' => $booking->status,
        ], 422);
    }
}
