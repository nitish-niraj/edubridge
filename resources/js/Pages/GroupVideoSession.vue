<script setup>
import { ref, onMounted, onUnmounted, computed, watch } from 'vue';
import { usePage, Link } from '@inertiajs/vue3';
import axios from 'axios';

const props = defineProps({ conversationId: Number });
const page = usePage();
const user = computed(() => page.props.auth?.user);

// Connection state
const token = ref(null);
const roomName = ref(null);
const identity = ref(null);
const sessionId = ref(null);
const connected = ref(false);
const error = ref(null);
const loading = ref(true);
const isTeacher = ref(false);

// Video elements
const localTrackEls = ref([]);
const remoteParticipants = ref([]);

// Panels
const showParticipants = ref(false);
const showWhiteboard = ref(false);

// Controls
const cameraOn = ref(true);
const micOn = ref(true);
const timer = ref(0);
let timerInterval = null;
let twilioRoom = null;

// Raise hand
const raisedHands = ref([]);
const myHandRaised = ref(false);

// Recording
const recordingRequested = ref(false);
const recordingActive = ref(false);

// Group info
const groupInfo = ref(null);

const formatTime = (s) => {
    const m = Math.floor(s / 60);
    const sec = s % 60;
    return `${m.toString().padStart(2, '0')}:${sec.toString().padStart(2, '0')}`;
};

onMounted(async () => {
    try {
        // Fetch group info
        const { data: gData } = await axios.get(`/api/groups/${props.conversationId}`);
        groupInfo.value = gData;
        isTeacher.value = gData.teacher_id === user.value?.id;

        // Get token — teacher starts, student joins
        const endpoint = isTeacher.value
            ? `/api/video-sessions/group/${props.conversationId}/start`
            : `/api/video-sessions/group/${props.conversationId}/join`;

        const { data } = await axios.post(endpoint);
        token.value = data.token;
        roomName.value = data.room_name;
        identity.value = data.identity;
        sessionId.value = data.video_session_id;

        // Connect to Twilio
        await connectToRoom();
    } catch (e) {
        error.value = e.response?.data?.message || 'Failed to connect.';
    }
    loading.value = false;
});

const connectToRoom = async () => {
    try {
        const Video = await import('twilio-video');
        twilioRoom = await Video.connect(token.value, {
            name: roomName.value,
            audio: true,
            video: { width: 640 },
        });

        connected.value = true;

        // Start timer
        timerInterval = setInterval(() => timer.value++, 1000);

        // Local tracks
        twilioRoom.localParticipant.tracks.forEach(pub => {
            if (pub.track) localTrackEls.value.push(pub.track);
        });

        // Remote participants
        twilioRoom.participants.forEach(handleParticipantConnected);
        twilioRoom.on('participantConnected', handleParticipantConnected);
        twilioRoom.on('participantDisconnected', handleParticipantDisconnected);
    } catch (e) {
        error.value = 'Failed to connect to video room: ' + e.message;
    }
};

const handleParticipantConnected = (participant) => {
    const p = { sid: participant.sid, identity: participant.identity, tracks: [], audioEnabled: true, videoEnabled: true };
    remoteParticipants.value.push(p);

    participant.tracks.forEach(pub => {
        if (pub.isSubscribed && pub.track) p.tracks.push(pub.track);
    });

    participant.on('trackSubscribed', track => {
        p.tracks.push(track);
        remoteParticipants.value = [...remoteParticipants.value];
    });

    participant.on('trackUnsubscribed', track => {
        p.tracks = p.tracks.filter(t => t !== track);
    });

    participant.on('trackDisabled', track => {
        if (track.kind === 'audio') p.audioEnabled = false;
        if (track.kind === 'video') p.videoEnabled = false;
        remoteParticipants.value = [...remoteParticipants.value];
    });

    participant.on('trackEnabled', track => {
        if (track.kind === 'audio') p.audioEnabled = true;
        if (track.kind === 'video') p.videoEnabled = true;
        remoteParticipants.value = [...remoteParticipants.value];
    });
};

const handleParticipantDisconnected = (participant) => {
    remoteParticipants.value = remoteParticipants.value.filter(p => p.sid !== participant.sid);
};

const toggleCamera = () => {
    cameraOn.value = !cameraOn.value;
    twilioRoom?.localParticipant.videoTracks.forEach(pub => {
        cameraOn.value ? pub.track.enable() : pub.track.disable();
    });
};

const toggleMic = () => {
    micOn.value = !micOn.value;
    twilioRoom?.localParticipant.audioTracks.forEach(pub => {
        micOn.value ? pub.track.enable() : pub.track.disable();
    });
};

const endSession = async () => {
    if (isTeacher.value && !confirm('End session for all participants?')) return;
    clearInterval(timerInterval);

    if (isTeacher.value && sessionId.value) {
        try {
            await axios.patch(`/api/video-sessions/group/${sessionId.value}/end`);
        } catch (e) { /* noop */ }
    }

    twilioRoom?.disconnect();
    window.location.href = `/teacher/classes/${props.conversationId}`;
};

// Raise hand
const raiseHand = () => {
    myHandRaised.value = !myHandRaised.value;
    // Use Pusher to broadcast (simplified — POST to server)
    if (myHandRaised.value) {
        raisedHands.value.push({ id: user.value.id, name: user.value.name });
        playDing();
    } else {
        raisedHands.value = raisedHands.value.filter(h => h.id !== user.value.id);
    }
};

const playDing = () => {
    try {
        const ctx = new (window.AudioContext || window.webkitAudioContext)();
        const osc = ctx.createOscillator();
        const gain = ctx.createGain();
        osc.connect(gain);
        gain.connect(ctx.destination);
        osc.frequency.value = 800;
        gain.gain.value = 0.1;
        osc.start();
        osc.stop(ctx.currentTime + 0.15);
    } catch (e) { /* Audio not available */ }
};

const acknowledgeHand = (studentId) => {
    raisedHands.value = raisedHands.value.filter(h => h.id !== studentId);
};

// Whiteboard sync
const whiteboardElements = ref([]);
const sendWhiteboardUpdate = async (elements) => {
    if (!sessionId.value) return;
    try {
        await axios.post(`/api/video-sessions/${sessionId.value}/whiteboard`, {
            elements,
            conversation_id: props.conversationId,
        });
    } catch (e) { /* debounced, ok to fail */ }
};

// Recording
const requestRecording = async () => {
    try {
        await axios.post(`/api/video-sessions/${sessionId.value}/recording/consent`, {
            conversation_id: props.conversationId,
        });
        recordingRequested.value = true;
    } catch (e) { /* noop */ }
};

onUnmounted(() => {
    clearInterval(timerInterval);
    twilioRoom?.disconnect();
});
</script>

<template>
    <div style="height: 100vh; background: #1a1a2e; color: #fff; font-family: 'Nunito', sans-serif; display: flex; flex-direction: column; overflow: hidden;">

        <!-- Loading -->
        <div v-if="loading" style="flex: 1; display: flex; align-items: center; justify-content: center; font-size: 18px;">
            Connecting to session...
        </div>

        <!-- Error -->
        <div v-else-if="error" style="flex: 1; display: flex; flex-direction: column; align-items: center; justify-content: center; gap: 16px;">
            <div style="font-size: 48px;">😕</div>
            <p style="font-size: 18px; color: #ff6b6b;">{{ error }}</p>
            <Link :href="route('teacher.classes.manage', { id: conversationId })" style="color: #4ecdc4; text-decoration: underline;">← Back to Class</Link>
        </div>

        <!-- Video session -->
        <template v-else>
            <!-- Top bar -->
            <div style="display: flex; justify-content: space-between; align-items: center; padding: 12px 20px; background: rgba(255,255,255,0.05); flex-shrink: 0;">
                <div style="display: flex; align-items: center; gap: 12px;">
                    <span style="font-size: 20px;">🎥</span>
                    <span style="font-weight: 700; font-size: 16px;">{{ groupInfo?.title || 'Group Session' }}</span>
                    <span style="background: #4ecdc4; color: #1a1a2e; padding: 2px 10px; border-radius: 12px; font-size: 12px; font-weight: 700;">
                        {{ remoteParticipants.length + 1 }} joined
                    </span>
                </div>
                <div style="display: flex; align-items: center; gap: 16px;">
                    <span style="font-size: 14px; color: #aaa; font-variant-numeric: tabular-nums;">⏱ {{ formatTime(timer) }}</span>
                    <span v-if="recordingActive" style="display: flex; align-items: center; gap: 6px; color: #ff6b6b; font-size: 13px;">
                        <span style="width: 8px; height: 8px; background: #ff6b6b; border-radius: 50%; animation: pulse 1s infinite;"></span>
                        REC
                    </span>
                </div>
            </div>

            <!-- Main content -->
            <div style="flex: 1; display: flex; overflow: hidden; position: relative;">

                <!-- Video grid -->
                <div style="flex: 1; display: grid; gap: 8px; padding: 8px; overflow: auto;"
                    :style="{
                        gridTemplateColumns: remoteParticipants.length <= 1 ? '1fr' :
                            remoteParticipants.length <= 4 ? 'repeat(2, 1fr)' :
                            'repeat(3, 1fr)',
                        gridAutoRows: 'minmax(200px, 1fr)'
                    }">
                    <!-- Local video -->
                    <div style="position: relative; background: #16213e; border-radius: 12px; overflow: hidden; display: flex; align-items: center; justify-content: center;">
                        <div v-if="!cameraOn" style="font-size: 48px;">📷</div>
                        <div v-for="track in localTrackEls" :key="track.sid" ref="localVidContainer" style="width: 100%; height: 100%;">
                            <component :is="track.kind === 'video' ? 'video' : 'audio'" :srcObject="track.mediaStreamTrack ? new MediaStream([track.mediaStreamTrack]) : null" autoplay muted playsinline
                                style="width: 100%; height: 100%; object-fit: cover;" />
                        </div>
                        <div style="position: absolute; bottom: 8px; left: 8px; background: rgba(0,0,0,0.6); padding: 4px 12px; border-radius: 8px; font-size: 12px;">
                            You {{ isTeacher ? '(Teacher)' : '' }}
                        </div>
                    </div>

                    <!-- Remote participants -->
                    <div v-for="p in remoteParticipants" :key="p.sid"
                        style="position: relative; background: #16213e; border-radius: 12px; overflow: hidden; display: flex; align-items: center; justify-content: center;">
                        <div v-if="!p.videoEnabled" style="font-size: 48px;">👤</div>
                        <div v-for="track in p.tracks.filter(t => t.kind === 'video')" :key="track.sid" style="width: 100%; height: 100%;">
                            <video :srcObject="track.mediaStreamTrack ? new MediaStream([track.mediaStreamTrack]) : null" autoplay playsinline
                                style="width: 100%; height: 100%; object-fit: cover;" />
                        </div>
                        <div style="position: absolute; bottom: 8px; left: 8px; background: rgba(0,0,0,0.6); padding: 4px 12px; border-radius: 8px; font-size: 12px; display: flex; align-items: center; gap: 6px;">
                            {{ p.identity }}
                            <span v-if="!p.audioEnabled">🔇</span>
                            <span v-if="!p.videoEnabled">📷</span>
                        </div>
                    </div>
                </div>

                <!-- Participants panel (right sidebar) -->
                <transition name="slide">
                    <div v-if="showParticipants" style="width: 280px; background: #16213e; border-left: 1px solid rgba(255,255,255,0.1); padding: 16px; overflow-y: auto; flex-shrink: 0;">
                        <h3 style="font-size: 16px; font-weight: 700; margin-bottom: 16px;">Participants ({{ remoteParticipants.length + 1 }})</h3>

                        <!-- Raised hands queue -->
                        <div v-if="raisedHands.length" style="margin-bottom: 16px; background: rgba(255,193,7,0.15); border-radius: 10px; padding: 12px;">
                            <div style="font-size: 13px; font-weight: 700; color: #ffc107; margin-bottom: 8px;">🙋 Raised Hands</div>
                            <div v-for="h in raisedHands" :key="h.id" style="display: flex; justify-content: space-between; align-items: center; padding: 6px 0;">
                                <span style="font-size: 14px;">{{ h.name }}</span>
                                <button v-if="isTeacher" @click="acknowledgeHand(h.id)"
                                    style="background: #4ecdc4; border: none; color: #1a1a2e; padding: 4px 12px; border-radius: 8px; font-size: 12px; cursor: pointer; font-weight: 700;">
                                    Acknowledge
                                </button>
                            </div>
                        </div>

                        <!-- Teacher controls -->
                        <button v-if="isTeacher"
                            style="width: 100%; padding: 10px; background: rgba(239,83,80,0.2); border: 1px solid rgba(239,83,80,0.4); border-radius: 10px; color: #ef5350; cursor: pointer; font-weight: 700; font-size: 13px; margin-bottom: 12px;">
                            🔇 Mute All
                        </button>

                        <!-- Participant list -->
                        <div v-for="p in [{ identity: identity, isLocal: true }, ...remoteParticipants]" :key="p.sid || 'local'"
                            style="display: flex; align-items: center; justify-content: space-between; padding: 10px 0; border-bottom: 1px solid rgba(255,255,255,0.06);">
                            <div style="display: flex; align-items: center; gap: 8px;">
                                <div style="width: 32px; height: 32px; background: #4ecdc4; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-size: 14px; font-weight: 700; color: #1a1a2e;">
                                    {{ (p.identity || '?')[0].toUpperCase() }}
                                </div>
                                <span style="font-size: 14px;">{{ p.isLocal ? 'You' : p.identity }}</span>
                            </div>
                            <button v-if="isTeacher && !p.isLocal"
                                style="background: none; border: none; color: #ef5350; cursor: pointer; font-size: 12px;">
                                Remove
                            </button>
                        </div>
                    </div>
                </transition>

                <!-- Whiteboard overlay -->
                <div v-if="showWhiteboard"
                    style="position: absolute; inset: 0; z-index: 10; background: #fff; display: flex; flex-direction: column;">
                    <div style="padding: 12px 20px; background: #f5f5f5; display: flex; justify-content: space-between; align-items: center;">
                        <span style="font-weight: 700; color: #333;">📝 Whiteboard</span>
                        <button @click="showWhiteboard = false"
                            style="background: #ef5350; color: #fff; border: none; padding: 6px 16px; border-radius: 8px; cursor: pointer; font-weight: 700;">
                            Close
                        </button>
                    </div>
                    <div style="flex: 1; display: flex; align-items: center; justify-content: center; color: #999; font-size: 16px;">
                        <!-- Excalidraw renders here -->
                        <p>Excalidraw whiteboard — install <code>@excalidraw/excalidraw</code> to enable</p>
                    </div>
                </div>
            </div>

            <!-- Controls bar -->
            <div style="display: flex; justify-content: center; align-items: center; gap: 16px; padding: 16px 20px; background: rgba(255,255,255,0.05); flex-shrink: 0;">
                <!-- Raise hand (students only) -->
                <button v-if="!isTeacher" @click="raiseHand"
                    :style="{ background: myHandRaised ? '#ffc107' : 'rgba(255,255,255,0.1)', color: myHandRaised ? '#1a1a2e' : '#fff', border: 'none', width: '48px', height: '48px', borderRadius: '50%', cursor: 'pointer', fontSize: '20px' }">
                    🙋
                </button>

                <button @click="toggleMic"
                    :style="{ background: micOn ? 'rgba(255,255,255,0.1)' : '#ef5350', color: '#fff', border: 'none', width: '48px', height: '48px', borderRadius: '50%', cursor: 'pointer', fontSize: '20px' }">
                    {{ micOn ? '🎤' : '🔇' }}
                </button>

                <button @click="toggleCamera"
                    :style="{ background: cameraOn ? 'rgba(255,255,255,0.1)' : '#ef5350', color: '#fff', border: 'none', width: '48px', height: '48px', borderRadius: '50%', cursor: 'pointer', fontSize: '20px' }">
                    {{ cameraOn ? '📹' : '📷' }}
                </button>

                <button @click="showWhiteboard = !showWhiteboard"
                    :style="{ background: showWhiteboard ? '#4ecdc4' : 'rgba(255,255,255,0.1)', color: showWhiteboard ? '#1a1a2e' : '#fff', border: 'none', width: '48px', height: '48px', borderRadius: '50%', cursor: 'pointer', fontSize: '20px' }">
                    📝
                </button>

                <button @click="showParticipants = !showParticipants"
                    :style="{ background: showParticipants ? '#4ecdc4' : 'rgba(255,255,255,0.1)', color: showParticipants ? '#1a1a2e' : '#fff', border: 'none', width: '48px', height: '48px', borderRadius: '50%', cursor: 'pointer', fontSize: '20px' }">
                    👥
                </button>

                <!-- Record (teacher only) -->
                <button v-if="isTeacher" @click="requestRecording"
                    :style="{ background: recordingActive ? '#ef5350' : 'rgba(255,255,255,0.1)', color: '#fff', border: 'none', width: '48px', height: '48px', borderRadius: '50%', cursor: 'pointer', fontSize: '18px' }">
                    ⏺
                </button>

                <button @click="endSession"
                    style="background: #ef5350; color: #fff; border: none; padding: 12px 28px; border-radius: 30px; cursor: pointer; font-weight: 700; font-size: 15px; margin-left: 12px;">
                    {{ isTeacher ? 'End Session' : 'Leave' }}
                </button>
            </div>
        </template>
    </div>

    <style>
        @keyframes pulse { 0%, 100% { opacity: 1; } 50% { opacity: 0.3; } }
        .slide-enter-active, .slide-leave-active { transition: width 0.2s ease; }
        .slide-enter-from, .slide-leave-to { width: 0 !important; padding: 0 !important; overflow: hidden; }
    </style>
</template>
