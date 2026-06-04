<script setup>
import { BellIcon } from '@heroicons/vue/24/outline';
import axios from 'axios';
import { computed, nextTick, onBeforeUnmount, onMounted, ref } from 'vue';
import { router, usePage } from '@inertiajs/vue3';

const props = defineProps({
    audience: {
        type: String,
        default: '',
        validator: (v) => ['', 'student', 'teacher', 'admin'].includes(v),
    },
    pollIntervalMs: {
        type: Number,
        default: 30000,
    },
    initialLimit: {
        type: Number,
        default: 8,
    },
    seeAllHref: {
        type: String,
        default: null,
    },
    buttonClass: {
        type: String,
        default: 'nbell-button',
    },
    iconClass: {
        type: String,
        default: 'nbell-icon',
    },
    badgeClass: {
        type: String,
        default: 'nbell-badge',
    },
});

const page = usePage();
const user = computed(() => page.props.auth?.user ?? null);

const open = ref(false);
const loading = ref(false);
const items = ref([]);
const error = ref('');
const wrapperRef = ref(null);
const menuRef = ref(null);
let pollTimer = null;

const unreadCount = computed(() => {
    const server = Number(page.props.notifications?.unread_count ?? 0);
    const localUnread = items.value.filter((i) => !i.read_at).length;
    if (server > 0) return server;
    return Number.isFinite(localUnread) ? Math.max(server, localUnread) : server;
});

const displayItems = computed(() => {
    if (!props.audience) return items.value;
    return items.value.filter((i) => i.audience === props.audience);
});

const itemTypeLabel = (type) => {
    const map = {
        booking_confirmed: 'Booking Confirmed',
        booking_cancelled: 'Booking Cancelled',
        session_starting_soon: 'Session Reminder',
        session_completed: 'Session Completed',
        new_message: 'New Message',
        review_received: 'New Review',
        review_reminder: 'Review Reminder',
        earnings_released: 'Earnings Released',
        no_show: 'No Show',
        group_session_started: 'Group Session Live',
    };
    return map[type] || (type ? type.replace(/_/g, ' ').replace(/\b\w/g, (c) => c.toUpperCase()) : 'Notification');
};

const formatTimeAgo = (iso) => {
    if (!iso) return '';
    const date = iso.includes('Z') || iso.includes('+') ? new Date(iso) : new Date(iso + 'Z');
    const diffMs = Date.now() - date.getTime();
    const sec = Math.round(diffMs / 1000);
    if (sec < 60) return 'just now';
    const min = Math.round(sec / 60);
    if (min < 60) return `${min}m ago`;
    const hr = Math.round(min / 60);
    if (hr < 24) return `${hr}h ago`;
    const day = Math.round(hr / 24);
    if (day < 7) return `${day}d ago`;
    return date.toLocaleDateString('en-IN', { day: 'numeric', month: 'short' });
};

const fetchNotifications = async () => {
    try {
        const { data } = await axios.get('/api/notifications', {
            params: { limit: props.initialLimit, active_only: true },
        });
        items.value = data?.data || [];
        if (data?.unread_count !== undefined) {
            page.props.notifications = { ...(page.props.notifications || {}), unread_count: data.unread_count };
        }
        error.value = '';
    } catch (e) {
        error.value = e?.response?.data?.message || 'Unable to load notifications.';
    }
};

const toggle = async () => {
    open.value = !open.value;
    if (open.value) {
        await nextTick();
        await fetchNotifications();
    }
};

const close = () => {
    open.value = false;
};

const handleDocumentClick = (event) => {
    if (!open.value) return;
    if (wrapperRef.value && !wrapperRef.value.contains(event.target)) {
        close();
    }
};

const handleKey = (event) => {
    if (event.key === 'Escape' && open.value) {
        close();
    }
};

const markAsRead = async (item, event) => {
    if (event) {
        event.preventDefault();
        event.stopPropagation();
    }
    if (!item || item.read_at) return;
    try {
        await axios.patch(`/api/notifications/${item.id}/read`);
        item.read_at = new Date().toISOString();
        if (page.props.notifications) {
            page.props.notifications.unread_count = Math.max(0, Number(page.props.notifications.unread_count || 0) - 1);
        }
    } catch {
        // silent — toast handled elsewhere if needed
    }
};

const dismiss = async (item, event) => {
    if (event) {
        event.preventDefault();
        event.stopPropagation();
    }
    if (!item) return;
    try {
        await axios.patch(`/api/notifications/${item.id}/dismiss`);
        items.value = items.value.filter((i) => Number(i.id) !== Number(item.id));
        if (!item.read_at && page.props.notifications) {
            page.props.notifications.unread_count = Math.max(0, Number(page.props.notifications.unread_count || 0) - 1);
        }
    } catch {
        // silent
    }
};

const markAllRead = async () => {
    try {
        await axios.patch('/api/notifications/read-all');
        items.value = items.value.map((i) => ({ ...i, read_at: i.read_at || new Date().toISOString() }));
        if (page.props.notifications) {
            page.props.notifications.unread_count = 0;
        }
    } catch {
        // silent
    }
};

const seeAll = () => {
    close();
    if (props.seeAllHref) {
        router.visit(props.seeAllHref);
    } else {
        router.visit(route('notifications.index'));
    }
};

const listenForRealtime = () => {
    if (!window.Echo || !user.value?.id) return;
    window.Echo.private(`App.Models.User.${user.value.id}`)
        .listen('.notification.created', (payload) => {
            if (!payload) return;
            if (props.audience && payload.audience !== props.audience) return;
            const exists = items.value.find((i) => Number(i.id) === Number(payload.id));
            if (!exists) {
                items.value = [
                    {
                        id: payload.id,
                        type: payload.type,
                        title: payload.title,
                        message: payload.message,
                        audience: payload.audience,
                        data: payload.data || {},
                        is_critical: payload.is_critical,
                        read_at: null,
                        created_at: payload.created_at || new Date().toISOString(),
                    },
                    ...items.value,
                ].slice(0, props.initialLimit);
            }
            if (page.props.notifications) {
                page.props.notifications.unread_count = Number(page.props.notifications.unread_count || 0) + 1;
            }
        });
};

onMounted(() => {
    if (!user.value) return;
    document.addEventListener('click', handleDocumentClick);
    document.addEventListener('keydown', handleKey);
    listenForRealtime();
    pollTimer = window.setInterval(() => {
        if (!open.value) return;
        fetchNotifications();
    }, props.pollIntervalMs);
});

onBeforeUnmount(() => {
    document.removeEventListener('click', handleDocumentClick);
    document.removeEventListener('keydown', handleKey);
    if (pollTimer) window.clearInterval(pollTimer);
});
</script>

<template>
    <div ref="wrapperRef" class="nbell">
        <button
            type="button"
            :class="buttonClass"
            :aria-label="`Notifications (${unreadCount} unread)`"
            aria-haspopup="true"
            :aria-expanded="open"
            @click.stop="toggle"
        >
            <BellIcon :class="iconClass" aria-hidden="true" />
            <span v-if="unreadCount > 0" :class="badgeClass">{{ unreadCount > 99 ? '99+' : unreadCount }}</span>
        </button>

        <div v-if="open" ref="menuRef" class="nbell-dropdown" role="menu">
            <header class="nbell-header">
                <h3 class="nbell-title">Notifications</h3>
                <button
                    v-if="displayItems.length"
                    type="button"
                    class="nbell-link"
                    @click="markAllRead"
                >Mark all read</button>
            </header>

            <div v-if="loading && !items.length" class="nbell-empty">Loading…</div>
            <div v-else-if="!displayItems.length" class="nbell-empty">
                <BellIcon class="nbell-empty-icon" aria-hidden="true" />
                <p>You're all caught up.</p>
            </div>

            <ul v-else class="nbell-list" role="list">
                <li
                    v-for="item in displayItems"
                    :key="item.id"
                    class="nbell-item"
                    :class="{ 'nbell-item--unread': !item.read_at, 'nbell-item--critical': item.is_critical }"
                    role="menuitem"
                >
                    <div class="nbell-item-main" @click="markAsRead(item, $event)">
                        <p class="nbell-item-type">{{ itemTypeLabel(item.type) }} · {{ formatTimeAgo(item.created_at) }}</p>
                        <p v-if="item.title" class="nbell-item-title">{{ item.title }}</p>
                        <p class="nbell-item-message">{{ item.message }}</p>
                    </div>
                    <button
                        type="button"
                        class="nbell-dismiss"
                        aria-label="Dismiss notification"
                        @click="dismiss(item, $event)"
                    >×</button>
                </li>
            </ul>

            <footer class="nbell-footer">
                <button type="button" class="nbell-see-all" @click="seeAll">See all notifications</button>
            </footer>
        </div>
    </div>
</template>

<style scoped>
.nbell {
    position: relative;
    display: inline-flex;
}

.nbell-button {
    position: relative;
    width: 44px;
    height: 44px;
    border: 1px solid var(--s-border, #F0E8E0);
    border-radius: 8px;
    background: #ffffff;
    color: var(--s-coral-dark, #B53A2D);
    display: inline-flex;
    align-items: center;
    justify-content: center;
    cursor: pointer;
    transition: background-color 150ms ease, border-color 150ms ease;
}

.nbell-button:hover,
.nbell-button[aria-expanded="true"] {
    background: #FFF3EF;
    border-color: #E8553E;
}

.nbell-icon {
    width: 22px;
    height: 22px;
}

.nbell-badge {
    position: absolute;
    top: -6px;
    right: -8px;
    min-width: 20px;
    height: 20px;
    padding: 0 6px;
    border-radius: 999px;
    background: #DC2626;
    color: #ffffff;
    font-family: 'Nunito', sans-serif;
    font-size: 11px;
    font-weight: 700;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    line-height: 1;
}

.nbell-dropdown {
    position: absolute;
    top: calc(100% + 8px);
    right: 0;
    width: 360px;
    max-width: calc(100vw - 32px);
    max-height: 460px;
    display: flex;
    flex-direction: column;
    background: #ffffff;
    border: 1px solid var(--s-border, #F0E8E0);
    border-radius: 12px;
    box-shadow: 0 12px 32px rgba(15, 23, 42, 0.16);
    overflow: hidden;
    z-index: 60;
}

.nbell-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 12px 14px;
    border-bottom: 1px solid #f1f5f9;
    background: #fff;
}

.nbell-title {
    margin: 0;
    font-family: 'Fredoka One', cursive;
    font-size: 16px;
    color: #E8553E;
}

.nbell-link {
    border: none;
    background: none;
    color: #E8553E;
    font-family: 'Nunito', sans-serif;
    font-size: 13px;
    font-weight: 700;
    cursor: pointer;
    padding: 4px 6px;
    border-radius: 6px;
}

.nbell-link:hover {
    background: #FFF3EF;
}

.nbell-list {
    list-style: none;
    margin: 0;
    padding: 4px 0;
    overflow-y: auto;
    flex: 1;
}

.nbell-item {
    display: flex;
    gap: 10px;
    padding: 10px 14px;
    border-bottom: 1px solid #f8fafc;
    transition: background-color 120ms ease;
}

.nbell-item:last-child {
    border-bottom: none;
}

.nbell-item:hover {
    background: #FFF8F0;
}

.nbell-item--unread {
    background: #FFFAF5;
    border-left: 3px solid #E8553E;
    padding-left: 11px;
}

.nbell-item--critical {
    background: #FFF4E5;
    border-left-color: #DC2626;
}

.nbell-item-main {
    flex: 1;
    min-width: 0;
    cursor: pointer;
}

.nbell-item-type {
    margin: 0 0 2px;
    font-family: 'Nunito', sans-serif;
    font-size: 11px;
    font-weight: 800;
    letter-spacing: 0.06em;
    text-transform: uppercase;
    color: #9CA3AF;
}

.nbell-item-title {
    margin: 0 0 2px;
    font-family: 'Nunito', sans-serif;
    font-size: 13px;
    font-weight: 800;
    color: #2D2D2D;
}

.nbell-item-message {
    margin: 0;
    font-family: 'Nunito', sans-serif;
    font-size: 13px;
    color: #4B5563;
    line-height: 1.4;
    word-break: break-word;
}

.nbell-dismiss {
    flex: 0 0 auto;
    width: 28px;
    height: 28px;
    border: none;
    background: transparent;
    color: #9CA3AF;
    font-size: 20px;
    line-height: 1;
    border-radius: 6px;
    cursor: pointer;
}

.nbell-dismiss:hover {
    background: #fee2e2;
    color: #be123c;
}

.nbell-empty {
    padding: 32px 20px;
    text-align: center;
    color: #9CA3AF;
    font-family: 'Nunito', sans-serif;
    font-size: 13px;
}

.nbell-empty p {
    margin: 6px 0 0;
}

.nbell-empty-icon {
    width: 32px;
    height: 32px;
    color: #E8553E;
    opacity: 0.5;
    margin: 0 auto;
}

.nbell-footer {
    border-top: 1px solid #f1f5f9;
    padding: 8px;
    background: #fff;
}

.nbell-see-all {
    width: 100%;
    min-height: 36px;
    border: none;
    background: #FFF3EF;
    color: #E8553E;
    font-family: 'Nunito', sans-serif;
    font-size: 13px;
    font-weight: 700;
    border-radius: 8px;
    cursor: pointer;
    transition: background-color 150ms ease;
}

.nbell-see-all:hover {
    background: #FFE7DD;
}

@media (max-width: 480px) {
    .nbell-dropdown {
        width: calc(100vw - 24px);
        right: -8px;
    }
}
</style>
