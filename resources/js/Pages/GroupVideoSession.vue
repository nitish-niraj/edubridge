<script setup>
import { ref, nextTick, onMounted, onUnmounted, computed, watch } from 'vue';
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
const showSidebar = ref(false);
const activeTab = ref('participants'); // 'participants' or 'chat'
const showWhiteboard = ref(false);
const controlsVisible = ref(true);
const screenSharing = ref(false);
const recordingRequesting = ref(false);
const isRecording = ref(false);
const showConsentModal = ref(false);
const consentModalTeacherName = ref('');
const consentList = ref([]); // Teacher checks student responses here

// Controls
const cameraOn = ref(true);
const micOn = ref(true);
const timer = ref(0);
let timerInterval = null;
let jitsiApi = null;
let controlsHideTimer = null;

// Participants (from Jitsi)
const participants = ref([]);

// Whiteboard Drawing State
const whiteboardElements = ref([]);
const whiteboardCanvas = ref(null);
const isDrawing = ref(false);
const currentStroke = ref(null);
const strokeColor = ref('#E8553E');
const strokeWidth = ref(3);
const hasDrawPermission = ref(false);
let whiteboardDebounceTimer = null;

// Raise hand
const raisedHands = ref([]);
const myHandRaised = ref(false);

// Pusher presence channel (whispers for raise-hand ack and recording consent)
const presenceChannel = ref(null);

// Chat state
const chatMessages = ref([]);
const chatText = ref('');
const chatLoading = ref(false);
const chatSending = ref(false);
const chatError = ref('');
const chatScroll = ref(null);
const pinnedAnnouncementMsg = ref(null);

const myClassMember = computed(() => {
    return groupInfo.value?.active_class_members?.find(m => m.user_id === user.value?.id);
});

watch(myClassMember, (newVal) => {
    if (newVal) {
        hasDrawPermission.value = newVal.can_draw;
    }
}, { immediate: true });

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

const fetchGroupInfo = async () => {
    const { data: gData } = await axios.get(`/api/groups/${props.conversationId}`);
    groupInfo.value = gData;
    isTeacher.value = gData.teacher_id === user.value?.id;
};

onMounted(async () => {
    try {
        await fetchGroupInfo();

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

        // Load chat + pinned announcement
        await fetchChatMessages();
        await fetchPinnedAnnouncement();

        window.addEventListener('resize', resizeCanvas);
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
            screenSharingStatusChanged: (e) => {
                screenSharing.value = e.on;
            },
        });
    } catch (e) {
        error.value = 'Failed to connect to video room: ' + e.message;
    }
};

const setupPusherListeners = () => {
    if (!window.Echo) return;

    // Listen to DrawPermissionChanged on user-specific private channel
    window.Echo.private(`App.Models.User.${user.value.id}`)
        .listen('.DrawPermissionChanged', (event) => {
            if (Number(event.conversation_id) === Number(props.conversationId)) {
                hasDrawPermission.value = event.can_draw;
            }
        });

    // Listen to WhiteboardUpdate
    window.Echo.private(`conversation.${props.conversationId}`)
        .listen('WhiteboardUpdate', (event) => {
            if (event.sender_id !== user.value?.id) {
                whiteboardElements.value = event.elements;
                drawElementsOnCanvas();
            }
        });

    // Listen to GroupHandRaised
    window.Echo.private(`conversation.${props.conversationId}`)
        .listen('GroupHandRaised', (event) => {
            if (event.raised) {
                if (!raisedHands.value.some(h => h.id === event.userId)) {
                    raisedHands.value.push({ id: event.userId, name: event.name });
                    if (isTeacher.value) {
                        playDing();
                    }
                }
            } else {
                raisedHands.value = raisedHands.value.filter(h => h.id !== event.userId);
            }
        });

    // Listen to RecordingConsentRequest
    window.Echo.private(`conversation.${props.conversationId}`)
        .listen('RecordingConsentRequest', (event) => {
            if (!isTeacher.value) {
                showConsentModal.value = true;
                consentModalTeacherName.value = event.teacher_name || event.teacherName || 'Your teacher';
            }
        });

    // Listen to MessageSent (in-session chat)
    window.Echo.private(`conversation.${props.conversationId}`)
        .listen('MessageSent', (payload) => {
            if (!chatMessages.value.some(m => m.id === payload.id)) {
                chatMessages.value.push(payload);
                nextTick(scrollToChatBottom);
            }
        });

    // Listen to GroupSessionEnded
    window.Echo.private(`conversation.${props.conversationId}`)
        .listen('.GroupSessionEnded', () => {
            alert('The teacher has ended this group session.');
            window.location.href = isTeacher.value
                ? `/teacher/classes/${props.conversationId}`
                : '/student/dashboard';
        });

    // Join Echo presence channel for client-to-client whispers
    presenceChannel.value = window.Echo.join(`conversation.${props.conversationId}`)
        .joining((joiningUser) => {
            // track joining
        })
        .leaving((leavingUser) => {
            raisedHands.value = raisedHands.value.filter(h => h.id !== leavingUser.id);
            consentList.value = consentList.value.filter(c => c.id !== leavingUser.id);
        })
        .listenForWhisper('acknowledge-hand', (payload) => {
            if (payload.studentId === user.value?.id) {
                myHandRaised.value = false;
                axios.post(`/api/video-sessions/group/${props.conversationId}/raise-hand`, {
                    raised: false
                }).catch(() => {});
            }
        })
        .listenForWhisper('recording-consent-response', (payload) => {
            if (isTeacher.value) {
                const existing = consentList.value.find(c => c.id === payload.userId);
                if (existing) {
                    existing.status = payload.consent ? 'agreed' : 'denied';
                } else {
                    consentList.value.push({
                        id: payload.userId,
                        name: payload.name,
                        status: payload.consent ? 'agreed' : 'denied'
                    });
                }
                const totalStudents = participants.value.length;
                const agreedStudents = consentList.value.filter(c => c.status === 'agreed').length;
                if (agreedStudents === totalStudents && totalStudents > 0) {
                    alert('All connected students have consented. You can start recording now.');
                }
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

const toggleScreenShare = () => {
    if (jitsiApi) {
        jitsiApi.executeCommand('toggleShareScreen');
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
const raiseHand = async () => {
    myHandRaised.value = !myHandRaised.value;
    try {
        await axios.post(`/api/video-sessions/group/${props.conversationId}/raise-hand`, {
            raised: myHandRaised.value
        });
        if (myHandRaised.value) {
            playDing();
        }
    } catch (e) {
        myHandRaised.value = !myHandRaised.value; // revert
        alert('Failed to raise hand.');
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
    if (presenceChannel.value) {
        presenceChannel.value.whisper('acknowledge-hand', { studentId });
    }
};

// Whiteboard Collaborative Canvas
const getCoords = (e) => {
    const canvas = whiteboardCanvas.value;
    if (!canvas) return [0, 0];
    const rect = canvas.getBoundingClientRect();
    
    let clientX, clientY;
    if (e.touches && e.touches.length > 0) {
        clientX = e.touches[0].clientX;
        clientY = e.touches[0].clientY;
    } else {
        clientX = e.clientX;
        clientY = e.clientY;
    }
    
    return [
        clientX - rect.left,
        clientY - rect.top
    ];
};

const startDrawing = (e) => {
    if (!isTeacher.value && !hasDrawPermission.value) return;
    const canvas = whiteboardCanvas.value;
    if (!canvas) return;
    const [x, y] = getCoords(e);
    isDrawing.value = true;
    currentStroke.value = {
        type: 'path',
        points: [[x, y]],
        color: strokeColor.value,
        width: strokeWidth.value,
    };
};

const draw = (e) => {
    if (!isDrawing.value || !currentStroke.value) return;
    const [x, y] = getCoords(e);
    currentStroke.value.points.push([x, y]);
    
    const canvas = whiteboardCanvas.value;
    const ctx = canvas?.getContext('2d');
    if (ctx && currentStroke.value.points.length > 1) {
        const points = currentStroke.value.points;
        const p1 = points[points.length - 2];
        const p2 = points[points.length - 1];
        ctx.beginPath();
        ctx.strokeStyle = currentStroke.value.color;
        ctx.lineWidth = currentStroke.value.width;
        ctx.lineCap = 'round';
        ctx.lineJoin = 'round';
        ctx.moveTo(p1[0], p1[1]);
        ctx.lineTo(p2[0], p2[1]);
        ctx.stroke();
    }
};

const stopDrawing = () => {
    if (!isDrawing.value || !currentStroke.value) return;
    isDrawing.value = false;
    
    whiteboardElements.value.push(currentStroke.value);
    if (whiteboardElements.value.length > 500) {
        whiteboardElements.value = whiteboardElements.value.slice(-500);
    }
    currentStroke.value = null;
    sendWhiteboardUpdate(whiteboardElements.value);
};

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
            /* fail-safe */
        }
    }, 150);
};

const drawElementsOnCanvas = () => {
    const canvas = whiteboardCanvas.value;
    if (!canvas) return;
    const ctx = canvas.getContext('2d');
    if (!ctx) return;

    ctx.clearRect(0, 0, canvas.width, canvas.height);

    whiteboardElements.value.forEach((el) => {
        if (el.type === 'path' && el.points && el.points.length > 0) {
            ctx.beginPath();
            ctx.strokeStyle = el.color || '#E8553E';
            ctx.lineWidth = el.width || 3;
            ctx.lineCap = 'round';
            ctx.lineJoin = 'round';

            const [startX, startY] = el.points[0];
            ctx.moveTo(startX, startY);

            for (let i = 1; i < el.points.length; i++) {
                const [x, y] = el.points[i];
                ctx.lineTo(x, y);
            }
            ctx.stroke();
        }
    });
};

const resizeCanvas = () => {
    const canvas = whiteboardCanvas.value;
    if (!canvas) return;
    const rect = canvas.parentElement.getBoundingClientRect();
    canvas.width = rect.width || 800;
    canvas.height = rect.height || 600;
    drawElementsOnCanvas();
};

const clearWhiteboard = () => {
    if (!confirm('Clear whiteboard for everyone?')) return;
    whiteboardElements.value = [];
    drawElementsOnCanvas();
    sendWhiteboardUpdate([]);
};

watch(showWhiteboard, async (visible) => {
    if (visible) {
        await nextTick();
        resizeCanvas();
    }
});

// Sidebar Tabs Toggle
const toggleSidebar = (tab) => {
    if (showSidebar.value && activeTab.value === tab) {
        showSidebar.value = false;
    } else {
        showSidebar.value = true;
        activeTab.value = tab;
        if (tab === 'chat') {
            nextTick(scrollToChatBottom);
        }
    }
};

// Chat Functions
const fetchChatMessages = async () => {
    chatLoading.value = true;
    try {
        const { data } = await axios.get(`/api/conversations/${props.conversationId}/messages`);
        chatMessages.value = [...(data.data ?? [])].reverse();
        nextTick(scrollToChatBottom);
    } catch (e) {
        chatError.value = 'Failed to load chat.';
    } finally {
        chatLoading.value = false;
    }
};

const fetchPinnedAnnouncement = async () => {
    try {
        const { data } = await axios.get(`/api/conversations/${props.conversationId}/pinned-announcement`);
        pinnedAnnouncementMsg.value = data;
    } catch (e) {
        /* fail-safe */
    }
};

const sendChatMessage = async () => {
    if (!chatText.value.trim() || chatSending.value) return;
    chatSending.value = true;
    try {
        const { data } = await axios.post(`/api/conversations/${props.conversationId}/messages`, {
            body: chatText.value.trim(),
            type: 'text'
        });
        chatMessages.value.push(data.data || data);
        chatText.value = '';
        nextTick(scrollToChatBottom);
    } catch (e) {
        alert(e.response?.data?.message || 'Failed to send message.');
    } finally {
        chatSending.value = false;
    }
};

const deleteChatMessage = async (msgId) => {
    if (!confirm('Delete this message?')) return;
    try {
        await axios.delete(`/api/conversations/${props.conversationId}/messages/${msgId}`);
        chatMessages.value = chatMessages.value.filter(m => m.id !== msgId);
    } catch (e) {
        alert(e.response?.data?.message || 'Failed to delete message.');
    }
};

const canDeleteMessage = (msg) => {
    if (isTeacher.value) return true;
    if (msg.sender_id !== user.value?.id) return false;
    
    // Within 10 minutes
    const diff = (new Date() - new Date(msg.created_at)) / 60000;
    return diff < 10;
};

const scrollToChatBottom = () => {
    if (chatScroll.value) {
        chatScroll.value.scrollTop = chatScroll.value.scrollHeight;
    }
};

// Recording Consent
const requestRecordingConsent = async () => {
    recordingRequesting.value = true;
    try {
        await axios.post(`/api/video-sessions/${sessionId.value}/recording/consent`, {
            conversation_id: props.conversationId
        });
        alert('Consent request broadcasted to all students.');
        consentList.value = participants.value.map(p => ({ id: p.id, name: p.displayName, status: 'pending' }));
    } catch (e) {
        alert(e.response?.data?.message || 'Failed to request recording consent.');
    } finally {
        recordingRequesting.value = false;
    }
};

const sendConsentResponse = (agreed) => {
    showConsentModal.value = false;
    if (presenceChannel.value) {
        presenceChannel.value.whisper('recording-consent-response', {
            userId: user.value.id,
            name: user.value.name,
            consent: agreed
        });
    }
};

const toggleRecording = () => {
    if (!isRecording.value) {
        if (jitsiApi) {
            jitsiApi.executeCommand('startRecording', { mode: 'file' });
            isRecording.value = true;
            alert('Recording session...');
        }
    } else {
        if (jitsiApi) {
            jitsiApi.executeCommand('stopRecording');
            isRecording.value = false;
            alert('Recording stopped.');
        }
    }
};

const toggleDrawAccess = async (userId) => {
    try {
        const { data } = await axios.patch(`/api/groups/${props.conversationId}/members/${userId}/draw`);
        // Refresh local group info to update status indicator
        await fetchGroupInfo();
    } catch (e) {
        alert('Failed to update draw permission.');
    }
};

const toggleMuteStudent = async (userId) => {
    try {
        await axios.patch(`/api/groups/${props.conversationId}/members/${userId}/mute`);
        await fetchGroupInfo();
    } catch (e) {
        alert('Failed to update mute status.');
    }
};

onUnmounted(() => {
    clearInterval(timerInterval);
    clearTimeout(controlsHideTimer);
    clearTimeout(whiteboardDebounceTimer);
    window.removeEventListener('resize', resizeCanvas);

    if (jitsiApi) {
        try {
            jitsiApi.dispose();
        } catch {
            /* ignore */
        }
        jitsiApi = null;
    }
    if (window.Echo) {
        window.Echo.leave(`conversation.${props.conversationId}`);
        if (user.value?.id) {
            window.Echo.leave(`App.Models.User.${user.value.id}`);
        }
        if (presenceChannel.value) {
            try {
                presenceChannel.value.leave();
            } catch {
                /* ignore */
            }
            presenceChannel.value = null;
        }
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
            <div class="loader-ring"></div>
            <h2>Connecting to session...</h2>
            <p>Preparing your group studio.</p>
        </div>

        <!-- Error -->
        <div v-else-if="error" class="state-screen state-screen--error">
            <div style="font-size: 64px; margin-bottom: 16px;">😕</div>
            <h2>{{ error }}</h2>
            <Link
                :href="isTeacher ? `/teacher/classes/${props.conversationId}` : '/student/dashboard'"
                class="error-back-btn"
            >
                Return Dashboard
            </Link>
        </div>

        <!-- Video session -->
        <template v-else>
            <!-- Top bar -->
            <div class="top-strip">
                <div style="display: flex; align-items: center; gap: 12px;">
                    <span style="font-size: 20px;">🏫</span>
                    <span style="font-weight: 700; font-size: 16px; text-shadow: 0 2px 4px rgba(0,0,0,0.5);">{{ groupInfo?.title || 'Group Class' }}</span>
                    <span class="joined-badge">
                        {{ participants.length + 1 }} joined
                    </span>
                    <span v-if="isRecording" class="recording-badge-active">🔴 Recording</span>
                </div>
                <div style="display: flex; align-items: center; gap: 16px;">
                    <span class="timer-display">⏱ {{ formatTime(timer) }}</span>
                </div>
            </div>

            <!-- Jitsi Meet iframe -->
            <div id="jitsi-container" class="jitsi-layer" />

            <!-- Collapsible Sidebar (Participants & Chat) -->
            <transition name="slide">
                <div v-if="showSidebar" class="sidebar-panel">
                    <div class="sidebar-tabs">
                        <button 
                            @click="activeTab = 'participants'" 
                            class="tab-btn" 
                            :class="{ active: activeTab === 'participants' }"
                        >
                            👥 Members ({{ participants.length + 1 }})
                        </button>
                        <button 
                            @click="activeTab = 'chat'; nextTick(scrollToChatBottom)" 
                            class="tab-btn" 
                            :class="{ active: activeTab === 'chat' }"
                        >
                            💬 Chat
                        </button>
                    </div>

                    <!-- Sidebar Content -->
                    <div class="sidebar-content">
                        <!-- Participants Tab -->
                        <div v-if="activeTab === 'participants'" class="tab-pane">
                            <!-- Raised hands queue -->
                            <div v-if="raisedHands.length" class="raised-hands-section">
                                <div class="section-title">🙋 Raised Hands</div>
                                <div v-for="h in raisedHands" :key="h.id" class="hand-row">
                                    <span>{{ h.name }}</span>
                                    <button v-if="isTeacher" @click="acknowledgeHand(h.id)" class="ack-btn">
                                        Acknowledge
                                    </button>
                                </div>
                            </div>

                            <!-- Consent list for teacher -->
                            <div v-if="isTeacher && consentList.length" class="raised-hands-section consent-section">
                                <div class="section-title">🔴 Recording Consent Check</div>
                                <div v-for="c in consentList" :key="c.id" class="hand-row">
                                    <span>{{ c.name }}</span>
                                    <span :class="`consent-status ${c.status}`">{{ c.status.toUpperCase() }}</span>
                                </div>
                            </div>

                            <!-- List -->
                            <div class="member-list">
                                <div class="member-row current-user">
                                    <div class="avatar-col">
                                        {{ (user?.name || '?')[0].toUpperCase() }}
                                    </div>
                                    <div class="info-col">
                                        <span class="m-name">You ({{ isTeacher ? 'Teacher' : 'Student' }})</span>
                                    </div>
                                </div>

                                <div v-for="p in groupInfo?.active_class_members?.filter(m => m.user_id !== user.value?.id)" :key="p.id" class="member-row">
                                    <div class="avatar-col">
                                        {{ (p.user?.name || '?')[0].toUpperCase() }}
                                    </div>
                                    <div class="info-col">
                                        <span class="m-name">{{ p.user?.name }}</span>
                                        <span class="m-role">{{ p.role.toUpperCase() }}</span>
                                    </div>
                                    <!-- Teacher Controls -->
                                    <div v-if="isTeacher && p.role === 'student'" class="controls-col">
                                        <button @click="toggleDrawAccess(p.user_id)" :title="p.can_draw ? 'Revoke draw access' : 'Grant draw access'" :class="{ active: p.can_draw }" class="m-ctrl-btn">
                                            ✏️
                                        </button>
                                        <button @click="toggleMuteStudent(p.user_id)" :title="p.is_muted ? 'Unmute' : 'Mute'" :class="{ active: p.is_muted }" class="m-ctrl-btn">
                                            {{ p.is_muted ? '🔇' : '🔊' }}
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Chat Tab -->
                        <div v-if="activeTab === 'chat'" class="tab-pane chat-pane">
                            <!-- Pinned announcement -->
                            <div v-if="pinnedAnnouncementMsg" class="pinned-banner">
                                <span class="pin-title">📢 Announcement:</span>
                                <p class="pin-body">{{ pinnedAnnouncementMsg.body }}</p>
                            </div>

                            <div ref="chatScroll" class="chat-messages-container">
                                <div v-for="msg in chatMessages" :key="msg.id" class="chat-bubble-wrap" :class="{ outgoing: msg.sender_id === user.id }">
                                    <div class="msg-meta">
                                        <span class="sender-name">{{ msg.sender?.name || 'User' }}</span>
                                        <span v-if="msg.type === 'announcement'" class="announcement-badge">Announcement</span>
                                    </div>
                                    <div class="msg-bubble">
                                        <p>{{ msg.body }}</p>
                                        <button v-if="canDeleteMessage(msg)" @click="deleteChatMessage(msg.id)" class="del-msg-btn" title="Delete message">×</button>
                                    </div>
                                </div>
                            </div>

                            <div class="chat-input-row">
                                <input 
                                    v-model="chatText" 
                                    type="text" 
                                    :placeholder="myClassMember?.is_muted ? 'You are muted by the teacher' : 'Type a message...'" 
                                    :disabled="myClassMember?.is_muted"
                                    @keydown.enter="sendChatMessage"
                                    class="c-input"
                                />
                                <button @click="sendChatMessage" :disabled="chatSending || myClassMember?.is_muted" class="c-send-btn">Send</button>
                            </div>
                        </div>
                    </div>
                </div>
            </transition>

            <!-- Whiteboard Canvas Overlay -->
            <transition name="fade">
                <div v-show="showWhiteboard" class="whiteboard-overlay">
                    <div class="whiteboard-header">
                        <div class="wb-title">📝 Shared Whiteboard</div>
                        <div class="wb-toolbar">
                            <!-- Colors -->
                            <div class="palette">
                                <button 
                                    v-for="color in ['#E8553E', '#4ecdc4', '#ffc107', '#1a1a2e', '#4caf50', '#2196f3']" 
                                    :key="color"
                                    @click="strokeColor = color"
                                    class="color-dot"
                                    :style="{ background: color, border: strokeColor === color ? '2px solid #fff' : 'none' }"
                                />
                            </div>
                            <!-- Sizes -->
                            <input type="range" min="1" max="15" v-model.number="strokeWidth" class="width-slider" title="Brush Width" />
                            
                            <button v-if="isTeacher || hasDrawPermission" @click="clearWhiteboard" class="wb-clear-btn">Clear Canvas</button>
                            <button @click="showWhiteboard = false" class="wb-close-btn">Close Board</button>
                        </div>
                    </div>
                    
                    <div class="canvas-container">
                        <canvas 
                            ref="whiteboardCanvas" 
                            @mousedown="startDrawing"
                            @mousemove="draw"
                            @mouseup="stopDrawing"
                            @mouseleave="stopDrawing"
                            @touchstart="startDrawing"
                            @touchmove="draw"
                            @touchend="stopDrawing"
                        />
                        <div v-if="!isTeacher && !hasDrawPermission" class="read-only-badge">
                            👁️ Read-only (Ask teacher for draw access)
                        </div>
                    </div>
                </div>
            </transition>

            <!-- Student Consent Modal -->
            <transition name="fade">
                <div v-if="showConsentModal" class="consent-modal-overlay">
                    <div class="consent-card">
                        <div style="font-size: 40px;">🔴</div>
                        <h3>Consent to Recording</h3>
                        <p>{{ consentModalTeacherName }} requests consent to record this live session for note sharing and review.</p>
                        <div class="consent-actions">
                            <button @click="sendConsentResponse(false)" class="decline-btn">Decline</button>
                            <button @click="sendConsentResponse(true)" class="agree-btn">I Consent</button>
                        </div>
                    </div>
                </div>
            </transition>

            <!-- Controls bar -->
            <div class="controls-bar" :class="{ 'controls-bar--hidden': !controlsVisible }">
                <!-- Raise hand (students only) -->
                <button v-if="!isTeacher" @click="raiseHand" class="control-btn"
                    :style="{ background: myHandRaised ? '#ffc107' : 'rgba(255,255,255,0.1)', color: myHandRaised ? '#1a1a2e' : '#fff' }">
                    🙋
                </button>

                <!-- Recording control (teacher only) -->
                <button v-if="isTeacher" @click="requestRecordingConsent" :disabled="recordingRequesting" class="control-btn" title="Request recording consent from students">
                    📢 Request Consent
                </button>

                <button v-if="isTeacher" @click="toggleRecording" class="control-btn" :style="{ background: isRecording ? '#ef5350' : 'rgba(255,255,255,0.1)' }" title="Toggle Recording">
                    🔴 Record
                </button>

                <button @click="toggleMic" class="control-btn"
                    :style="{ background: micOn ? 'rgba(255,255,255,0.1)' : '#ef5350' }">
                    {{ micOn ? '🎤' : '🔇' }}
                </button>

                <button @click="toggleCamera" class="control-btn"
                    :style="{ background: cameraOn ? 'rgba(255,255,255,0.1)' : '#ef5350' }">
                    {{ cameraOn ? '📹' : '📷' }}
                </button>

                <button @click="toggleScreenShare" class="control-btn"
                    :style="{ background: screenSharing ? '#4ecdc4' : 'rgba(255,255,255,0.1)', color: screenSharing ? '#1a1a2e' : '#fff' }">
                    🖥️
                </button>

                <button @click="showWhiteboard = !showWhiteboard" class="control-btn"
                    :style="{ background: showWhiteboard ? '#4ecdc4' : 'rgba(255,255,255,0.1)', color: showWhiteboard ? '#1a1a2e' : '#fff' }">
                    📝
                </button>

                <button @click="toggleSidebar('participants')" class="control-btn"
                    :style="{ background: (showSidebar && activeTab === 'participants') ? '#4ecdc4' : 'rgba(255,255,255,0.1)', color: (showSidebar && activeTab === 'participants') ? '#1a1a2e' : '#fff' }">
                    👥
                </button>

                <button @click="toggleSidebar('chat')" class="control-btn"
                    :style="{ background: (showSidebar && activeTab === 'chat') ? '#4ecdc4' : 'rgba(255,255,255,0.1)', color: (showSidebar && activeTab === 'chat') ? '#1a1a2e' : '#fff' }">
                    💬
                </button>

                <button @click="endSession" class="control-btn control-btn--danger">
                    {{ isTeacher ? 'End Session' : 'Leave' }}
                </button>
            </div>
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

    .loader-ring {
        border: 4px solid rgba(255,255,255,0.1);
        width: 60px;
        height: 60px;
        border-radius: 50%;
        border-left-color: #4ecdc4;
        animation: spin 1s linear infinite;
        margin-bottom: 20px;
    }

    @keyframes spin {
        0% { transform: rotate(0deg); }
        100% { transform: rotate(360deg); }
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

    .error-back-btn {
        background: #ef5350;
        color: white;
        padding: 10px 24px;
        border-radius: 20px;
        text-decoration: none;
        font-weight: bold;
        transition: transform 0.2s ease;
    }

    .error-back-btn:hover {
        transform: scale(1.05);
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
        padding: 16px 24px;
        background: linear-gradient(180deg, rgba(0,0,0,0.7) 0%, rgba(0,0,0,0) 100%);
    }

    .joined-badge {
        background: #4ecdc4;
        color: #1a1a2e;
        padding: 4px 12px;
        border-radius: 12px;
        font-size: 12px;
        font-weight: 700;
    }

    .recording-badge-active {
        background: #ef5350;
        color: white;
        padding: 4px 12px;
        border-radius: 12px;
        font-size: 12px;
        font-weight: 700;
        animation: pulse 1.5s infinite;
    }

    @keyframes pulse {
        0%, 100% { opacity: 1; }
        50% { opacity: 0.5; }
    }

    .timer-display {
        font-size: 15px;
        color: #fff;
        background: rgba(0,0,0,0.4);
        padding: 6px 12px;
        border-radius: 12px;
        font-variant-numeric: tabular-nums;
    }

    /* Sidebar drawer */
    .sidebar-panel {
        position: absolute;
        right: 0;
        top: 0;
        bottom: 0;
        width: 320px;
        background: rgba(22, 33, 62, 0.95);
        backdrop-filter: blur(10px);
        border-left: 1px solid rgba(255,255,255,0.1);
        display: flex;
        flex-direction: column;
        z-index: 11;
        box-shadow: -10px 0 30px rgba(0,0,0,0.5);
    }

    .sidebar-tabs {
        display: flex;
        border-bottom: 1px solid rgba(255,255,255,0.1);
    }

    .tab-btn {
        flex: 1;
        background: none;
        border: none;
        padding: 16px 8px;
        color: rgba(255,255,255,0.6);
        cursor: pointer;
        font-weight: bold;
        font-size: 13px;
        transition: color 0.2s, border-bottom 0.2s;
    }

    .tab-btn.active {
        color: #4ecdc4;
        border-bottom: 2px solid #4ecdc4;
        background: rgba(255,255,255,0.03);
    }

    .sidebar-content {
        flex: 1;
        overflow-y: auto;
        padding: 16px;
    }

    .tab-pane {
        height: 100%;
        display: flex;
        flex-direction: column;
    }

    .raised-hands-section {
        background: rgba(255, 193, 7, 0.12);
        border: 1px solid rgba(255, 193, 7, 0.2);
        border-radius: 10px;
        padding: 12px;
        margin-bottom: 16px;
    }

    .section-title {
        font-size: 13px;
        font-weight: 700;
        color: #ffc107;
        margin-bottom: 8px;
    }

    .hand-row {
        display: flex;
        justify-content: space-between;
        align-items: center;
        padding: 6px 0;
        font-size: 14px;
    }

    .consent-status {
        font-size: 11px;
        padding: 2px 6px;
        border-radius: 8px;
        font-weight: bold;
    }
    .consent-status.pending { background: #e0e0e0; color: #333; }
    .consent-status.agreed { background: #4caf50; color: white; }
    .consent-status.denied { background: #ef5350; color: white; }

    .ack-btn {
        background: #4ecdc4;
        border: none;
        color: #1a1a2e;
        padding: 4px 10px;
        border-radius: 6px;
        font-size: 11px;
        cursor: pointer;
        font-weight: 700;
    }

    .member-list {
        display: flex;
        flex-direction: column;
        gap: 12px;
    }

    .member-row {
        display: flex;
        align-items: center;
        gap: 12px;
        padding: 8px 12px;
        background: rgba(255,255,255,0.03);
        border-radius: 10px;
    }

    .avatar-col {
        width: 32px;
        height: 32px;
        background: #4ecdc4;
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 14px;
        font-weight: 700;
        color: #1a1a2e;
    }

    .info-col {
        flex: 1;
        display: flex;
        flex-direction: column;
    }

    .m-name {
        font-size: 14px;
    }

    .m-role {
        font-size: 10px;
        color: #888;
    }

    .controls-col {
        display: flex;
        gap: 6px;
    }

    .m-ctrl-btn {
        background: rgba(255,255,255,0.1);
        border: none;
        border-radius: 50%;
        width: 28px;
        height: 28px;
        cursor: pointer;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 12px;
    }

    .m-ctrl-btn.active {
        background: #4ecdc4;
    }

    /* Chat Tab Layout */
    .chat-pane {
        display: flex;
        flex-direction: column;
        height: 100%;
    }

    .pinned-banner {
        background: rgba(78, 205, 196, 0.15);
        border-left: 3px solid #4ecdc4;
        padding: 8px 12px;
        border-radius: 6px;
        margin-bottom: 12px;
    }

    .pin-title {
        font-size: 12px;
        font-weight: bold;
        color: #4ecdc4;
    }

    .pin-body {
        margin: 4px 0 0;
        font-size: 13px;
        color: #ddd;
    }

    .chat-messages-container {
        flex: 1;
        overflow-y: auto;
        display: flex;
        flex-direction: column;
        gap: 12px;
        padding-right: 4px;
        margin-bottom: 12px;
        max-height: calc(100vh - 200px);
    }

    .chat-bubble-wrap {
        display: flex;
        flex-direction: column;
        max-width: 85%;
    }

    .chat-bubble-wrap.outgoing {
        align-self: flex-end;
    }

    .msg-meta {
        font-size: 10px;
        color: #888;
        margin-bottom: 2px;
        display: flex;
        align-items: center;
        gap: 6px;
    }

    .announcement-badge {
        background: rgba(255,193,7,0.2);
        color: #ffc107;
        padding: 1px 4px;
        border-radius: 4px;
    }

    .msg-bubble {
        background: rgba(255,255,255,0.06);
        padding: 10px 12px;
        border-radius: 12px;
        position: relative;
    }

    .chat-bubble-wrap.outgoing .msg-bubble {
        background: #FFF3EF;
        color: #1a1a2e;
    }

    .msg-bubble p {
        margin: 0;
        font-size: 13.5px;
        line-height: 1.4;
    }

    .del-msg-btn {
        position: absolute;
        top: -6px;
        right: -6px;
        background: #ef5350;
        color: white;
        border: none;
        border-radius: 50%;
        width: 16px;
        height: 16px;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 10px;
        cursor: pointer;
    }

    .chat-input-row {
        display: flex;
        gap: 8px;
    }

    .c-input {
        flex: 1;
        background: rgba(255,255,255,0.06);
        border: 1px solid rgba(255,255,255,0.1);
        border-radius: 20px;
        padding: 8px 16px;
        color: white;
        font-size: 13.5px;
    }

    .c-send-btn {
        background: #4ecdc4;
        border: none;
        color: #1a1a2e;
        padding: 8px 16px;
        border-radius: 20px;
        font-weight: bold;
        cursor: pointer;
    }

    /* Whiteboard overlay CSS */
    .whiteboard-overlay {
        position: absolute;
        inset: 0;
        z-index: 10;
        background: #fff;
        display: flex;
        flex-direction: column;
    }

    .whiteboard-header {
        padding: 12px 20px;
        background: #f5f5f5;
        display: flex;
        justify-content: space-between;
        align-items: center;
        border-bottom: 1px solid #ddd;
    }

    .wb-title {
        font-weight: 800;
        color: #1a1a2e;
        font-size: 16px;
    }

    .wb-toolbar {
        display: flex;
        align-items: center;
        gap: 16px;
    }

    .palette {
        display: flex;
        gap: 6px;
    }

    .color-dot {
        width: 24px;
        height: 24px;
        border-radius: 50%;
        cursor: pointer;
        outline: none;
    }

    .width-slider {
        width: 100px;
    }

    .wb-clear-btn {
        background: #ffc107;
        color: #1a1a2e;
        border: none;
        padding: 6px 14px;
        border-radius: 8px;
        cursor: pointer;
        font-weight: bold;
    }

    .wb-close-btn {
        background: #ef5350;
        color: #fff;
        border: none;
        padding: 6px 14px;
        border-radius: 8px;
        cursor: pointer;
        font-weight: bold;
    }

    .canvas-container {
        flex: 1;
        position: relative;
        overflow: hidden;
        background: #fafafa;
    }

    .canvas-container canvas {
        display: block;
        width: 100%;
        height: 100%;
        cursor: crosshair;
    }

    .read-only-badge {
        position: absolute;
        top: 20px;
        left: 50%;
        transform: translateX(-50%);
        background: rgba(0,0,0,0.8);
        color: white;
        padding: 8px 16px;
        border-radius: 20px;
        font-size: 13px;
        pointer-events: none;
        box-shadow: 0 4px 10px rgba(0,0,0,0.3);
    }

    /* Consent Modal */
    .consent-modal-overlay {
        position: absolute;
        inset: 0;
        background: rgba(0,0,0,0.75);
        backdrop-filter: blur(5px);
        display: flex;
        align-items: center;
        justify-content: center;
        z-index: 100;
    }

    .consent-card {
        background: #1a1a2e;
        border: 1px solid rgba(255,255,255,0.1);
        border-radius: 24px;
        padding: 32px;
        max-width: 400px;
        text-align: center;
        box-shadow: 0 20px 50px rgba(0,0,0,0.5);
    }

    .consent-card h3 {
        margin: 16px 0 8px;
        font-size: 20px;
    }

    .consent-card p {
        color: #aaa;
        font-size: 14px;
        line-height: 1.5;
        margin-bottom: 24px;
    }

    .consent-actions {
        display: flex;
        gap: 12px;
    }

    .consent-actions .decline-btn {
        flex: 1;
        background: rgba(255,255,255,0.1);
        border: none;
        color: white;
        padding: 12px;
        border-radius: 12px;
        cursor: pointer;
        font-weight: bold;
    }

    .consent-actions .agree-btn {
        flex: 1;
        background: #ef5350;
        border: none;
        color: white;
        padding: 12px;
        border-radius: 12px;
        cursor: pointer;
        font-weight: bold;
    }

    /* Controls bar */
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
        min-width: 52px;
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
        padding: 0 10px;
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

    /* Transitions */
    .slide-enter-active, .slide-leave-active {
        transition: transform 0.3s ease;
    }

    .slide-enter-from, .slide-leave-to {
        transform: translateX(100%);
    }

    .fade-enter-active, .fade-leave-active {
        transition: opacity 0.3s ease;
    }

    .fade-enter-from, .fade-leave-to {
        opacity: 0;
    }
</style>
