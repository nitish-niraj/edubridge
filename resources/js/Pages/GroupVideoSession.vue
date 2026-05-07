<script setup>
import { ref, onMounted, onUnmounted, computed } from 'vue';
import { usePage, Link } from '@inertiajs/vue3';
import axios from 'axios';

const props = defineProps({ conversationId: Number });
const page = usePage();
const user = computed(() => page.props.auth?.user);

// Connection state
const jwt = ref(null);
const roomName = ref(null);
const identity = ref(null);
const sessionId = ref(null);
const connected = ref(false);
const error = ref(null);
const loading = ref(true);
const isTeacher = ref(false);
const groupInfo = ref(null);

// Jitsi configuration
const rawJitsiDomain = import.meta.env.VITE_JITSI_DOMAIN || 'meet.jit.si';
const jitsiDomain = rawJitsiDomain.replace(/^https?:\/\//, '').replace(/\/+$/, '');
const jitsiExternalApiUrl = import.meta.env.VITE_JITSI_EXTERNAL_API_URL || `https://${jitsiDomain}/external_api.js`;

// UI state
const showParticipants = ref(false);
const showWhiteboard = ref(false);
const controlsVisible = ref(true);

// Controls
const cameraOn = ref(true);
const micOn = ref(true);
const timer = ref(0);
let timerInterval = null;
let jitsiApi = null;
let controlsHideTimer = null;

// Participants
const participants = ref([]);

// Whiteboard
const whiteboardElements = ref([]);
let whiteboardDebounceTimer = null;

// Raise hand
const raisedHands = ref([]);
const myHandRaised = ref(false);

const formatTime = (s) => {
    const m = Math.floor(s / 60);
    const sec = s % 60;
    return `${m.toString().padStart(2, '0')}:${sec.toString().padStart(2, '0')}`;
};

const loadJitsiScript = () => new Promise((resolve, reject) => {
    if (window.JitsiMeetExternalAPI) {
        resolve();
        return;
    }
    const script = document.createElement('script');
    script.src = jitsiExternalApiUrl;
    script.onload = resolve;
    script.onerror = () => reject(new Error('Failed to load Jitsi script.'));
    document.head.appendChild(script);
});

const destroyJitsi = () => {
    if (!jitsiApi) return;
    try {
        jitsiApi.dispose();
    } catch {
        /* ignore */
    }
    jitsiApi = null;
};

const resetControlsHideTimer = () => {
    window.clearTimeout(controlsHideTimer);
    controlsHideTimer = window.setTimeout(() => {
        controlsVisible.value = false;
    }, 3000);
};

const handleUserActivity = () => {
    if (!connected.value) return;
    controlsVisible.value = true;
    resetControlsHideTimer();
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
        
        jwt.value = data.jwt;
        roomName.value = data.room_name;
        identity.value = data.identity;
        sessionId.value = data.video_session_id;

        // Connect to Jitsi
        await connectToRoom();
        
        // Listen to Pusher events
        setupPusherListeners();
    } catch (e) {
        error.value = e.response?.data?.message || 'Failed to connect.';
    }
    loading.value = false;
});

const connectToRoom = async () => {
    try {
        await loadJitsiScript();
        destroyJitsi();

        const container = document.getElementById('jitsi-container');
        if (!container) throw new Error('Jitsi container not found.');

        const appId = import.meta.env.VITE_JITSI_APP_ID;
        const fullRoomName = appId ? `${appId}/${roomName.value}` : roomName.value;

        jitsiApi = new window.JitsiMeetExternalAPI(jitsiDomain, {
            roomName: fullRoomName,
            jwt: jwt.value,
            parentNode: container,
            width: '100%',
            height: '100%',
            userInfo: {
                displayName: user.value?.name || identity.value,
            },
            configOverwrite: {
                startWithAudioMuted: !micOn.value,
                startWithVideoMuted: !cameraOn.value,
                prejoinPageEnabled: false,
                disableDeepLinking: true,
            },
            interfaceConfigOverwrite: {
                TOOLBAR_BUTTONS: [],
                SHOW_JITSI_WATERMARK: false,
                SHOW_WATERMARK_FOR_GUESTS: false,
                SHOW_BRAND_WATERMARK: false,
                DEFAULT_BACKGROUND: '#1a1a2e',
            },
        });

        jitsiApi.addEventListeners({
            videoConferenceJoined: () => {
                connected.value = true;
                controlsVisible.value = true;
                resetControlsHideTimer();
                
                // Start timer
                if (!timerInterval) {
                    timerInterval = setInterval(() => timer.value++, 1000);
                }
            },
            videoConferenceLeft: () => {
                connected.value = false;
            },
            participantJoined: (e) => {
                participants.value.push({
                    id: e.id,
                    displayName: e.displayName || 'Participant',
                });
            },
            participantLeft: (e) => {
                participants.value = participants.value.filter(p => p.id !== e.id);
            },
            audioMuteStatusChanged: (e) => {
                micOn.value = !e.muted;
            },
            videoMuteStatusChanged: (e) => {
                cameraOn.value = !e.muted;
            },
        });
    } catch (e) {
        error.value = 'Failed to connect to video room: ' + e.message;
    }
};

const setupPusherListeners = () => {
    if (!window.Echo) return;

    // Listen to DrawPermissionGranted
    window.Echo.private(`conversation.${props.conversationId}`)
        .listen('DrawPermissionGranted', (event) => {
            if (event.student_id === user.value?.id) {
                alert(event.granted ? 'You can now draw on the whiteboard!' : 'Draw permission revoked.');
            }
        });

    // Listen to WhiteboardUpdate
    window.Echo.private(`conversation.${props.conversationId}`)
        .listen('WhiteboardUpdate', (event) => {
            if (event.sender_id !== user.value?.id) {
                whiteboardElements.value = event.elements;
                // TODO: Update Excalidraw scene when integrated
            }
        });
};

const toggleCamera = () => {
    if (jitsiApi) {
        jitsiApi.executeCommand('toggleVideo');
    } else {
        cameraOn.value = !cameraOn.value;
    }
};

const toggleMic = () => {
    if (jitsiApi) {
        jitsiApi.executeCommand('toggleAudio');
    } else {
        micOn.value = !micOn.value;
    }
};

const endSession = async () => {
    if (isTeacher.value && !confirm('End session for all participants?')) return;
    
    clearInterval(timerInterval);

    if (isTeacher.value && sessionId.value) {
        try {
            await axios.patch(`/api/video-sessions/group/${sessionId.value}/end`);
        } catch (e) {
            /* noop */
        }
    }

    if (jitsiApi) {
        try {
            jitsiApi.executeCommand('hangup');
        } catch {
            /* noop */
        }
        destroyJitsi();
    }

    window.location.href = isTeacher.value
        ? `/teacher/classes/${props.conversationId}`
        : '/student/dashboard';
};

// Raise hand
const raiseHand = () => {
    myHandRaised.value = !myHandRaised.value;
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
    } catch (e) {
        /* Audio not available */
    }
};

const acknowledgeHand = (studentId) => {
    raisedHands.value = raisedHands.value.filter(h => h.id !== studentId);
};

// Whiteboard sync
const sendWhiteboardUpdate = (elements) => {
    if (!sessionId.value) return;
    
    clearTimeout(whiteboardDebounceTimer);
    whiteboardDebounceTimer = setTimeout(async () => {
        try {
            await axios.post(`/api/video-sessions/${sessionId.value}/whiteboard`, {
                elements,
                conversation_id: props.conversationId,
            });
        } catch (e) {
            /* debounced, ok to fail */
        }
    }, 150);
};

onUnmounted(() => {
    clearInterval(timerInterval);
    clearTimeout(controlsHideTimer);
    clearTimeout(whiteboardDebounceTimer);
    
    if (jitsiApi) {
        try {
            jitsiApi.dispose();
        } catch {
            /* ignore */
        }
        jitsiApi = null;
    }
});
</script>

<template>
    <div 
        class="video-root"
        @mousemove="handleUserActivity"
        @touchstart.passive="handleUserActivity"
        @click="handleUserActivity"
    >
        <!-- Loading -->
        <div v-if="loading" class="state-screen">
            <h2>Connecting to session...</h2>
            <p>Preparing your group studio.</p>
        </div>

        <!-- Error -->
        <div v-else-if="error" class="state-screen state-screen--error">
            <div style="font-size: 48px; margin-bottom: 16px;">😕</div>
            <h2>{{ error }}</h2>
            <Link
                :href="isTeacher ? `/teacher/classes/${props.conversationId}` : '/student/dashboard'"
                style="color: #4ecdc4; text-decoration: underline; margin-top: 16px; display: inline-block;"
            >
                Back
            </Link>
        </div>

        <!-- Video session -->
        <template v-else>
            <!-- Top bar -->
            <div class="top-strip">
                <div style="display: flex; align-items: center; gap: 12px;">
                    <span style="font-size: 20px;">🎥</span>
                    <span style="font-weight: 700; font-size: 16px;">{{ groupInfo?.title || 'Group Session' }}</span>
                    <span style="background: #4ecdc4; color: #1a1a2e; padding: 2px 10px; border-radius: 12px; font-size: 12px; font-weight: 700;">
                        {{ participants.length + 1 }} joined
                    </span>
                </div>
                <div style="display: flex; align-items: center; gap: 16px;">
                    <span style="font-size: 14px; color: #aaa; font-variant-numeric: tabular-nums;">⏱ {{ formatTime(timer) }}</span>
                </div>
            </div>

            <!-- Jitsi Meet iframe -->
            <div id="jitsi-container" class="jitsi-layer" />

            <!-- Participants panel (right sidebar) -->
            <transition name="slide">
                <div v-if="showParticipants" class="participants-panel">
                    <h3 style="font-size: 16px; font-weight: 700; margin-bottom: 16px;">Participants ({{ participants.length + 1 }})</h3>

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

                    <!-- Participant list -->
                    <div style="display: flex; align-items: center; justify-content: space-between; padding: 10px 0; border-bottom: 1px solid rgba(255,255,255,0.06);">
                        <div style="display: flex; align-items: center; gap: 8px;">
                            <div style="width: 32px; height: 32px; background: #4ecdc4; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-size: 14px; font-weight: 700; color: #1a1a2e;">
                                {{ (user?.name || '?')[0].toUpperCase() }}
                            </div>
                            <span style="font-size: 14px;">You {{ isTeacher ? '(Teacher)' : '' }}</span>
                        </div>
                    </div>
                    
                    <div v-for="p in participants" :key="p.id"
                        style="display: flex; align-items: center; justify-content: space-between; padding: 10px 0; border-bottom: 1px solid rgba(255,255,255,0.06);">
                        <div style="display: flex; align-items: center; gap: 8px;">
                            <div style="width: 32px; height: 32px; background: #4ecdc4; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-size: 14px; font-weight: 700; color: #1a1a2e;">
                                {{ (p.displayName || '?')[0].toUpperCase() }}
                            </div>
                            <span style="font-size: 14px;">{{ p.displayName }}</span>
                        </div>
                    </div>
                </div>
            </transition>

            <!-- Whiteboard overlay -->
            <div v-if="showWhiteboard" class="whiteboard-overlay">
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

            <!-- Controls bar -->
            <div class="controls-bar" :class="{ 'controls-bar--hidden': !controlsVisible }">
                <!-- Raise hand (students only) -->
                <button v-if="!isTeacher" @click="raiseHand" class="control-btn"
                    :style="{ background: myHandRaised ? '#ffc107' : 'rgba(255,255,255,0.1)', color: myHandRaised ? '#1a1a2e' : '#fff' }">
                    🙋
                </button>

                <button @click="toggleMic" class="control-btn"
                    :style="{ background: micOn ? 'rgba(255,255,255,0.1)' : '#ef5350' }">
                    {{ micOn ? '🎤' : '🔇' }}
                </button>

                <button @click="toggleCamera" class="control-btn"
                    :style="{ background: cameraOn ? 'rgba(255,255,255,0.1)' : '#ef5350' }">
                    {{ cameraOn ? '📹' : '📷' }}
                </button>

                <button @click="showWhiteboard = !showWhiteboard" class="control-btn"
                    :style="{ background: showWhiteboard ? '#4ecdc4' : 'rgba(255,255,255,0.1)', color: showWhiteboard ? '#1a1a2e' : '#fff' }">
                    📝
                </button>

                <button @click="showParticipants = !showParticipants" class="control-btn"
                    :style="{ background: showParticipants ? '#4ecdc4' : 'rgba(255,255,255,0.1)', color: showParticipants ? '#1a1a2e' : '#fff' }">
                    👥
                </button>

                <button @click="endSession" class="control-btn control-btn--danger">
                    {{ isTeacher ? 'End Session' : 'Leave' }}
                </button>
            </div>
        </template>
    </div>

    <style scoped>
        .video-root {
            position: relative;
            width: 100vw;
            height: 100vh;
            overflow: hidden;
            font-family: 'Nunito', sans-serif;
            background: #1a1a2e;
            color: #fff;
        }

        .state-screen {
            height: 100%;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            text-align: center;
            padding: 24px;
            gap: 12px;
        }

        .state-screen h2 {
            margin: 0;
            font-size: 28px;
            font-weight: 800;
        }

        .state-screen p {
            margin: 0;
            color: rgba(255, 255, 255, 0.76);
            font-size: 15px;
        }

        .state-screen--error h2 {
            color: #ff6b6b;
        }

        .jitsi-layer {
            position: absolute;
            inset: 0;
            width: 100%;
            height: 100%;
        }

        .top-strip {
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            z-index: 10;
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 12px 20px;
            background: rgba(0,0,0,0.5);
        }

        .participants-panel {
            position: absolute;
            right: 0;
            top: 0;
            bottom: 0;
            width: 280px;
            background: #16213e;
            border-left: 1px solid rgba(255,255,255,0.1);
            padding: 16px;
            overflow-y: auto;
            z-index: 11;
        }

        .whiteboard-overlay {
            position: absolute;
            inset: 0;
            z-index: 10;
            background: #fff;
            display: flex;
            flex-direction: column;
        }

        .controls-bar {
            position: absolute;
            left: 50%;
            bottom: 24px;
            transform: translateX(-50%);
            display: flex;
            gap: 14px;
            padding: 12px 18px;
            border-radius: 50px;
            background: rgba(10, 10, 25, 0.85);
            backdrop-filter: blur(20px);
            border: 1px solid rgba(255, 255, 255, 0.1);
            box-shadow: 0 20px 50px rgba(0, 0, 0, 0.38);
            z-index: 12;
            transition: transform 480ms ease, opacity 380ms ease;
        }

        .controls-bar--hidden {
            transform: translate(-50%, 120%);
            opacity: 0;
            pointer-events: none;
        }

        .control-btn {
            width: 52px;
            height: 52px;
            border-radius: 999px;
            border: 1px solid rgba(255, 255, 255, 0.18);
            background: rgba(255, 255, 255, 0.12);
            display: inline-flex;
            align-items: center;
            justify-content: center;
            color: #fff;
            cursor: pointer;
            font-size: 20px;
            transition: transform 320ms ease, background-color 220ms ease;
        }

        .control-btn:hover {
            transform: scale(1.12);
        }

        .control-btn--danger {
            background: rgba(220, 38, 38, 0.85);
            border-color: rgba(254, 202, 202, 0.35);
            font-size: 14px;
            font-weight: 700;
            width: auto;
            padding: 0 24px;
        }

        .control-btn--danger:hover {
            background: rgba(220, 38, 38, 1);
        }

        .slide-enter-active, .slide-leave-active {
            transition: transform 0.3s ease;
        }

        .slide-enter-from {
            transform: translateX(100%);
        }

        .slide-leave-to {
            transform: translateX(100%);
        }
    </style>
</template>
