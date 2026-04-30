<script setup>
import TeacherLayout from '@/Layouts/TeacherLayout.vue';
import axios from 'axios';
import { Head, usePage } from '@inertiajs/vue3';
import { computed, nextTick, onBeforeUnmount, onMounted, ref } from 'vue';

const props = defineProps({
    initialConversationId: {
        type: Number,
        default: null,
    },
});

const page = usePage();
const authUserId = computed(() => Number(page.props?.auth?.user?.id || 0));

const conversations = ref([]);
const activeConversation = ref(null);
const messages = ref([]);
const messageText = ref('');
const bannerMessage = ref('');
const messageContainer = ref(null);
const viewMode = ref('list');
const subscribedConversationId = ref(null);

const loadingConversations = ref(false);
const loadingMessages = ref(false);
const conversationError = ref('');
const messagesError = ref('');
const sending = ref(false);

const activeConversationId = computed(() => activeConversation.value?.id ?? null);

const showBanner = (text) => {
    bannerMessage.value = text;
    setTimeout(() => {
        bannerMessage.value = '';
    }, 2200);
};

const formatDate = (value) => {
    if (!value) return '-';
    return new Date(value).toLocaleDateString();
};

const formatTime = (value) => {
    if (!value) return '';
    return new Date(value).toLocaleTimeString(undefined, {
        hour: '2-digit',
        minute: '2-digit',
    });
};

const unsubscribe = () => {
    if (!window.Echo || !subscribedConversationId.value) {
        return;
    }

    window.Echo.leave(`conversation.${subscribedConversationId.value}`);
    subscribedConversationId.value = null;
};

const fetchConversations = async () => {
    loadingConversations.value = true;
    conversationError.value = '';

    try {
        const response = await axios.get('/api/conversations');
        conversations.value = response.data.data ?? [];

        if (props.initialConversationId && !activeConversation.value) {
            const matching = conversations.value.find((conversation) => conversation.id === Number(props.initialConversationId));
            if (matching) {
                await openConversation(matching);
            }
        }
    } catch (error) {
        conversationError.value = error?.response?.data?.message || 'Unable to load conversations right now.';
    } finally {
        loadingConversations.value = false;
    }
};

const fetchMessages = async (conversationId) => {
    loadingMessages.value = true;
    messagesError.value = '';

    try {
        const response = await axios.get(`/api/conversations/${conversationId}/messages`);
        messages.value = [...(response.data.data ?? [])].reverse();
        await axios.patch(`/api/conversations/${conversationId}/read`);
        await nextTick();
        scrollToBottom();
    } catch (error) {
        messages.value = [];
        messagesError.value = error?.response?.data?.message || 'Unable to load messages for this conversation.';
    } finally {
        loadingMessages.value = false;
    }
};

const subscribe = (conversationId) => {
    if (!window.Echo) return;

    unsubscribe();
    subscribedConversationId.value = conversationId;

    window.Echo.private(`conversation.${conversationId}`).listen('MessageSent', async (payload) => {
        if (Number(payload.conversation_id) !== Number(conversationId)) {
            return;
        }

        messages.value.push(payload);
        await axios.patch(`/api/conversations/${conversationId}/read`);
        await nextTick();
        scrollToBottom();
        await fetchConversations();
    });
};

const openConversation = async (conversation) => {
    activeConversation.value = conversation;
    viewMode.value = 'messages';
    await fetchMessages(conversation.id);
    subscribe(conversation.id);
};

const sendMessage = async () => {
    if (!activeConversationId.value || sending.value) {
        return;
    }

    const body = messageText.value.trim();
    if (!body) {
        return;
    }

    sending.value = true;

    try {
        const response = await axios.post(`/api/conversations/${activeConversationId.value}/messages`, {
            type: 'text',
            body,
        });

        messages.value.push(response.data.data ?? response.data);
        messageText.value = '';
        await nextTick();
        scrollToBottom();
        await fetchConversations();
        showBanner('Message sent successfully.');
    } catch (error) {
        showBanner(error?.response?.data?.message || 'Message could not be sent.');
    } finally {
        sending.value = false;
    }
};

const scrollToBottom = () => {
    if (!messageContainer.value) return;
    messageContainer.value.scrollTop = messageContainer.value.scrollHeight;
};

const isOwnMessage = (message) => Number(message.sender_id) === authUserId.value;

const isDateBreak = (index) => {
    if (index === 0) return true;
    const current = new Date(messages.value[index].created_at).toDateString();
    const previous = new Date(messages.value[index - 1].created_at).toDateString();
    return current !== previous;
};

onMounted(async () => {
    document.body.setAttribute('data-portal', 'teacher');
    await fetchConversations();
});

onBeforeUnmount(() => {
    unsubscribe();
});
</script>

<template>
    <Head title="Teacher Chat" />

    <TeacherLayout page-title="Chat">
        <div class="teacher-chat-page">
            <div v-if="bannerMessage" class="banner">{{ bannerMessage }}</div>

            <section v-if="viewMode === 'list'" class="panel list-view">
                <div class="panel-header">
                    <h1>Student messages</h1>
                    <button type="button" class="secondary-btn" @click="fetchConversations">Refresh</button>
                </div>

                <p class="helper-copy">Open a conversation to read messages and reply in real time.</p>

                <div v-if="loadingConversations" class="state-card">Loading conversations...</div>
                <div v-else-if="conversationError" class="state-card error">
                    <p>{{ conversationError }}</p>
                    <button type="button" class="secondary-btn" @click="fetchConversations">Try again</button>
                </div>
                <div v-else-if="!conversations.length" class="state-card empty">
                    No conversations yet. Chats will appear here when students or parents message you.
                </div>

                <button
                    v-for="conversation in conversations"
                    v-else
                    :key="conversation.id"
                    class="conversation-item"
                    type="button"
                    @click="openConversation(conversation)"
                >
                    <div>
                        <p class="student-name">{{ conversation.display_name || 'Conversation' }}</p>
                        <p class="item-preview">{{ conversation.last_message_preview || 'No messages yet' }}</p>
                    </div>
                    <div class="item-side">
                        <p class="item-date">{{ formatDate(conversation.updated_at) }}</p>
                        <span v-if="conversation.unread_count" class="badge">{{ conversation.unread_count }}</span>
                    </div>
                </button>
            </section>

            <section v-else class="panel messages-view">
                <div class="header">
                    <button class="secondary-btn" type="button" @click="viewMode = 'list'">Back</button>
                    <h2>{{ activeConversation?.display_name || 'Conversation' }}</h2>
                    <button class="secondary-btn" type="button" @click="fetchMessages(activeConversation.id)">Refresh</button>
                </div>

                <div ref="messageContainer" class="messages-scroll">
                    <div v-if="loadingMessages" class="state-card">Loading messages...</div>
                    <div v-else-if="messagesError" class="state-card error">{{ messagesError }}</div>
                    <div v-else-if="!messages.length" class="state-card empty">No messages in this conversation yet.</div>

                    <template v-else v-for="(message, index) in messages" :key="message.id">
                        <div v-if="isDateBreak(index)" class="date-separator">
                            {{ formatDate(message.created_at) }}
                        </div>

                        <article class="chat-bubble" :class="isOwnMessage(message) ? 'mine' : 'theirs'">
                            <div class="message-sender">
                                <span>{{ message.sender?.name || 'User' }}</span>
                                <span v-if="message.is_teacher" class="message-badge">Teacher</span>
                                <span v-if="message.type === 'announcement'" class="message-badge announcement">Announcement</span>
                                <span v-if="message.muted_label" class="message-badge muted">{{ message.muted_label }}</span>
                            </div>
                            <p class="bubble-body">{{ message.body || 'Unsupported message type.' }}</p>
                            <p class="bubble-time">{{ formatTime(message.created_at) }}</p>
                        </article>
                    </template>
                </div>

                <div class="input-row">
                    <input
                        v-model="messageText"
                        type="text"
                        class="teacher-input"
                        placeholder="Type your reply..."
                        :disabled="sending"
                        @keydown.enter.prevent="sendMessage"
                    />
                    <button class="send-button" type="button" :disabled="sending" @click="sendMessage">
                        {{ sending ? 'Sending...' : 'Send' }}
                    </button>
                </div>
            </section>
        </div>
    </TeacherLayout>
</template>

<style scoped>
.teacher-chat-page {
    display: flex;
    flex-direction: column;
    gap: 12px;
}

.panel {
    border: 1px solid #f0e8e0;
    border-radius: 14px;
    background: #fff;
    padding: 14px;
}

.banner {
    border-radius: 10px;
    border: 1px solid #f2b7a8;
    background: #fff3ef;
    color: #c2410c;
    font-size: 14px;
    padding: 10px 12px;
}

.panel-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    gap: 8px;
}

.panel-header h1 {
    margin: 0;
    font-size: 24px;
    color: #2D2D2D;
}

.helper-copy {
    margin: 6px 0 10px;
    color: #64748B;
    font-size: 14px;
}

.conversation-item {
    width: 100%;
    border: 1px solid #f0e8e0;
    border-radius: 10px;
    background: #fff;
    padding: 10px 12px;
    margin-bottom: 8px;
    display: flex;
    justify-content: space-between;
    align-items: center;
    text-align: left;
    cursor: pointer;
}

.student-name {
    margin: 0;
    font-size: 17px;
    font-weight: 700;
    color: #2D2D2D;
}

.item-preview {
    margin: 2px 0 0;
    font-size: 13px;
    color: #64748B;
}

.item-side {
    text-align: right;
    display: flex;
    flex-direction: column;
    align-items: flex-end;
    gap: 4px;
}

.item-date {
    margin: 0;
    color: #94A3B8;
    font-size: 12px;
}

.badge {
    border-radius: 999px;
    min-width: 24px;
    height: 24px;
    padding: 0 8px;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    font-size: 12px;
    font-weight: 700;
    background: #E8553E;
    color: #fff;
}

.state-card {
    border-radius: 10px;
    border: 1px dashed #e5e7eb;
    background: #f8fafc;
    color: #334155;
    font-size: 14px;
    padding: 16px;
    margin-bottom: 8px;
}

.state-card.error {
    border-style: solid;
    border-color: #fecaca;
    background: #fef2f2;
    color: #991b1b;
}

.state-card.empty {
    border-style: solid;
    border-color: #e2e8f0;
    background: #f8fafc;
}

.messages-view {
    display: flex;
    flex-direction: column;
    min-height: 70vh;
}

.header {
    display: grid;
    grid-template-columns: auto 1fr auto;
    gap: 8px;
    align-items: center;
    border-bottom: 1px solid #f0e8e0;
    padding-bottom: 10px;
}

.header h2 {
    margin: 0;
    font-size: 18px;
    color: #2D2D2D;
    text-align: center;
}

.messages-scroll {
    flex: 1;
    overflow: auto;
    padding: 12px 2px;
    display: flex;
    flex-direction: column;
    gap: 6px;
}

.date-separator {
    text-align: center;
    color: #94A3B8;
    font-size: 12px;
    margin: 8px 0;
}

.chat-bubble {
    max-width: 78%;
    border-radius: 12px;
    padding: 8px 10px;
    border: 1px solid #f0e8e0;
}

.chat-bubble.mine {
    margin-left: auto;
    background: #fff3ef;
    border-color: #f2b7a8;
}

.chat-bubble.theirs {
    margin-right: auto;
    background: #fff;
}

.message-sender {
    display: flex;
    align-items: center;
    gap: 6px;
    flex-wrap: wrap;
    margin-bottom: 4px;
    color: #64748b;
    font-size: 11px;
    font-weight: 800;
}

.message-badge {
    border-radius: 999px;
    background: #eef2ff;
    color: #3730a3;
    padding: 2px 6px;
    font-size: 10px;
}

.message-badge.announcement {
    background: #fff7ed;
    color: #c2410c;
}

.message-badge.muted {
    background: #fee2e2;
    color: #991b1b;
}

.bubble-body {
    margin: 0;
    color: #1f2937;
    font-size: 14px;
    line-height: 1.4;
    white-space: pre-wrap;
}

.bubble-time {
    margin: 4px 0 0;
    font-size: 11px;
    color: #64748B;
    text-align: right;
}

.input-row {
    border-top: 1px solid #f0e8e0;
    padding-top: 10px;
    display: grid;
    grid-template-columns: minmax(0, 1fr) auto;
    gap: 8px;
}

.teacher-input {
    border: 1px solid #e5e7eb;
    border-radius: 10px;
    padding: 10px;
    font-size: 14px;
}

.secondary-btn {
    border: 1px solid #f2b7a8;
    background: #fff;
    color: #E8553E;
    border-radius: 999px;
    font-size: 12px;
    font-weight: 700;
    padding: 6px 10px;
    cursor: pointer;
}

.send-button {
    border: none;
    border-radius: 999px;
    background: #E8553E;
    color: #fff;
    font-size: 13px;
    font-weight: 700;
    padding: 10px 14px;
    cursor: pointer;
}

.send-button:disabled {
    opacity: 0.7;
    cursor: not-allowed;
}

@media (max-width: 900px) {
    .header {
        grid-template-columns: auto 1fr;
    }

    .header .secondary-btn:last-child {
        grid-column: 1 / -1;
    }

    .chat-bubble {
        max-width: 90%;
    }
}
</style>
