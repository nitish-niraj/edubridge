<script setup>
import StudentLayout from '@/Layouts/StudentLayout.vue';
import axios from 'axios';
import EmptyState from '@/Components/Shared/EmptyState.vue';
import ErrorState from '@/Components/Shared/ErrorState.vue';
import { usePage } from '@inertiajs/vue3';
import { computed, nextTick, onBeforeUnmount, onMounted, ref, watch } from 'vue';
import { enforceMinimumDelay } from '@/composables/useMinimumDelay';

const props = defineProps({
    initialConversationId: {
        type: Number,
        default: null,
    },
});

const page = usePage();

const conversations = ref([]);
const activeConversation = ref(null);
const messages = ref([]);
const messageText = ref('');
const typingUsers = ref([]);
const isLoadingConversations = ref(false);
const isSending = ref(false);
const mobileView = ref('list');
const messagesContainer = ref(null);
const attachmentFile = ref(null);
const attachmentPreviewUrl = ref('');
const isDraggingAttachment = ref(false);
const conversationChannel = ref(null);
const presenceChannel = ref(null);
const lastTypingAt = ref(0);
const openMessageMenuId = ref(null);
const reportTargetMessage = ref(null);
const reportReason = ref('');
const reporting = ref(false);
const chatError = ref('');

const activeConversationId = computed(() => activeConversation.value?.id ?? null);
const showConversationSkeleton = computed(() => isLoadingConversations.value && conversations.value.length === 0);
const messageSkeletonPattern = ['left', 'right', 'left', 'right', 'left'];

const revokeAttachmentPreview = () => {
    if (attachmentPreviewUrl.value) {
        URL.revokeObjectURL(attachmentPreviewUrl.value);
        attachmentPreviewUrl.value = '';
    }
};

const assignAttachment = (file) => {
    if (!file) return;

    if (!/^image\/(png|jpe?g|webp)$/i.test(file.type)) {
        window.alert('Only PNG, JPG, and WEBP images are supported.');
        return;
    }

    if (file.size > 5 * 1024 * 1024) {
        window.alert('Image must be 5MB or smaller.');
        return;
    }

    revokeAttachmentPreview();
    attachmentFile.value = file;
    attachmentPreviewUrl.value = URL.createObjectURL(file);
};

const fetchConversations = async () => {
    const requestStartedAt = performance.now();

    chatError.value = '';
    isLoadingConversations.value = true;
    try {
        const response = await axios.get('/api/conversations');
        conversations.value = response.data.data ?? [];

        if (props.initialConversationId) {
            const matching = conversations.value.find((conversation) => conversation.id === Number(props.initialConversationId));
            if (matching) {
                await openConversation(matching);
                return;
            }
        }

        if (conversations.value.length > 0 && !activeConversation.value) {
            await openConversation(conversations.value[0]);
        }
    } catch (error) {
        chatError.value = error?.response?.data?.message || 'Messages are currently unavailable. Please try again shortly.';
        conversations.value = [];
        messages.value = [];
        activeConversation.value = null;
    } finally {
        await enforceMinimumDelay(requestStartedAt, 400);
        isLoadingConversations.value = false;
    }
};

const fetchMessages = async (conversationId) => {
    try {
        const response = await axios.get(`/api/conversations/${conversationId}/messages`);
        messages.value = [...(response.data.data ?? [])].reverse();
        await markAsRead(conversationId);
        await nextTick();
        scrollToBottom();
    } catch (error) {
        chatError.value = error?.response?.data?.message || 'Unable to load this conversation right now.';
        messages.value = [];
    }
};

const markAsRead = async (conversationId) => {
    await axios.patch(`/api/conversations/${conversationId}/read`);
};

const openConversation = async (conversation) => {
    activeConversation.value = conversation;
    mobileView.value = 'messages';
    await fetchMessages(conversation.id);
    subscribeToConversation(conversation.id);
    openMessageMenuId.value = null;
};

const scrollToBottom = () => {
    if (!messagesContainer.value) return;
    messagesContainer.value.scrollTop = messagesContainer.value.scrollHeight;
};

const sendMessage = async () => {
    if (!activeConversationId.value || isSending.value) return;
    if (!messageText.value.trim() && !attachmentFile.value) return;

    isSending.value = true;
    try {
        const formData = new FormData();
        if (attachmentFile.value) {
            formData.append('type', 'image');
            formData.append('attachment', attachmentFile.value);
            if (messageText.value.trim()) {
                formData.append('body', messageText.value.trim());
            }
        } else {
            formData.append('type', 'text');
            formData.append('body', messageText.value.trim());
        }

        const response = await axios.post(
            `/api/conversations/${activeConversationId.value}/messages`,
            formData,
            { headers: { 'Content-Type': 'multipart/form-data' } },
        );

        messages.value.push(response.data.data ?? response.data);
        messageText.value = '';
        attachmentFile.value = null;
        revokeAttachmentPreview();
        await nextTick();
        scrollToBottom();
        await fetchConversations();
    } finally {
        isSending.value = false;
    }
};

const onAttachmentChange = (event) => {
    const file = event.target.files?.[0] ?? null;
    if (!file) return;
    assignAttachment(file);
};

const clearAttachment = () => {
    attachmentFile.value = null;
    revokeAttachmentPreview();
};

const handleDragOver = (event) => {
    event.preventDefault();
    isDraggingAttachment.value = true;
};

const handleDragLeave = (event) => {
    event.preventDefault();
    const nextTarget = event.relatedTarget;
    if (nextTarget && event.currentTarget.contains(nextTarget)) {
        return;
    }
    isDraggingAttachment.value = false;
};

const handleDrop = (event) => {
    event.preventDefault();
    isDraggingAttachment.value = false;
    const file = event.dataTransfer?.files?.[0] ?? null;
    assignAttachment(file);
};

const subscribeToConversation = (conversationId) => {
    if (!window.Echo) return;
    unsubscribeFromConversation();

    conversationChannel.value = window.Echo.private(`conversation.${conversationId}`)
        .listen('MessageSent', async (payload) => {
            if (Number(payload.conversation_id) !== Number(conversationId)) return;

            messages.value.push(payload);
            await markAsRead(conversationId);
            await nextTick();
            scrollToBottom();
            await fetchConversations();
        });

    presenceChannel.value = window.Echo.join(`conversation.${conversationId}`)
        .listenForWhisper('typing', (payload) => {
            if (!payload?.name) return;
            typingUsers.value = [payload.name];
            setTimeout(() => {
                typingUsers.value = [];
            }, 1600);
        });
};

const unsubscribeFromConversation = () => {
    if (!window.Echo || !activeConversationId.value) return;
    window.Echo.leave(`conversation.${activeConversationId.value}`);
};

const emitTyping = () => {
    if (!presenceChannel.value) return;

    const now = Date.now();
    if (now - lastTypingAt.value < 2000) return;

    lastTypingAt.value = now;
    presenceChannel.value.whisper('typing', {
        name: 'Student',
    });
};

const openMessageActions = (message) => {
    openMessageMenuId.value = openMessageMenuId.value === message.id ? null : message.id;
};

const openReportMessage = (message) => {
    reportTargetMessage.value = message;
    reportReason.value = '';
    openMessageMenuId.value = null;
};

const closeReportModal = () => {
    reportTargetMessage.value = null;
    reportReason.value = '';
};

const submitReport = async () => {
    if (!reportTargetMessage.value || reporting.value || !reportReason.value.trim()) return;
    reporting.value = true;
    try {
        await axios.post('/api/reports', {
            type: 'message',
            reason: reportReason.value.trim(),
            reported_message_id: reportTargetMessage.value.id,
            reported_user_id: reportTargetMessage.value.sender_id,
        });
        closeReportModal();
        window.alert('Report submitted.');
    } finally {
        reporting.value = false;
    }
};

const currentUserId = computed(() => Number(page.props.auth?.user?.id || 0));

const messageDelay = (index) => `${Math.min(index, 6) * 50}ms`;

watch(messageText, () => {
    emitTyping();
});

const handleEscape = (event) => {
    if (event.key === 'Escape') {
        openMessageMenuId.value = null;
        if (reportTargetMessage.value) closeReportModal();
    }
};

onMounted(async () => {
    document.body.setAttribute('data-portal', 'student');
    window.addEventListener('keydown', handleEscape);
    await fetchConversations();
});

onBeforeUnmount(() => {
    window.removeEventListener('keydown', handleEscape);
    unsubscribeFromConversation();
    revokeAttachmentPreview();
});
</script>

<template>
    <StudentLayout>
        <div class="student-chat">
            <aside class="conversation-list" :class="{ mobileHidden: mobileView === 'messages' }">
                <h2>Messages</h2>

                <div v-if="showConversationSkeleton" class="conversation-skeleton-list">
                    <div v-for="index in 5" :key="index" class="conversation-row conversation-row--skeleton">
                        <div class="row-avatar skeleton skeleton-avatar"></div>
                        <div class="row-text">
                            <div class="skeleton skeleton-line skeleton-name"></div>
                            <div class="skeleton skeleton-line skeleton-preview"></div>
                        </div>
                    </div>
                </div>

                <TransitionGroup v-else name="conversation-list" tag="div" class="conversation-list-items">
                    <button
                        v-for="conversation in conversations"
                        :key="conversation.id"
                        class="conversation-row"
                        :class="{ active: activeConversationId === conversation.id }"
                        @click="openConversation(conversation)"
                    >
                        <img :src="conversation.display_avatar || '/favicon.ico'" loading="lazy" alt="Avatar" class="row-avatar" width="48" height="48" />
                        <div class="row-text">
                            <p class="name">{{ conversation.display_name }}</p>
                            <p class="preview">{{ conversation.last_message_preview }}</p>
                        </div>
                        <div class="row-meta">
                            <span class="time">{{ new Date(conversation.updated_at).toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' }) }}</span>
                            <transition name="badge-pop">
                                <span
                                    v-if="conversation.unread_count"
                                    :key="`badge-${conversation.id}-${conversation.unread_count}`"
                                    class="unread"
                                >
                                    {{ conversation.unread_count }}
                                </span>
                            </transition>
                        </div>
                    </button>
                </TransitionGroup>
            </aside>

            <section class="message-pane" :class="{ mobileHidden: mobileView === 'list' }">
                <div class="message-header">
                    <button class="back-btn" @click="mobileView = 'list'">←</button>
                    <h3>{{ activeConversation?.display_name || 'Select a conversation' }}</h3>
                </div>

                <div v-if="showConversationSkeleton" class="chat-message-skeleton">
                    <div
                        v-for="(side, index) in messageSkeletonPattern"
                        :key="`bubble-skeleton-${index}`"
                        class="chat-bubble-skeleton-row"
                        :class="side === 'right' ? 'chat-bubble-skeleton-row--right' : 'chat-bubble-skeleton-row--left'"
                    >
                        <div class="skeleton chat-bubble-skeleton" :class="side === 'right' ? 'chat-bubble-skeleton--right' : 'chat-bubble-skeleton--left'"></div>
                    </div>
                </div>

                <div v-else-if="chatError" class="messages-empty-wrap">
                    <ErrorState
                        code="503"
                        title="Messages are unavailable"
                        :message="chatError"
                        :show-back="false"
                    />
                </div>

                <div v-else-if="!isLoadingConversations && conversations.length === 0" class="messages-empty-wrap">
                    <EmptyState
                        illustration="messages"
                        title="No conversations yet"
                        body="Find a teacher and send them a message."
                        cta-text="Find teachers"
                        :cta-route="route('teachers.index')"
                    />
                </div>

                <div v-else ref="messagesContainer" class="messages-scroll">
                    <TransitionGroup name="message-flow" tag="div" class="messages-list">
                        <div
                            v-for="(message, index) in messages"
                            :key="message.id"
                            class="message-wrap"
                            :class="Number(message.sender_id) === currentUserId ? 'message-wrap--outgoing' : 'message-wrap--incoming'"
                            :style="{ '--msg-delay': messageDelay(index) }"
                        >
                            <div v-if="index === 0 || new Date(messages[index - 1].created_at).toDateString() !== new Date(message.created_at).toDateString()" class="time-divider">
                                {{ new Date(message.created_at).toLocaleDateString() }}
                            </div>

                            <div :class="Number(message.sender_id) === currentUserId ? 'bubble student' : 'bubble teacher'">
                                <img
                                    v-if="Number(message.sender_id) !== currentUserId"
                                    :src="message.sender?.avatar || '/favicon.ico'"
                                    loading="lazy"
                                    alt="Teacher avatar"
                                    class="mini-avatar"
                                    width="28"
                                    height="28"
                                />
                                <div class="bubble-body">
                                    <div class="bubble-top">
                                        <p v-if="message.body">{{ message.body }}</p>
                                        <button
                                            v-if="Number(message.sender_id) !== currentUserId"
                                            type="button"
                                            class="message-menu-trigger"
                                            @click.stop="openMessageActions(message)"
                                        >
                                            •••
                                        </button>
                                        <div v-if="openMessageMenuId === message.id" class="message-menu">
                                            <button type="button" @click="openReportMessage(message)">Report message</button>
                                        </div>
                                    </div>

                                    <a v-if="message.file_url" :href="message.file_url" target="_blank" rel="noreferrer">View image</a>

                                    <small class="meta">
                                        {{ new Date(message.created_at).toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' }) }}
                                        <span v-if="Number(message.sender_id) === currentUserId" class="read-receipt" :class="{ read: message.read_at }">
                                            <svg viewBox="0 0 16 16" class="tick tick-one" aria-hidden="true">
                                                <path d="M2.5 8.2l2.4 2.4 4.6-4.6" />
                                            </svg>
                                            <svg viewBox="0 0 16 16" class="tick tick-two" aria-hidden="true">
                                                <path d="M5.5 8.2l2.4 2.4 4.6-4.6" />
                                            </svg>
                                        </span>
                                    </small>
                                </div>
                            </div>
                        </div>
                    </TransitionGroup>

                    <div v-if="typingUsers.length" class="typing-indicator">
                        <span></span><span></span><span></span>
                    </div>
                </div>

                <div
                    v-if="!showConversationSkeleton && conversations.length"
                    class="input-bar"
                    :class="{ 'is-dragging': isDraggingAttachment }"
                    @dragover="handleDragOver"
                    @dragleave="handleDragLeave"
                    @drop="handleDrop"
                >
                    <div v-if="attachmentPreviewUrl" class="attachment-preview">
                        <div class="preview-polaroid">
                            <img :src="attachmentPreviewUrl" alt="Upload preview" />
                        </div>
                        <button type="button" class="remove-attachment" @click="clearAttachment">×</button>
                    </div>

                    <label class="clip-btn" aria-label="Attach image">
                        <span class="clip-icon">📎</span>
                        <input type="file" accept="image/png,image/jpeg,image/webp" hidden @change="onAttachmentChange" />
                    </label>
                    <input
                        v-model="messageText"
                        type="text"
                        class="message-input"
                        placeholder="Type a message..."
                        @keydown.enter.prevent="sendMessage"
                    />
                    <button class="send-btn" :disabled="isSending" @click="sendMessage">➤</button>
                </div>
            </section>
        </div>

        <div v-if="reportTargetMessage" class="modal-overlay" @click.self="closeReportModal">
            <div class="modal-card">
                <h3>Report message</h3>
                <p>Explain why this message should be reviewed.</p>
                <textarea v-model="reportReason" rows="4" class="modal-textarea" placeholder="Reason for report" />
                <div class="modal-actions">
                    <button class="secondary-button" type="button" @click="closeReportModal">Cancel</button>
                    <button class="primary-button" type="button" :disabled="reporting || !reportReason.trim()" @click="submitReport">
                        {{ reporting ? 'Submitting...' : 'Submit report' }}
                    </button>
                </div>
            </div>
        </div>
    </StudentLayout>
</template>

<style scoped>
.student-chat {
    min-height: 100vh;
    background: #fff8f0;
    display: grid;
    grid-template-columns: 30% 70%;
}

.conversation-list {
    background: #fff8f0;
    border-right: 1px solid #f0ddd5;
    padding: 14px;
}

h2 {
    margin: 0 0 12px;
    font-family: 'Fredoka One', cursive;
    color: #e8553e;
}

.conversation-list-items {
    display: flex;
    flex-direction: column;
    gap: 8px;
}

.conversation-row {
    width: 100%;
    display: grid;
    grid-template-columns: 48px minmax(0, 1fr) auto;
    gap: 10px;
    align-items: center;
    background: #fff;
    border: none;
    border-radius: 14px;
    padding: 10px;
    text-align: left;
    cursor: pointer;
}

.conversation-row--skeleton {
    cursor: default;
    border: 1px solid #f0ddd5;
    margin-bottom: 8px;
    grid-template-columns: 48px minmax(0, 1fr);
}

.conversation-row.active {
    background: #fff3ef;
    border-left: 3px solid #e8553e;
}

.row-avatar {
    width: 48px;
    height: 48px;
    border-radius: 50%;
    object-fit: cover;
}

.skeleton-avatar {
    border-radius: 50%;
}

.name {
    margin: 0;
    font-family: Nunito, sans-serif;
    font-size: 16px;
    font-weight: 700;
    color: #1f2937;
}

.preview {
    margin: 4px 0 0;
    font-family: Nunito, sans-serif;
    font-size: 14px;
    color: #6b7280;
}

.skeleton-name {
    width: 74%;
    margin-bottom: 8px;
}

.skeleton-preview {
    width: 92%;
}

.row-meta {
    text-align: right;
}

.time {
    display: block;
    color: #9ca3af;
    font-size: 12px;
}

.unread {
    display: inline-flex;
    width: 24px;
    height: 24px;
    border-radius: 50%;
    align-items: center;
    justify-content: center;
    background: #e8553e;
    color: #fff;
    font-size: 12px;
    font-weight: 700;
}

.message-pane {
    display: flex;
    flex-direction: column;
    min-height: 100vh;
}

.chat-message-skeleton {
    flex: 1;
    overflow: hidden;
    background: #fff;
    padding: 16px;
    display: flex;
    flex-direction: column;
    gap: 10px;
}

.chat-bubble-skeleton-row {
    display: flex;
}

.chat-bubble-skeleton-row--left {
    justify-content: flex-start;
}

.chat-bubble-skeleton-row--right {
    justify-content: flex-end;
}

.chat-bubble-skeleton {
    height: 34px;
    border-radius: 16px;
}

.chat-bubble-skeleton--left {
    width: clamp(120px, 46%, 270px);
}

.chat-bubble-skeleton--right {
    width: clamp(100px, 42%, 250px);
}

.messages-empty-wrap {
    flex: 1;
    overflow: auto;
    padding: 12px;
    background: #fff;
}

.message-header {
    display: flex;
    align-items: center;
    gap: 10px;
    padding: 14px;
    border-bottom: 1px solid #f1f5f9;
    background: #fff;
}

.message-header h3 {
    margin: 0;
    font-family: Nunito, sans-serif;
    font-size: 18px;
    font-weight: 700;
}

.back-btn {
    display: none;
    border: none;
    background: transparent;
    font-size: 20px;
}

.messages-scroll {
    flex: 1;
    overflow: auto;
    padding: 16px;
    background: #fff;
}

.messages-list {
    display: flex;
    flex-direction: column;
    gap: 10px;
}

.time-divider {
    text-align: center;
    color: #9ca3af;
    font-size: 12px;
    margin: 10px 0;
}

.message-wrap {
    margin-bottom: 10px;
}

.bubble {
    display: inline-flex;
    gap: 8px;
    max-width: 74%;
}

.bubble p {
    margin: 0;
}

.bubble.student {
    margin-left: auto;
    background: #e8553e;
    color: #fff;
    border-radius: 18px 18px 4px 18px;
    padding: 10px 12px;
}

.bubble.teacher {
    background: #fff;
    border: 1px solid #e8e8e8;
    color: #2d2d2d;
    border-radius: 18px 18px 18px 4px;
    padding: 10px 12px;
}

.bubble-body {
    position: relative;
    min-width: 0;
}

.bubble-top {
    display: flex;
    align-items: start;
    gap: 10px;
}

.message-menu-trigger {
    border: none;
    background: transparent;
    color: #9CA3AF;
    cursor: pointer;
    font-size: 14px;
    line-height: 1;
    padding: 0;
}

.message-menu {
    position: absolute;
    top: 20px;
    right: 0;
    z-index: 10;
    background: #fff;
    border: 1px solid #e5ebf3;
    border-radius: 12px;
    box-shadow: 0 18px 36px rgba(15, 23, 42, 0.14);
    overflow: hidden;
}

.message-menu button {
    min-width: 150px;
    min-height: 38px;
    padding: 0 12px;
    border: none;
    background: #fff;
    text-align: left;
    font: inherit;
    cursor: pointer;
}

.message-menu button:hover {
    background: #f8fbff;
}

.mini-avatar {
    width: 32px;
    height: 32px;
    border-radius: 50%;
    object-fit: cover;
}

.meta {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    margin-top: 6px;
    color: rgba(255, 255, 255, 0.75);
    font-size: 11px;
}

.teacher .meta {
    color: #9ca3af;
}

.read-receipt {
    display: inline-flex;
    align-items: center;
    gap: 1px;
}

.tick {
    width: 12px;
    height: 12px;
    fill: none;
    stroke: currentColor;
    stroke-width: 2;
    stroke-linecap: round;
    stroke-linejoin: round;
}

.tick-two path {
    stroke-dasharray: 14;
    stroke-dashoffset: 14;
}

.read-receipt.read .tick-two path {
    animation: tick-draw 280ms ease forwards;
}

.typing-indicator {
    display: inline-flex;
    gap: 6px;
    background: #fff;
    border: 1px solid #e5e7eb;
    border-radius: 999px;
    padding: 8px 10px;
}

.typing-indicator span {
    width: 8px;
    height: 8px;
    border-radius: 50%;
    background: #9ca3af;
    animation: typing-wave 950ms infinite;
}

.typing-indicator span:nth-child(2) { animation-delay: 120ms; }
.typing-indicator span:nth-child(3) { animation-delay: 240ms; }

.input-bar {
    position: relative;
    padding: 10px;
    border-top: 1px solid #f1f5f9;
    display: grid;
    grid-template-columns: auto minmax(0, 1fr) auto;
    gap: 8px;
    background: #fff;
    transition: border-color 0.25s var(--s-spring), box-shadow 0.25s var(--s-spring);
}

.input-bar.is-dragging {
    border-top-color: #e8553e;
    box-shadow: inset 0 0 0 2px #e8553e;
    border-style: dashed;
}

.attachment-preview {
    position: absolute;
    left: 12px;
    right: 12px;
    bottom: calc(100% + 8px);
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 10px;
}

.preview-polaroid {
    background: #fff;
    border-radius: 10px;
    padding: 8px 8px 14px;
    box-shadow: 0 16px 28px rgba(15, 23, 42, 0.2);
    transform: rotate(-3deg) scale(0.5);
    animation: polaroid-reveal 380ms var(--s-spring) forwards;
    transform-origin: center;
}

.preview-polaroid img {
    width: 80px;
    height: 80px;
    border-radius: 8px;
    object-fit: cover;
    display: block;
}

.remove-attachment {
    border: none;
    border-radius: 999px;
    width: 32px;
    height: 32px;
    background: #e8553e;
    color: #fff;
    font-size: 20px;
    line-height: 1;
    cursor: pointer;
}

.clip-btn,
.send-btn {
    border: none;
    background: #fff3ef;
    border-radius: 999px;
    width: 42px;
    height: 42px;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    cursor: pointer;
}

.clip-icon {
    transition: transform 0.25s var(--s-spring);
}

.input-bar.is-dragging .clip-icon {
    transform: scale(1.18);
}

.send-btn {
    background: #e8553e;
    color: #fff;
}

.message-input {
    border: 1px solid #f0ddd5;
    border-radius: 999px;
    padding: 0 14px;
    font-family: Nunito, sans-serif;
}

.modal-overlay {
    position: fixed;
    inset: 0;
    background: rgba(15, 23, 42, 0.38);
    z-index: 70;
    display: flex;
    align-items: center;
    justify-content: center;
    padding: 20px;
}

.modal-card {
    width: min(520px, 100%);
    background: #fff;
    border-radius: 20px;
    padding: 22px;
    box-shadow: 0 24px 80px rgba(15, 23, 42, 0.22);
}

.modal-card h3 {
    margin: 0 0 8px;
    font-family: 'Fredoka One', cursive;
    color: #e8553e;
}

.modal-card p {
    margin: 0 0 14px;
    font-family: Nunito, sans-serif;
    color: #475569;
}

.modal-textarea {
    width: 100%;
    border: 1px solid #f0ddd5;
    border-radius: 14px;
    padding: 12px 14px;
    font: inherit;
    resize: vertical;
    outline: none;
}

.modal-actions {
    margin-top: 14px;
    display: flex;
    gap: 10px;
}

.primary-button,
.secondary-button {
    min-height: 42px;
    padding: 0 14px;
    border-radius: 14px;
    border: 1px solid transparent;
    font-size: 13px;
    font-weight: 800;
    cursor: pointer;
}

.primary-button {
    background: #e8553e;
    color: #fff;
}

.secondary-button {
    background: #fff;
    color: #2D2D2D;
    border-color: #f0ddd5;
}

.primary-button:disabled {
    opacity: 0.6;
    cursor: not-allowed;
}

.conversation-list-enter-active,
.conversation-list-leave-active {
    transition: transform 260ms var(--s-spring), opacity 220ms ease;
}

.conversation-list-enter-from,
.conversation-list-leave-to {
    opacity: 0;
    transform: translateY(10px);
}

.conversation-list-move {
    transition: transform 280ms var(--s-spring);
}

.badge-pop-enter-active {
    animation: badge-pop 220ms var(--s-spring);
}

.badge-pop-leave-active {
    transition: opacity 0.15s ease;
}

.badge-pop-leave-to {
    opacity: 0;
}

.message-flow-enter-active {
    transition: transform 250ms var(--s-spring), opacity 250ms var(--s-spring);
    transition-delay: var(--msg-delay);
}

.message-flow-leave-active {
    transition: opacity 150ms ease;
}

.message-flow-enter-from.message-wrap--incoming {
    opacity: 0;
    transform: translateX(-20px) scale(0.94);
}

.message-flow-enter-from.message-wrap--outgoing {
    opacity: 0;
    transform: translateX(20px) scale(0.94);
}

.message-flow-leave-to {
    opacity: 0;
}

@keyframes typing-wave {
    0%, 100% {
        transform: scale(0.6);
        background: #9ca3af;
    }

    50% {
        transform: scale(1.2);
        background: #e8553e;
    }
}

@keyframes badge-pop {
    0% {
        transform: scale(0);
    }

    80% {
        transform: scale(1.12);
    }

    100% {
        transform: scale(1);
    }
}

@keyframes tick-draw {
    from {
        stroke-dashoffset: 14;
    }

    to {
        stroke-dashoffset: 0;
    }
}

@keyframes polaroid-reveal {
    0% {
        opacity: 0;
        transform: rotate(-3deg) scale(0.5);
    }

    75% {
        opacity: 1;
        transform: rotate(1deg) scale(1.04);
    }

    100% {
        opacity: 1;
        transform: rotate(0deg) scale(1);
    }
}

@media (max-width: 900px) {
    .student-chat {
        grid-template-columns: 1fr;
    }

    .mobileHidden {
        display: none;
    }

    .back-btn {
        display: inline-block;
    }

    .bubble {
        max-width: 88%;
    }
}
</style>
