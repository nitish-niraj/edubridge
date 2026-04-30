<script setup>
import axios from 'axios';
import { computed, nextTick, onMounted, onUnmounted, ref, watch } from 'vue';
import { usePage, Link } from '@inertiajs/vue3';
import { useAnalytics } from '@/composables/useAnalytics';

const props = defineProps({
    bookingId: {
        type: Number,
        required: true,
    },
});

const page = usePage();
const token = ref(null);
const roomName = ref('');
const identity = ref('');
const connected = ref(false);
const tooEarly = ref(false);
const startsIn = ref(0);
const muted = ref(false);
const cameraOff = ref(false);
const screenSharing = ref(false);
const elapsed = ref(0);
const participantName = ref('');
const expectedParticipantName = ref('Participant');
const error = ref(null);
const connectionError = ref(null);
const connectionState = ref('idle');
const loading = ref(true);
const joining = ref(false);

const showEndDialog = ref(false);
const controlsVisible = ref(true);
const timerPulse = ref(false);
const isTouchDevice = ref(typeof window !== 'undefined' && ('ontouchstart' in window || navigator.maxTouchPoints > 0));
const micBounce = ref(false);

const sessionDurationSeconds = ref(60 * 60);
const hasDurationMeta = ref(false);

const { trackEvent } = useAnalytics();

let room = null;
let timerInterval = null;
let controlsHideTimer = null;
let timerPulseTimeout = null;
let earlyRefreshTimer = null;
let localTracks = [];
let VideoModule = null;
let activeVideoTrack = null;
let screenTrack = null;
let screenShareRestorePromise = null;
let endingCall = false;
const remoteParticipantSids = ref([]);

const isTeacher = computed(() => identity.value.startsWith('teacher-'));

const hasRemoteParticipant = computed(() => remoteParticipantSids.value.length > 0);

const participantDisplayName = computed(() => {
    if (participantName.value) {
        const parsedIdentity = participantName.value.replace(/^(teacher|student)-/, '');
        if (/^\d+$/.test(parsedIdentity)) {
            return expectedParticipantName.value;
        }
        return parsedIdentity;
    }
    return expectedParticipantName.value;
});

const formatElapsed = computed(() => {
    const minutes = Math.floor(elapsed.value / 60);
    const seconds = elapsed.value % 60;
    return `${String(minutes).padStart(2, '0')}:${String(seconds).padStart(2, '0')}`;
});

const displaySeconds = computed(() => {
    if (hasDurationMeta.value && remainingSeconds.value !== null) {
        return remainingSeconds.value;
    }

    return elapsed.value;
});

const displayTime = computed(() => {
    const minutes = Math.floor(displaySeconds.value / 60);
    const seconds = displaySeconds.value % 60;
    return `${String(minutes).padStart(2, '0')}:${String(seconds).padStart(2, '0')}`;
});

const displayDigits = computed(() => displayTime.value.replace(':', '').split(''));

const timerProgress = computed(() => {
    if (!hasDurationMeta.value || remainingSeconds.value === null || sessionDurationSeconds.value <= 0) {
        return 1;
    }

    return Math.max(0, Math.min(1, remainingSeconds.value / sessionDurationSeconds.value));
});

const timerRingRadius = 38;
const timerRingCircumference = 2 * Math.PI * timerRingRadius;
const timerRingOffset = computed(() => timerRingCircumference * (1 - timerProgress.value));

const remainingSeconds = computed(() => {
    if (!hasDurationMeta.value) return null;
    return Math.max(sessionDurationSeconds.value - elapsed.value, 0);
});

const isFinalFiveMinutes = computed(() => {
    if (remainingSeconds.value === null) return false;
    return remainingSeconds.value > 0 && remainingSeconds.value <= 300;
});

const lobbySessionDetails = computed(() => {
    const durationText = hasDurationMeta.value
        ? `${Math.round(sessionDurationSeconds.value / 60)} min session`
        : 'Live session';

    return `Booking #${props.bookingId} • ${durationText}`;
});

const connectionStatus = computed(() => {
    if (connectionError.value) {
        return connectionError.value;
    }

    if (connectionState.value === 'reconnecting') {
        return 'Connection interrupted. Reconnecting...';
    }

    if (connectionState.value === 'disconnected') {
        return 'Disconnected from the room. Rejoin when you are ready.';
    }

    if (connectionState.value === 'connected' && screenSharing.value) {
        return 'Screen sharing is live.';
    }

    return '';
});

const connectionStatusClass = computed(() => ({
    'connection-banner--error': Boolean(connectionError.value) || connectionState.value === 'disconnected',
    'connection-banner--warning': connectionState.value === 'reconnecting',
}));

const resetControlsHideTimer = () => {
    window.clearTimeout(controlsHideTimer);
    controlsHideTimer = window.setTimeout(() => {
        controlsVisible.value = false;
    }, 3000);
};

const loadTwilioVideo = async () => {
    if (!VideoModule) {
        const module = await import('twilio-video');
        VideoModule = module.default || module;
    }

    return VideoModule;
};

const handleUserActivity = () => {
    if (!connected.value) return;
    controlsVisible.value = true;
    resetControlsHideTimer();
};

const fetchBookingMeta = async () => {
    try {
        const { data } = await axios.get(`/api/bookings/${props.bookingId}`);
        const currentUserId = page.props.auth?.user?.id;

        if (data.teacher_id === currentUserId) {
            expectedParticipantName.value = data.student?.name || 'Student';
        } else {
            expectedParticipantName.value = data.teacher?.name || 'Teacher';
        }

        const duration = Number(data?.slot?.duration_minutes || data?.video_session?.duration_minutes || 0);
        if (Number.isFinite(duration) && duration > 0) {
            sessionDurationSeconds.value = duration * 60;
            hasDurationMeta.value = true;
        }
    } catch {
        hasDurationMeta.value = false;
    }
};

const fetchToken = async () => {
    loading.value = true;
    error.value = null;

    try {
        const { data } = await axios.post(`/api/video-sessions/${props.bookingId}/token`);

        if (data.too_early) {
            tooEarly.value = true;
            startsIn.value = data.starts_in_minutes;
            loading.value = false;

            window.clearTimeout(earlyRefreshTimer);
            earlyRefreshTimer = window.setTimeout(fetchToken, Math.min(startsIn.value * 60000, 60000));
            return;
        }

        token.value = data.token;
        roomName.value = data.room_name;
        identity.value = data.identity;
        tooEarly.value = false;
    } catch (requestError) {
        error.value = requestError.response?.data?.message || 'Failed to get video token';
    } finally {
        loading.value = false;
    }
};

const refreshTokenForRejoin = async () => {
    const previousRoomName = roomName.value;

    await fetchToken();

    if (previousRoomName && roomName.value && roomName.value !== previousRoomName) {
        throw new Error('Unable to rejoin the original video room.');
    }
};

const attachRemoteTrack = (track) => {
    const container = document.getElementById('remote-video');
    if (!container) return;
    container.appendChild(track.attach());
};

const detachTrackElements = (track) => {
    track.detach().forEach((element) => element.remove());
};

const attachLocalVideoTrack = (track) => {
    const localContainer = document.getElementById('local-video');
    if (!localContainer || track.kind !== 'video') return;

    localContainer.querySelectorAll('video').forEach((element) => element.remove());
    localContainer.appendChild(track.attach());
};

const removeLocalTrack = (track, shouldStop = true) => {
    if (!track) return;

    if (room?.localParticipant) {
        room.localParticipant.unpublishTrack(track);
    }

    detachTrackElements(track);
    localTracks = localTracks.filter((localTrack) => localTrack !== track);

    if (activeVideoTrack === track) {
        activeVideoTrack = null;
    }

    if (shouldStop) {
        track.stop();
    }
};

const replaceLocalVideoTrack = async (nextTrack, { stopCurrent = true } = {}) => {
    if (activeVideoTrack) {
        removeLocalTrack(activeVideoTrack, stopCurrent);
    }

    activeVideoTrack = nextTrack;
    localTracks = [...localTracks.filter((track) => track.kind !== 'video'), nextTrack];

    if (room?.localParticipant) {
        await room.localParticipant.publishTrack(nextTrack);
    }

    if (cameraOff.value && !screenSharing.value) {
        nextTrack.disable();
    }

    attachLocalVideoTrack(nextTrack);
};

const restoreCameraTrack = async () => {
    if (screenShareRestorePromise) {
        return screenShareRestorePromise;
    }

    screenShareRestorePromise = (async () => {
        try {
            if (screenTrack) {
                removeLocalTrack(screenTrack);
                screenTrack = null;
            }

            screenSharing.value = false;

            const Video = await loadTwilioVideo();
            const cameraTrack = await Video.createLocalVideoTrack({ width: 1280 });
            await replaceLocalVideoTrack(cameraTrack, { stopCurrent: false });
            connectionError.value = null;
        } catch (restoreError) {
            activeVideoTrack = null;
            connectionError.value = `Screen sharing stopped, but camera could not restart: ${restoreError.message || restoreError}`;
        } finally {
            screenShareRestorePromise = null;
        }
    })();

    return screenShareRestorePromise;
};

const cleanupRoom = () => {
    if (screenTrack) {
        screenTrack.mediaStreamTrack.onended = null;
        screenTrack = null;
    }

    localTracks.forEach((track) => {
        detachTrackElements(track);
        track.stop();
    });

    localTracks = [];
    activeVideoTrack = null;
    room = null;
    screenSharing.value = false;
    remoteParticipantSids.value = [];
    participantName.value = '';
    window.clearInterval(timerInterval);
    timerInterval = null;
};

const addRemoteParticipant = (participant) => {
    if (!remoteParticipantSids.value.includes(participant.sid)) {
        remoteParticipantSids.value = [...remoteParticipantSids.value, participant.sid];
    }

    participantName.value = participant.identity;

    participant.tracks.forEach((publication) => {
        if (publication.track) {
            attachRemoteTrack(publication.track);
        }
    });

    participant.on('trackSubscribed', attachRemoteTrack);
    participant.on('trackUnsubscribed', detachTrackElements);
};

const removeRemoteParticipant = (participant) => {
    remoteParticipantSids.value = remoteParticipantSids.value.filter((sid) => sid !== participant.sid);

    participant.tracks.forEach((publication) => {
        if (publication.track) {
            detachTrackElements(publication.track);
        }
    });

    if (!remoteParticipantSids.value.length) {
        participantName.value = '';
    }
};

const connectToRoom = async () => {
    if (joining.value) return;

    try {
        joining.value = true;
        connectionError.value = null;

        if (connectionState.value === 'disconnected') {
            await refreshTokenForRejoin();
        }

        if (!token.value) return;

        connectionState.value = 'connecting';

        const Video = await loadTwilioVideo();

        const tracks = await Video.createLocalTracks({
            audio: true,
            video: { width: 1280 },
        });

        localTracks = tracks;
        activeVideoTrack = tracks.find((track) => track.kind === 'video') || null;
        room = await Video.connect(token.value, { name: roomName.value, tracks });
        connected.value = true;
        connectionState.value = 'connected';
        await nextTick();

        tracks.forEach((track) => {
            if (track.kind === 'video') {
                attachLocalVideoTrack(track);
            }
        });

        room.participants.forEach(addRemoteParticipant);
        room.on('participantConnected', addRemoteParticipant);
        room.on('participantDisconnected', removeRemoteParticipant);
        room.on('reconnecting', () => {
            connectionError.value = null;
            connectionState.value = 'reconnecting';
            controlsVisible.value = true;
        });
        room.on('reconnected', () => {
            connectionError.value = null;
            connectionState.value = 'connected';
        });
        room.on('disconnected', (_disconnectedRoom, disconnectError) => {
            const wasEndingCall = endingCall;
            cleanupRoom();
            connected.value = false;
            connectionState.value = wasEndingCall ? 'idle' : 'disconnected';

            if (!wasEndingCall && disconnectError) {
                connectionError.value = disconnectError.message || 'Disconnected from the video room.';
            }
        });

        if (!timerInterval) {
            timerInterval = window.setInterval(() => {
                elapsed.value += 1;
            }, 1000);
        }

        controlsVisible.value = true;
        resetControlsHideTimer();

        await axios.patch(`/api/video-sessions/${props.bookingId}/start`);
    } catch (connectError) {
        cleanupRoom();
        connected.value = false;
        connectionState.value = 'disconnected';
        connectionError.value = `Failed to connect: ${connectError.message || connectError}`;
    } finally {
        joining.value = false;
    }
};

const toggleMute = () => {
    muted.value = !muted.value;
    localTracks
        .filter((track) => track.kind === 'audio')
        .forEach((track) => (muted.value ? track.disable() : track.enable()));

    if (muted.value) {
        micBounce.value = true;
        window.setTimeout(() => {
            micBounce.value = false;
        }, 280);
    }
};

const toggleCamera = () => {
    cameraOff.value = !cameraOff.value;

    if (screenSharing.value) return;

    localTracks
        .filter((track) => track.kind === 'video')
        .forEach((track) => (cameraOff.value ? track.disable() : track.enable()));
};

const toggleScreenShare = async () => {
    if (screenSharing.value) {
        await restoreCameraTrack();
        return;
    }

    try {
        connectionError.value = null;

        const Video = await loadTwilioVideo();

        const stream = await navigator.mediaDevices.getDisplayMedia({ video: true });
        const displayTrack = stream.getVideoTracks()[0];
        if (!displayTrack) {
            throw new Error('No screen video track was selected.');
        }

        screenTrack = new Video.LocalVideoTrack(displayTrack, { name: 'screen-share' });
        screenSharing.value = true;
        displayTrack.onended = () => {
            void restoreCameraTrack();
        };

        await replaceLocalVideoTrack(screenTrack);
    } catch (shareError) {
        if (screenTrack) {
            removeLocalTrack(screenTrack);
            screenTrack = null;
        }

        screenSharing.value = false;
        connectionError.value = shareError?.name === 'NotAllowedError'
            ? 'Screen sharing was cancelled.'
            : `Unable to start screen sharing: ${shareError.message || shareError}`;
    }
};

const performEndCall = async () => {
    endingCall = true;

    try {
        if (isTeacher.value) {
            await axios.patch(`/api/video-sessions/${props.bookingId}/end`);
        }
    } catch {
        // Ignore end call API failure and continue local cleanup.
    }

    trackEvent('session_completed', {
        booking_id: props.bookingId,
        ended_by: isTeacher.value ? 'teacher' : 'student',
    });

    if (room) {
        room.disconnect();
    }

    cleanupRoom();
    window.clearInterval(timerInterval);

    window.location.href = isTeacher.value
        ? '/teacher/sessions'
        : '/student/bookings?session=completed';
};

const requestEndCall = () => {
    if (isTeacher.value) {
        showEndDialog.value = true;
        controlsVisible.value = true;
        return;
    }

    void performEndCall();
};

const confirmEndCall = async () => {
    showEndDialog.value = false;
    await performEndCall();
};

watch(elapsed, (nextElapsed) => {
    if (nextElapsed <= 0) return;
    if (nextElapsed % 60 !== 0) return;

    timerPulse.value = true;
    window.clearTimeout(timerPulseTimeout);
    timerPulseTimeout = window.setTimeout(() => {
        timerPulse.value = false;
    }, 360);
});

onMounted(async () => {
    await Promise.all([fetchBookingMeta(), fetchToken()]);
});

onUnmounted(() => {
    endingCall = true;

    if (room) {
        room.disconnect();
    }

    cleanupRoom();

    window.clearInterval(timerInterval);
    window.clearTimeout(controlsHideTimer);
    window.clearTimeout(timerPulseTimeout);
    window.clearTimeout(earlyRefreshTimer);
});
</script>

<template>
    <div
        class="video-root"
        @mousemove="handleUserActivity"
        @touchstart.passive="handleUserActivity"
        @click="handleUserActivity"
    >
        <div v-if="loading" class="state-screen">
            <h2>Loading session...</h2>
            <p>Preparing your live studio.</p>
        </div>

        <div v-else-if="tooEarly" class="state-screen">
            <h2>Session has not started yet</h2>
            <p>Starts in {{ startsIn }} minutes. This page refreshes automatically.</p>
        </div>

        <div v-else-if="error" class="state-screen state-screen--error">
            <h2>{{ error }}</h2>
            <Link :href="route('student.bookings')">Go back</Link>
        </div>

        <div v-else-if="!connected" class="state-screen">
            <h2>{{ connectionState === 'disconnected' ? 'Session disconnected' : 'Ready to Join?' }}</h2>
            <p>{{ connectionStatus || 'Step in when you are set. Camera and microphone will initialize automatically.' }}</p>
            <button type="button" class="join-button" :disabled="joining || loading" @click="connectToRoom">
                {{ joining ? 'Joining...' : connectionState === 'disconnected' ? 'Rejoin Session' : 'Join Session' }}
            </button>
        </div>

        <template v-else>
            <div
                v-if="connectionStatus"
                class="connection-banner"
                :class="connectionStatusClass"
                role="status"
                aria-live="polite"
            >
                {{ connectionStatus }}
            </div>

            <div id="remote-video" class="remote-video-layer" :class="{ 'remote-video-layer--active': hasRemoteParticipant }" />

            <div
                id="local-video"
                class="local-video-tile"
                :class="{
                    'local-video-tile--lobby': !hasRemoteParticipant,
                    'local-video-tile--pip': hasRemoteParticipant,
                    'is-sharing': screenSharing,
                }"
            >
                <span v-if="screenSharing && hasRemoteParticipant" class="sharing-badge">Sharing</span>
            </div>

            <div class="top-strip">
                <div
                    class="session-timer"
                    :class="{
                        'session-timer--pulse': timerPulse,
                        'session-timer--warning': isFinalFiveMinutes,
                    }"
                >
                    <svg class="session-timer-ring" viewBox="0 0 92 92" aria-hidden="true">
                        <circle class="session-timer-ring-track" cx="46" cy="46" :r="timerRingRadius" />
                        <circle
                            class="session-timer-ring-progress"
                            cx="46"
                            cy="46"
                            :r="timerRingRadius"
                            :stroke-dasharray="timerRingCircumference"
                            :stroke-dashoffset="timerRingOffset"
                        />
                    </svg>
                    <span class="timer-digits" aria-label="Session countdown">
                        <TransitionGroup name="flip-digit" tag="span" class="digit-group">
                            <span v-for="(digit, idx) in displayDigits" :key="`${idx}-${digit}`" class="digit-flap">{{ digit }}</span>
                        </TransitionGroup>
                        <span class="timer-colon">:</span>
                    </span>
                </div>
                <p class="session-partner">
                    {{ hasRemoteParticipant ? `Live with ${participantDisplayName}` : `Waiting for ${participantDisplayName}` }}
                </p>
            </div>

            <div v-if="!hasRemoteParticipant" class="lobby-meta">
                <p class="lobby-participant">{{ participantDisplayName }}</p>
                <p class="lobby-session">{{ lobbySessionDetails }}</p>
                <div class="waiting-wave" aria-hidden="true">
                    <span />
                    <span />
                    <span />
                </div>
            </div>

            <div class="controls-bar" :class="{ 'controls-bar--hidden': !controlsVisible }">
                <button
                    type="button"
                    class="control-btn"
                    :class="{ 'is-muted': muted, 'control-btn--bounce': micBounce }"
                    @click.stop="toggleMute"
                >
                    <span class="icon-stack">
                        <svg class="icon icon-mic" :class="{ 'is-visible': !muted }" viewBox="0 0 24 24" aria-hidden="true">
                            <path d="M12 3a3 3 0 0 0-3 3v5a3 3 0 0 0 6 0V6a3 3 0 0 0-3-3Zm-6 8a1 1 0 0 1 2 0a4 4 0 1 0 8 0a1 1 0 1 1 2 0a6 6 0 0 1-5 5.91V20h2a1 1 0 1 1 0 2H9a1 1 0 1 1 0-2h2v-3.09A6 6 0 0 1 6 11Z" />
                        </svg>
                        <svg class="icon icon-mic" :class="{ 'is-visible': muted }" viewBox="0 0 24 24" aria-hidden="true">
                            <path d="M5.71 4.29a1 1 0 0 0-1.42 1.42L9 10.41V11a3 3 0 0 0 4.68 2.49l1.6 1.6A5.98 5.98 0 0 1 12 16a6 6 0 0 1-6-6a1 1 0 1 0-2 0a8 8 0 0 0 7 7.94V20H9a1 1 0 1 0 0 2h6a1 1 0 1 0 0-2h-2v-2.06a7.95 7.95 0 0 0 3.7-1.39l2.59 2.59a1 1 0 0 0 1.42-1.42L5.71 4.29Z" />
                        </svg>
                    </span>
                </button>

                <button
                    type="button"
                    class="control-btn"
                    :class="{ 'is-muted': cameraOff }"
                    @click.stop="toggleCamera"
                >
                    <svg class="icon" viewBox="0 0 24 24" aria-hidden="true">
                        <path d="M4 7a3 3 0 0 1 3-3h7a3 3 0 0 1 3 3v1.17l2.59-1.3A1 1 0 0 1 21 7.76v8.48a1 1 0 0 1-1.41.89L17 15.83V17a3 3 0 0 1-3 3H7a3 3 0 0 1-3-3V7Z" />
                    </svg>
                </button>

                <button
                    v-if="!isTouchDevice"
                    type="button"
                    class="control-btn"
                    :class="{ 'is-sharing': screenSharing }"
                    @click.stop="toggleScreenShare"
                >
                    <svg class="icon" viewBox="0 0 24 24" aria-hidden="true">
                        <path d="M4 4a2 2 0 0 0-2 2v9a2 2 0 0 0 2 2h5l-1.3 1.3a1 1 0 1 0 1.4 1.4L12 17l2.9 2.7a1 1 0 1 0 1.4-1.4L15 17h5a2 2 0 0 0 2-2V6a2 2 0 0 0-2-2H4Zm0 2h16v9H4V6Z" />
                    </svg>
                </button>

                <button type="button" class="control-btn control-btn--danger" @click.stop="requestEndCall">
                    <svg class="icon" viewBox="0 0 24 24" aria-hidden="true">
                        <path d="M6.7 10.7a15.4 15.4 0 0 1 10.6 0l1.3-2.6A2 2 0 0 1 21.3 7L23 9.6a2 2 0 0 1-.3 2.5l-2.3 2.1a2 2 0 0 1-2.4.2l-1.7-1.1a13.2 13.2 0 0 0-8.6 0L6 14.4a2 2 0 0 1-2.4-.2l-2.3-2.1A2 2 0 0 1 1 9.6L2.7 7a2 2 0 0 1 2.7-.9l1.3 2.6Z" />
                    </svg>
                </button>
            </div>

            <transition name="end-dialog">
                <div v-if="showEndDialog" class="end-dialog-overlay" @click.self="showEndDialog = false">
                    <div class="end-dialog-card">
                        <h3>End session for everyone?</h3>
                        <p>This action closes the room for both participants immediately.</p>
                        <div class="end-dialog-actions">
                            <button type="button" class="end-dialog-cancel" @click="showEndDialog = false">Cancel</button>
                            <button type="button" class="end-dialog-confirm" @click="confirmEndCall">End Call</button>
                        </div>
                    </div>
                </div>
            </transition>
        </template>
    </div>
</template>

<style scoped>
.video-root {
    position: relative;
    width: 100vw;
    height: 100vh;
    overflow: hidden;
    font-family: 'Nunito', sans-serif;
    background:
        radial-gradient(circle at 18% 18%, rgba(91, 196, 229, 0.18), transparent 48%),
        radial-gradient(circle at 82% 20%, rgba(232, 85, 62, 0.15), transparent 45%),
        #050816;
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
    max-width: 480px;
}

.state-screen a {
    color: #93c5fd;
    text-decoration: none;
    font-weight: 700;
}

.state-screen--error h2 {
    color: #fecaca;
}

.join-button {
    margin-top: 8px;
    min-height: 52px;
    border: none;
    border-radius: 999px;
    padding: 0 34px;
    font-size: 16px;
    font-weight: 800;
    color: #fff;
    background: linear-gradient(135deg, #e8553e 0%, #ff8a6d 100%);
    box-shadow: 0 16px 32px rgba(232, 85, 62, 0.38);
    cursor: pointer;
}

.join-button:disabled {
    cursor: wait;
    opacity: 0.72;
}

.connection-banner {
    position: absolute;
    top: 118px;
    left: 50%;
    z-index: 14;
    transform: translateX(-50%);
    max-width: min(520px, calc(100vw - 32px));
    border-radius: 999px;
    padding: 10px 16px;
    background: rgba(12, 74, 110, 0.88);
    border: 1px solid rgba(125, 211, 252, 0.42);
    box-shadow: 0 18px 42px rgba(2, 6, 23, 0.32);
    color: #eff6ff;
    font-size: 13px;
    font-weight: 800;
    text-align: center;
}

.connection-banner--warning {
    background: rgba(146, 64, 14, 0.9);
    border-color: rgba(251, 191, 36, 0.5);
    color: #fffbeb;
}

.connection-banner--error {
    background: rgba(127, 29, 29, 0.92);
    border-color: rgba(254, 202, 202, 0.45);
    color: #fff1f2;
}

.remote-video-layer {
    position: absolute;
    inset: 0;
    opacity: 0;
    transform: scale(1.03);
    transition: opacity 420ms cubic-bezier(0.16, 1, 0.3, 1), transform 580ms cubic-bezier(0.34, 1.56, 0.64, 1);
    background: #020617;
}

.remote-video-layer--active {
    opacity: 1;
    transform: scale(1);
}

.local-video-tile {
    position: absolute;
    overflow: hidden;
    background: linear-gradient(145deg, #111827 0%, #1f2937 100%);
    border: 1px solid rgba(255, 255, 255, 0.2);
    box-shadow: 0 24px 60px rgba(0, 0, 0, 0.45);
    z-index: 8;
    transition:
        width 560ms cubic-bezier(0.34, 1.56, 0.64, 1),
        height 560ms cubic-bezier(0.34, 1.56, 0.64, 1),
        top 560ms cubic-bezier(0.34, 1.56, 0.64, 1),
        left 560ms cubic-bezier(0.34, 1.56, 0.64, 1),
        right 560ms cubic-bezier(0.34, 1.56, 0.64, 1),
        bottom 560ms cubic-bezier(0.34, 1.56, 0.64, 1),
        transform 560ms cubic-bezier(0.34, 1.56, 0.64, 1),
        border-radius 560ms cubic-bezier(0.34, 1.56, 0.64, 1);
}

.local-video-tile--lobby {
    width: min(72vw, 820px);
    aspect-ratio: 16 / 10;
    left: 50%;
    top: 50%;
    transform: translate(-50%, -60%);
    border-radius: 26px;
}

.local-video-tile--pip {
    width: 220px;
    height: 124px;
    right: 22px;
    bottom: 112px;
    top: auto;
    left: auto;
    transform: translate(0, 0);
    border-radius: 16px;
}

.local-video-tile.is-sharing::after {
    content: '';
    position: absolute;
    inset: 3px;
    border-radius: inherit;
    border: 2px dashed rgba(91, 196, 229, 0.7);
    animation: sharing-dash-spin 1.3s linear infinite;
    pointer-events: none;
}

.sharing-badge {
    position: absolute;
    left: 10px;
    top: 10px;
    z-index: 2;
    background: rgba(91, 196, 229, 0.88);
    color: #082f49;
    border-radius: 999px;
    font-size: 11px;
    font-weight: 800;
    letter-spacing: 0.08em;
    text-transform: uppercase;
    padding: 4px 8px;
    animation: sharing-badge-pulse 1.2s ease-in-out infinite;
}

.top-strip {
    position: absolute;
    top: 0;
    left: 0;
    right: 0;
    z-index: 10;
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 16px 20px;
    background: linear-gradient(to bottom, rgba(4, 6, 18, 0.72) 0%, rgba(4, 6, 18, 0.05) 100%);
}

.session-timer {
    position: relative;
    width: 92px;
    height: 92px;
    border-radius: 50%;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    color: #FFF8F0;
    background: rgba(15, 23, 42, 0.64);
    border: 1px solid rgba(255, 255, 255, 0.18);
    overflow: hidden;
}

.session-timer::before {
    content: '';
    position: absolute;
    inset: -8px;
    border-radius: inherit;
    background: radial-gradient(circle, rgba(217, 119, 6, 0.35) 0%, rgba(217, 119, 6, 0) 72%);
    opacity: 0;
    transform: scale(0.92);
}

.session-timer-ring {
    position: absolute;
    inset: 0;
    width: 92px;
    height: 92px;
    transform: rotate(-90deg);
}

.session-timer-ring-track,
.session-timer-ring-progress {
    fill: none;
    stroke-width: 5;
}

.session-timer-ring-track {
    stroke: rgba(255, 255, 255, 0.16);
}

.session-timer-ring-progress {
    stroke: #f5c518;
    stroke-linecap: round;
    transition: stroke-dashoffset 900ms linear;
}

.timer-digits {
    position: relative;
    z-index: 2;
    display: inline-flex;
    align-items: center;
    justify-content: center;
}

.digit-group {
    display: inline-flex;
    gap: 2px;
}

.digit-flap {
    width: 12px;
    text-align: center;
    font-size: 15px;
    font-weight: 800;
    line-height: 1;
    font-variant-numeric: tabular-nums;
    transform-origin: center top;
}

.timer-colon {
    position: absolute;
    left: 50%;
    transform: translateX(-50%);
    font-size: 15px;
    font-weight: 800;
    font-variant-numeric: tabular-nums;
}

.session-timer--warning {
    color: #fbbf24;
    border-color: rgba(251, 191, 36, 0.45);
}

.session-timer--warning .session-timer-ring-progress {
    stroke: #fb923c;
}

.session-timer--warning::before {
    animation: timer-halo 1.8s ease-in-out infinite;
}

.session-timer--pulse {
    animation: timer-minute-pulse 340ms cubic-bezier(0.34, 1.56, 0.64, 1);
}

.flip-digit-enter-active {
    animation: split-flap-in 240ms ease both;
}

.flip-digit-leave-active {
    position: absolute;
    animation: split-flap-out 220ms ease both;
}

.session-partner {
    margin: 0;
    font-size: 13px;
    color: rgba(255, 255, 255, 0.8);
}

.lobby-meta {
    position: absolute;
    left: 50%;
    bottom: 74px;
    transform: translateX(-50%);
    z-index: 9;
    text-align: center;
}

.lobby-participant {
    margin: 0;
    font-size: 24px;
    font-weight: 800;
    letter-spacing: 0.01em;
}

.lobby-session {
    margin: 7px 0 0;
    font-size: 14px;
    color: rgba(255, 255, 255, 0.72);
}

.waiting-wave {
    margin-top: 12px;
    display: inline-flex;
    gap: 8px;
    align-items: flex-end;
}

.waiting-wave span {
    width: 4px;
    height: 10px;
    border-radius: 999px;
    background: #5bc4e5;
    animation: waiting-wave 1.2s ease-in-out infinite;
}

.waiting-wave span:nth-child(2) {
    animation-delay: 0.12s;
}

.waiting-wave span:nth-child(3) {
    animation-delay: 0.24s;
}

.controls-bar {
    position: absolute;
    left: 50%;
    bottom: 24px;
    transform: translate(-50%, 0);
    display: flex;
    gap: 14px;
    padding: 12px 18px;
    border-radius: 50px;
    background: rgba(10, 10, 25, 0.65);
    backdrop-filter: blur(20px);
    -webkit-backdrop-filter: blur(20px);
    border: 1px solid rgba(255, 255, 255, 0.1);
    box-shadow: 0 20px 50px rgba(0, 0, 0, 0.38);
    z-index: 12;
    transition: transform 480ms cubic-bezier(0.34, 1.56, 0.64, 1), opacity 380ms ease;
    animation: controls-breathe 3.6s ease-in-out infinite;
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
    color: #FFF8F0;
    cursor: pointer;
    transition:
        transform 320ms cubic-bezier(0.34, 1.56, 0.64, 1),
        background-color 220ms ease,
        border-color 220ms ease;
}

.control-btn:hover {
    background: rgba(255, 255, 255, 0.22);
    transform: scale(1.12);
}

.control-btn.is-muted {
    background: rgba(232, 85, 62, 0.7);
    border-color: rgba(255, 255, 255, 0.34);
    transform: scale(1.05);
}

.control-btn.is-sharing {
    background: rgba(91, 196, 229, 0.55);
}

.control-btn--danger {
    background: rgba(220, 38, 38, 0.85);
    border-color: rgba(254, 202, 202, 0.35);
}

.control-btn--danger:hover {
    background: rgba(220, 38, 38, 1);
}

.control-btn--bounce {
    animation: control-bounce 280ms cubic-bezier(0.34, 1.56, 0.64, 1);
}

.icon {
    width: 24px;
    height: 24px;
    fill: currentColor;
}

.icon-stack {
    position: relative;
    width: 24px;
    height: 24px;
}

.icon-mic {
    position: absolute;
    inset: 0;
    opacity: 0;
    transition: opacity 180ms ease;
}

.icon-mic.is-visible {
    opacity: 1;
}

.end-dialog-overlay {
    position: absolute;
    inset: 0;
    z-index: 20;
    display: flex;
    align-items: center;
    justify-content: center;
    background:
        radial-gradient(circle at center, rgba(220, 38, 38, 0.02) 0%, rgba(220, 38, 38, 0.15) 100%),
        rgba(2, 6, 23, 0.5);
    padding: 20px;
}

.end-dialog-card {
    width: min(420px, 100%);
    border-radius: 20px;
    background: rgba(8, 14, 30, 0.9);
    border: 1px solid rgba(248, 113, 113, 0.32);
    box-shadow: 0 28px 68px rgba(2, 6, 23, 0.65);
    padding: 20px;
}

.end-dialog-card h3 {
    margin: 0;
    font-size: 22px;
    font-weight: 800;
}

.end-dialog-card p {
    margin: 8px 0 0;
    color: rgba(255, 255, 255, 0.76);
    font-size: 14px;
}

.end-dialog-actions {
    margin-top: 16px;
    display: flex;
    gap: 10px;
    justify-content: flex-end;
}

.end-dialog-cancel,
.end-dialog-confirm {
    min-height: 42px;
    border-radius: 12px;
    border: none;
    padding: 0 16px;
    cursor: pointer;
    font-weight: 800;
}

.end-dialog-cancel {
    background: rgba(148, 163, 184, 0.2);
    color: #e2e8f0;
}

.end-dialog-confirm {
    background: #dc2626;
    color: #fff;
}

.end-dialog-enter-active {
    animation: end-dialog-enter 360ms cubic-bezier(0.34, 1.56, 0.64, 1);
}

.end-dialog-leave-active {
    animation: end-dialog-leave 220ms ease;
}

:deep(#remote-video video),
:deep(#local-video video) {
    width: 100%;
    height: 100%;
    object-fit: cover;
}

@keyframes waiting-wave {
    0%,
    100% {
        transform: scaleY(0.5);
        opacity: 0.5;
    }

    50% {
        transform: scaleY(1.6);
        opacity: 1;
    }
}

@keyframes sharing-dash-spin {
    to {
        transform: rotate(360deg);
    }
}

@keyframes sharing-badge-pulse {
    0%,
    100% {
        transform: scale(1);
        opacity: 0.92;
    }

    50% {
        transform: scale(1.06);
        opacity: 1;
    }
}

@keyframes timer-minute-pulse {
    0% {
        transform: scale(1);
    }

    50% {
        transform: scale(1.02);
    }

    100% {
        transform: scale(1);
    }
}

@keyframes split-flap-out {
    from {
        opacity: 1;
        transform: rotateX(0deg);
    }

    to {
        opacity: 0;
        transform: rotateX(90deg);
    }
}

@keyframes split-flap-in {
    from {
        opacity: 0;
        transform: rotateX(-90deg);
    }

    to {
        opacity: 1;
        transform: rotateX(0deg);
    }
}

@keyframes timer-halo {
    0%,
    100% {
        opacity: 0.3;
        transform: scale(0.92);
    }

    50% {
        opacity: 0.65;
        transform: scale(1.08);
    }
}

@keyframes controls-breathe {
    0%,
    100% {
        box-shadow: 0 20px 50px rgba(0, 0, 0, 0.3);
    }

    50% {
        box-shadow: 0 24px 58px rgba(0, 0, 0, 0.44);
    }
}

@keyframes control-bounce {
    0% {
        transform: scale(1);
    }

    65% {
        transform: scale(1.08);
    }

    100% {
        transform: scale(1.05);
    }
}

@keyframes end-dialog-enter {
    0% {
        opacity: 0;
        transform: scale(0.85);
    }

    100% {
        opacity: 1;
        transform: scale(1);
    }
}

@keyframes end-dialog-leave {
    from {
        opacity: 1;
        transform: scale(1);
    }

    to {
        opacity: 0;
        transform: scale(0.94);
    }
}

@media (max-width: 900px) {
    .local-video-tile--lobby {
        width: min(90vw, 640px);
    }

    .local-video-tile--pip {
        width: 152px;
        height: 94px;
        right: 12px;
        bottom: 102px;
    }

    .lobby-meta {
        width: calc(100% - 34px);
        bottom: 80px;
    }

    .lobby-participant {
        font-size: 20px;
    }

    .controls-bar {
        bottom: 14px;
        gap: 10px;
        padding: 10px 12px;
    }

    .control-btn {
        width: 48px;
        height: 48px;
    }

    .top-strip {
        padding: 12px;
    }

    .connection-banner {
        top: 96px;
        width: calc(100vw - 28px);
        border-radius: 16px;
    }

    .session-partner {
        max-width: 52%;
        text-align: right;
    }
}
</style>
