<?php

namespace App\Http\Controllers\Api;

use App\Events\GroupSessionStarted;
use App\Events\RecordingConsentRequest;
use App\Events\WhiteboardUpdate;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\RecordingConsentRequest as RecordingConsentFormRequest;
use App\Http\Requests\Api\WhiteboardSyncRequest;
use App\Models\Booking;
use App\Models\ClassMember;
use App\Models\Conversation;
use App\Models\VideoSession;
use App\Services\TwilioService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Sentry\Breadcrumb;
use Twilio\Security\RequestValidator;

class VideoSessionController extends Controller
{
    public function __construct(
        protected TwilioService $twilioService
    ) {}

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

        $roomName = $this->roomName($bookingId);
        $this->ensureVideoSession($booking, $roomName);

        $identity = ($user->id === $booking->student_id ? 'student-' : 'teacher-') . $user->id;

        $this->addSentryBreadcrumb('video.token.generated', [
            'booking_id' => $bookingId,
            'user_id' => $user->id,
            'identity' => $identity,
        ]);

        $token = $this->twilioService->generateVideoToken($roomName, $identity);

        return response()->json([
            'token'           => $token,
            'room_name'       => $roomName,
            'identity'        => $identity,
            'too_early'       => false,
            'session_expired' => false,
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

        if ($conversation->activeClassMembers()->count() > 50) {
            return response()->json(['message' => 'Group sessions support a maximum of 50 participants.'], 422);
        }

        $roomName = 'edubridge-group-' . $conversationId . '-' . now()->format('Ymd');

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
                'room_name'      => $roomName,
                'room_type'      => 'group',
                'started_at'     => now(),
            ]);
        }

        $identity = 'teacher-' . $user->id;
        $token = $this->twilioService->generateVideoToken($videoSession->room_name, $identity);

        // Broadcast to all group members
        broadcast(new GroupSessionStarted(
            $conversationId,
            $user->name,
            $videoSession->id,
            $videoSession->room_name
        ));

        return response()->json([
            'token'            => $token,
            'room_name'        => $videoSession->room_name,
            'identity'         => $identity,
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
        $isMember = ClassMember::where('conversation_id', $conversationId)
            ->where('user_id', $user->id)
            ->whereNull('left_at')
            ->exists();

        if (! $isMember) {
            return response()->json(['message' => 'You are not a member of this class.'], 403);
        }

        // Find the active group session
        $videoSession = VideoSession::where('conversation_id', $conversationId)
            ->where('is_group', true)
            ->whereNotNull('started_at')
            ->whereNull('ended_at')
            ->latest()
            ->first();

        if (! $videoSession) {
            return response()->json(['message' => 'No active session right now.'], 404);
        }

        $identity = 'student-' . $user->id;
        $token = $this->twilioService->generateVideoToken($videoSession->room_name, $identity);

        return response()->json([
            'token'            => $token,
            'room_name'        => $videoSession->room_name,
            'identity'         => $identity,
            'video_session_id' => $videoSession->id,
        ]);
    }

    public function groupToken(int $groupId, Request $request): JsonResponse
    {
        return $this->joinGroupSession($groupId, $request);
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

        $booking->update([
            'status' => $duration >= 5 ? 'completed' : 'no_show',
        ]);

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
            ->where('conversation_id', $validated['conversation_id'])
            ->where('is_group', true)
            ->whereNotNull('started_at')
            ->whereNull('ended_at')
            ->firstOrFail();

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
     * POST /api/webhooks/twilio/recording-complete
     * Twilio webhook for recording completion.
     */
    public function recordingWebhook(Request $request): JsonResponse
    {
        $twilioSignature = (string) $request->header('X-Twilio-Signature', '');
        $twilioAuthToken = (string) config('services.twilio.auth_token');

        if ($twilioSignature === '' || $twilioAuthToken === '') {
            return response()->json(['message' => 'Invalid signature.'], 400);
        }

        $validator = new RequestValidator($twilioAuthToken);
        $isValidSignature = $validator->validate($twilioSignature, $request->fullUrl(), $request->post());

        if (! $isValidSignature) {
            return response()->json(['message' => 'Invalid signature.'], 400);
        }

        $roomSid       = $request->input('RoomSid');
        $compositionSid = $request->input('CompositionSid');
        $statusCallbackEvent = $request->input('StatusCallbackEvent');

        if ($statusCallbackEvent !== 'composition-available') {
            return response()->json(['message' => 'Ignored.']);
        }

        // Find video session by room SID or name
        $videoSession = VideoSession::where('room_name', 'like', '%' . $roomSid . '%')
            ->orWhere('composition_sid', $compositionSid)
            ->first();

        if (! $videoSession) {
            return response()->json(['message' => 'Session not found.'], 404);
        }

        // Download from Twilio and store to S3
        $downloadUrl = "https://video.twilio.com/v1/Compositions/{$compositionSid}/Media";
        $s3Path = "recordings/{$videoSession->id}/session.mp4";

        try {
            $twilioSid   = config('services.twilio.account_sid');
            $twilioToken = config('services.twilio.auth_token');
            $mediaUrl    = $downloadUrl . '?Ttl=3600';

            // Get redirect URL with auth
            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, $mediaUrl);
            curl_setopt($ch, CURLOPT_USERPWD, "{$twilioSid}:{$twilioToken}");
            curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            $content = curl_exec($ch);
            curl_close($ch);

            if ($content) {
                Storage::disk('s3')->put($s3Path, $content, 'private');
                $videoSession->update([
                    'recording_url'   => $s3Path,
                    'composition_sid' => $compositionSid,
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

        $signedUrl = Storage::disk('s3')->temporaryUrl($videoSession->recording_url, now()->addHours(2));

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
                'room_type' => 'group',
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

        if ($videoSession->room_type !== 'group') {
            $updates['room_type'] = 'group';
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

    private function joinWindowErrorResponse(Booking $booking): ?JsonResponse
    {
        $now = now();
        $opensAt = $booking->start_at->copy()->subMinutes(15);
        $expiresAt = $booking->start_at->copy()->addMinutes(30);

        if ($now->lt($opensAt)) {
            return response()->json([
                'message' => 'Session is not open yet.',
                'too_early' => true,
                'session_expired' => false,
                'starts_at' => $booking->start_at,
                'available_at' => $opensAt,
                'starts_in_minutes' => (int) $now->diffInMinutes($booking->start_at),
            ], 422);
        }

        if ($now->gt($expiresAt)) {
            return response()->json([
                'message' => 'Session join window has expired.',
                'too_early' => false,
                'session_expired' => true,
                'starts_at' => $booking->start_at,
                'expired_at' => $expiresAt,
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
