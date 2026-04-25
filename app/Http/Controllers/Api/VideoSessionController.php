<?php

namespace App\Http\Controllers\Api;

use App\Events\GroupSessionStarted;
use App\Events\RecordingConsentRequest;
use App\Events\WhiteboardUpdate;
use App\Http\Controllers\Controller;
use App\Jobs\ReleasePayment;
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
            return response()->json(['message' => 'Unauthorized.'], 403);
        }

        if ($booking->status !== 'confirmed') {
            return response()->json(['message' => 'Booking is not confirmed.'], 422);
        }

        $minutesUntil = now()->diffInMinutes($booking->start_at, false);
        if ($minutesUntil > 30) {
            return response()->json([
                'too_early'         => true,
                'starts_in_minutes' => (int) $minutesUntil,
            ]);
        }

        $roomName = 'edubridge-' . $bookingId;

        $videoSession = VideoSession::firstOrCreate(
            ['booking_id' => $bookingId],
            ['room_name' => $roomName, 'room_type' => 'peer-to-peer']
        );

        $identity = ($user->id === $booking->student_id ? 'student-' : 'teacher-') . $user->id;

        $this->addSentryBreadcrumb('video.token.generated', [
            'booking_id' => $bookingId,
            'user_id' => $user->id,
            'identity' => $identity,
        ]);

        $token = $this->twilioService->generateVideoToken($roomName, $identity);

        return response()->json([
            'token'     => $token,
            'room_name' => $roomName,
            'identity'  => $identity,
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

        $roomName = 'edubridge-group-' . $conversationId . '-' . time();

        // Create a video session record linked to the conversation
        $videoSession = VideoSession::create([
            'booking_id'      => 0, // no booking for group sessions
            'room_name'       => $roomName,
            'room_type'       => 'group',
            'started_at'      => now(),
        ]);

        // Store the session ID on the conversation for reference
        $conversation->update(['description' => $conversation->description]); // touch updated_at

        $identity = 'teacher-' . $user->id;
        $token = $this->twilioService->generateVideoToken($roomName, $identity);

        // Broadcast to all group members
        broadcast(new GroupSessionStarted(
            $conversationId,
            $user->name,
            $videoSession->id,
            $roomName
        ));

        return response()->json([
            'token'            => $token,
            'room_name'        => $roomName,
            'identity'         => $identity,
            'video_session_id' => $videoSession->id,
        ]);
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
        $videoSession = VideoSession::where('room_name', 'like', "edubridge-group-{$conversationId}-%")
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

    /**
     * PATCH /api/video-sessions/{bookingId}/start
     */
    public function start(int $bookingId): JsonResponse
    {
        $booking = Booking::findOrFail($bookingId);
        $user    = auth()->user();

        if ($user->id !== $booking->student_id && $user->id !== $booking->teacher_id) {
            return response()->json(['message' => 'Unauthorized.'], 403);
        }

        $videoSession = VideoSession::where('booking_id', $bookingId)->firstOrFail();

        if (! $videoSession->started_at) {
            $videoSession->update(['started_at' => now()]);
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
            return response()->json(['message' => 'Only the teacher can end the session.'], 403);
        }

        $videoSession = VideoSession::where('booking_id', $bookingId)->firstOrFail();

        $videoSession->update([
            'ended_at'         => now(),
            'duration_minutes' => $videoSession->started_at
                ? (int) $videoSession->started_at->diffInMinutes(now())
                : 0,
        ]);

        $duration = $videoSession->duration_minutes;

        if ($duration >= 5) {
            $booking->update(['status' => 'completed']);

            if ($booking->payment_status === 'held') {
                ReleasePayment::dispatch($booking->id)->delay(now()->addHours(24));
            }
        } else {
            $booking->update(['status' => 'no_show']);
        }

        return response()->json([
            'message'          => 'Session ended.',
            'duration_minutes' => $duration,
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

        // Extract conversation ID from room_name: edubridge-group-{convId}-{timestamp}
        preg_match('/edubridge-group-(\d+)-/', $videoSession->room_name, $matches);
        $conversationId = (int) ($matches[1] ?? 0);

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
    public function whiteboardSync(int $sessionId, Request $request): JsonResponse
    {
        $request->validate(['elements' => 'required|array', 'conversation_id' => 'required|integer']);

        broadcast(new WhiteboardUpdate(
            $request->input('conversation_id'),
            $request->input('elements'),
            $request->user()->id
        ))->toOthers();

        return response()->json(['message' => 'Synced.']);
    }

    /**
     * POST /api/video-sessions/{sessionId}/recording/consent
     * Teacher requests recording consent from all participants.
     */
    public function requestRecordingConsent(int $sessionId, Request $request): JsonResponse
    {
        $request->validate(['conversation_id' => 'required|integer']);

        $user = $request->user();
        $conversationId = $request->input('conversation_id');

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
            // Group session — check class membership via room name
            preg_match('/edubridge-group-(\d+)-/', $videoSession->room_name, $matches);
            $conversationId = (int) ($matches[1] ?? 0);

            if (! ClassMember::where('conversation_id', $conversationId)->where('user_id', $user->id)->exists()) {
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
}

