<script setup>
import { Head, router, usePage } from '@inertiajs/vue3';
import axios from 'axios';
import { computed, onBeforeUnmount, onMounted, ref } from 'vue';

const page = usePage();
const user = computed(() => page.props.auth?.user ?? null);

const items = ref([]);
const loading = ref(true);
const error = ref('');
const filter = ref('all');
const pageNumber = ref(1);
const hasMore = ref(false);

const filterOptions = [
    { key: 'all', label: 'All' },
    { key: 'unread', label: 'Unread' },
    { key: 'critical', label: 'Critical' },
];

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

const formatDateTime = (iso) => {
    if (!iso) return '—';
    const d = iso.includes('Z') || iso.includes('+') ? new Date(iso) : new Date(iso + 'Z');
    return d.toLocaleString('en-IN', { day: 'numeric', month: 'short', hour: '2-digit', minute: '2-digit' });
};

const filteredItems = computed(() => {
    if (filter.value === 'unread') return items.value.filter((i) => !i.read_at);
    if (filter.value === 'critical') return items.value.filter((i) => i.is_critical);
    return items.value;
});

const fetchPage = async (pageIdx = 1) => {
    loading.value = true;
    error.value = '';
    try {
        const { data } = await axios.get('/api/notifications', {
            params: { limit: 50, page: pageIdx },
        });
        if (pageIdx === 1) {
            items.value = data?.data || [];
        } else {
            items.value = items.value.concat(data?.data || []);
        }
        hasMore.value = (data?.data?.length ?? 0) >= 50;
        pageNumber.value = pageIdx;
    } catch (e) {
        error.value = e?.response?.data?.message || 'Unable to load notifications.';
    } finally {
        loading.value = false;
    }
};

const markAsRead = async (item) => {
    if (!item || item.read_at) return;
    try {
        await axios.patch(`/api/notifications/${item.id}/read`);
        item.read_at = new Date().toISOString();
        if (page.props.notifications) {
            page.props.notifications.unread_count = Math.max(0, Number(page.props.notifications.unread_count || 0) - 1);
        }
    } catch {
        // silent
    }
};

const dismiss = async (item) => {
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

const listenForRealtime = () => {
    if (!window.Echo || !user.value?.id) return;
    window.Echo.private(`App.Models.User.${user.value.id}`)
        .listen('.notification.created', (payload) => {
            if (!payload) return;
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
                ];
            }
            if (page.props.notifications) {
                page.props.notifications.unread_count = Number(page.props.notifications.unread_count || 0) + 1;
            }
        });
};

onMounted(() => {
    fetchPage(1);
    listenForRealtime();
});

onBeforeUnmount(() => {
    if (window.Echo) {
        try {
            window.Echo.leave(`private-App.Models.User.${user.value?.id}`);
        } catch {
            // ignore
        }
    }
});

const layoutForUser = computed(() => {
    if (user.value?.isAdmin?.()) return 'admin';
    if (user.value?.isTeacher?.()) return 'teacher';
    return 'student';
});
</script>

<template>
    <Head title="Notifications — EduBridge" />

    <component
        :is="layoutForUser === 'admin' ? 'AdminLayout' : layoutForUser === 'teacher' ? 'TeacherLayout' : 'StudentLayout'"
    >
        <div class="notif-page">
            <header class="notif-header">
                <div>
                    <h1>Notifications</h1>
                    <p class="notif-helper">Your recent activity, reminders, and review updates.</p>
                </div>
                <button
                    v-if="items.length"
                    type="button"
                    class="notif-mark-all"
                    @click="markAllRead"
                >Mark all as read</button>
            </header>

            <nav class="notif-filters" aria-label="Filter notifications">
                <button
                    v-for="opt in filterOptions"
                    :key="opt.key"
                    type="button"
                    class="notif-filter"
                    :class="{ 'notif-filter--active': filter === opt.key }"
                    @click="filter = opt.key"
                >{{ opt.label }}</button>
            </nav>

            <div v-if="loading && !items.length" class="notif-loading">Loading notifications…</div>
            <div v-else-if="error" class="notif-error">{{ error }}</div>
            <div v-else-if="!filteredItems.length" class="notif-empty">
                <p>No notifications to show.</p>
            </div>

            <ul v-else class="notif-list">
                <li
                    v-for="item in filteredItems"
                    :key="item.id"
                    class="notif-row"
                    :class="{
                        'notif-row--unread': !item.read_at,
                        'notif-row--critical': item.is_critical,
                        'notif-row--dismissed': !!item.dismissed_at,
                    }"
                >
                    <button type="button" class="notif-row-body" @click="markAsRead(item)">
                        <p class="notif-row-type">{{ itemTypeLabel(item.type) }} · {{ formatDateTime(item.created_at) }}</p>
                        <p v-if="item.title" class="notif-row-title">{{ item.title }}</p>
                        <p class="notif-row-message">{{ item.message }}</p>
                        <p v-if="!item.read_at" class="notif-row-unread">Unread</p>
                    </button>
                    <button
                        type="button"
                        class="notif-row-dismiss"
                        aria-label="Dismiss"
                        @click="dismiss(item)"
                    >×</button>
                </li>
            </ul>

            <div v-if="hasMore && !loading" class="notif-load-more">
                <button type="button" class="notif-load-more-btn" @click="fetchPage(pageNumber + 1)">Load more</button>
            </div>
        </div>
    </component>
</template>

<style scoped>
.notif-page {
    max-width: 760px;
    margin: 0 auto;
    padding: 32px 24px;
    font-family: 'Nunito', sans-serif;
}

.notif-header {
    display: flex;
    align-items: flex-end;
    justify-content: space-between;
    gap: 16px;
    flex-wrap: wrap;
    margin-bottom: 18px;
}

.notif-header h1 {
    margin: 0;
    font-family: 'Fredoka One', cursive;
    font-size: 26px;
    color: #E8553E;
}

.notif-helper {
    margin: 6px 0 0;
    color: #6B7280;
    font-size: 14px;
}

.notif-mark-all {
    border: 1px solid #E8553E;
    background: #fff;
    color: #E8553E;
    font-family: 'Nunito', sans-serif;
    font-size: 14px;
    font-weight: 700;
    padding: 8px 14px;
    border-radius: 999px;
    cursor: pointer;
}

.notif-mark-all:hover {
    background: #FFF3EF;
}

.notif-filters {
    display: flex;
    gap: 8px;
    margin-bottom: 18px;
}

.notif-filter {
    border: 1px solid #F0E8E0;
    background: #fff;
    color: #4B5563;
    font-family: 'Nunito', sans-serif;
    font-size: 13px;
    font-weight: 700;
    padding: 6px 14px;
    border-radius: 999px;
    cursor: pointer;
}

.notif-filter--active {
    background: #E8553E;
    color: #fff;
    border-color: #E8553E;
}

.notif-loading,
.notif-empty,
.notif-error {
    padding: 32px 20px;
    text-align: center;
    background: #fff;
    border: 1px solid #F0E8E0;
    border-radius: 14px;
    color: #6B7280;
    font-size: 14px;
}

.notif-error {
    color: #DC2626;
    background: #FEF2F2;
    border-color: #FECACA;
}

.notif-list {
    list-style: none;
    margin: 0;
    padding: 0;
    display: flex;
    flex-direction: column;
    gap: 10px;
}

.notif-row {
    display: flex;
    align-items: stretch;
    gap: 6px;
    background: #fff;
    border: 1px solid #F0E8E0;
    border-radius: 14px;
    overflow: hidden;
    transition: box-shadow 150ms ease, transform 150ms ease;
}

.notif-row:hover {
    box-shadow: 0 6px 16px rgba(232, 85, 62, 0.08);
}

.notif-row--unread {
    border-left: 4px solid #E8553E;
}

.notif-row--critical {
    background: #FFFAF0;
    border-color: #FCD7B5;
    border-left-color: #DC2626;
}

.notif-row--dismissed {
    opacity: 0.55;
}

.notif-row-body {
    flex: 1;
    text-align: left;
    background: transparent;
    border: none;
    padding: 14px 16px;
    cursor: pointer;
    font-family: 'Nunito', sans-serif;
    color: inherit;
}

.notif-row-type {
    margin: 0 0 4px;
    font-size: 11px;
    font-weight: 800;
    letter-spacing: 0.08em;
    text-transform: uppercase;
    color: #9CA3AF;
}

.notif-row-title {
    margin: 0 0 2px;
    font-size: 15px;
    font-weight: 800;
    color: #2D2D2D;
}

.notif-row-message {
    margin: 0;
    font-size: 14px;
    color: #4B5563;
    line-height: 1.45;
}

.notif-row-unread {
    margin: 6px 0 0;
    font-size: 11px;
    color: #E8553E;
    font-weight: 800;
    letter-spacing: 0.08em;
    text-transform: uppercase;
}

.notif-row-dismiss {
    flex: 0 0 auto;
    width: 40px;
    border: none;
    background: transparent;
    color: #9CA3AF;
    font-size: 22px;
    cursor: pointer;
}

.notif-row-dismiss:hover {
    background: #fee2e2;
    color: #be123c;
}

.notif-load-more {
    margin-top: 16px;
    text-align: center;
}

.notif-load-more-btn {
    border: 1px solid #F0E8E0;
    background: #fff;
    color: #4B5563;
    font-family: 'Nunito', sans-serif;
    font-size: 13px;
    font-weight: 700;
    padding: 8px 18px;
    border-radius: 999px;
    cursor: pointer;
}

.notif-load-more-btn:hover {
    background: #FFF8F0;
}
</style>
