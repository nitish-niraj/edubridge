<script setup>
import TeacherLayout from '@/Layouts/TeacherLayout.vue';
import { Head, Link } from '@inertiajs/vue3';
import { computed } from 'vue';

const props = defineProps({
    user: {
        type: Object,
        default: () => ({}),
    },
    profile: {
        type: Object,
        default: () => ({}),
    },
    is_verified: {
        type: Boolean,
        default: false,
    },
    stats: {
        type: Object,
        default: () => ({
            sessions_this_month: 0,
            total_students: 0,
            earnings_this_month: 0,
            upcoming_sessions: 0,
            unread_messages: 0,
        }),
    },
    today_sessions: {
        type: Array,
        default: () => [],
    },
});

const firstName = computed(() => {
    const name = String(props.user?.name || 'Teacher').trim();
    return name.split(' ')[0] || 'Teacher';
});

const dashboardCards = computed(() => [
    { label: 'Sessions this month', value: Number(props.stats?.sessions_this_month || 0) },
    { label: 'Total students taught', value: Number(props.stats?.total_students || 0) },
    { label: 'Earnings this month', value: `INR ${Number(props.stats?.earnings_this_month || 0).toLocaleString()}` },
    { label: 'Upcoming sessions', value: Number(props.stats?.upcoming_sessions || 0) },
    { label: 'Unread messages', value: Number(props.stats?.unread_messages || 0) },
]);

const formatTime = (value) => {
    if (!value) {
        return '-';
    }

    return new Date(value).toLocaleTimeString(undefined, {
        hour: '2-digit',
        minute: '2-digit',
    });
};

const sessionStatusClass = (status) => {
    if (status === 'confirmed') return 'status-confirmed';
    if (status === 'pending') return 'status-pending';
    return 'status-default';
};
</script>

<template>
    <Head title="Teacher Dashboard" />

    <TeacherLayout page-title="Dashboard">
        <div class="teacher-dashboard-page">
            <section class="hero-card">
                <div>
                    <p class="eyebrow">Teacher portal</p>
                    <h1>Welcome back, {{ firstName }}.</h1>
                    <p class="hero-copy">
                        Keep your profile updated, manage your schedule, and respond to students from one place.
                    </p>
                </div>
                <div class="hero-status" :class="props.is_verified ? 'verified' : 'pending'">
                    {{ props.is_verified ? 'Profile verified' : 'Verification in review' }}
                </div>
            </section>

            <section v-if="!props.is_verified" class="notice-banner notice-pending">
                Your profile is still under verification. You can continue updating your profile and availability while the review is in progress.
            </section>

            <section class="stats-grid">
                <article v-for="card in dashboardCards" :key="card.label" class="stat-card">
                    <span>{{ card.label }}</span>
                    <strong>{{ card.value }}</strong>
                </article>
            </section>

            <section class="panel">
                <header class="panel-header">
                    <h2>Today's sessions</h2>
                    <Link :href="route('teacher.sessions')">Open all sessions</Link>
                </header>

                <div v-if="today_sessions.length" class="session-list">
                    <article v-for="session in today_sessions" :key="session.id" class="session-item">
                        <div>
                            <p class="session-student">{{ session.student?.name || 'Student' }}</p>
                            <p class="session-meta">
                                {{ session.subject || 'General session' }} · {{ formatTime(session.start_at) }} - {{ formatTime(session.end_at) }}
                            </p>
                        </div>
                        <span class="session-status" :class="sessionStatusClass(session.status)">
                            {{ session.status }}
                        </span>
                    </article>
                </div>

                <p v-else class="empty-copy">No sessions scheduled for today.</p>
            </section>

            <section class="panel">
                <header class="panel-header">
                    <h2>Quick actions</h2>
                </header>

                <div class="quick-grid">
                    <Link :href="route('teacher.profile.step', { step: 1 })" class="quick-card">
                        <strong>My profile</strong>
                        <span>Continue profile setup and verification documents.</span>
                    </Link>

                    <Link :href="route('teacher.availability')" class="quick-card">
                        <strong>Availability</strong>
                        <span>Update your weekly teaching slots.</span>
                    </Link>

                    <Link :href="route('teacher.chat')" class="quick-card">
                        <strong>Messages</strong>
                        <span>Reply to student messages and class chats.</span>
                    </Link>

                    <Link :href="route('teacher.settings')" class="quick-card">
                        <strong>Settings</strong>
                        <span>Adjust accessibility and teacher preferences.</span>
                    </Link>
                </div>
            </section>
        </div>
    </TeacherLayout>
</template>

<style scoped>
.teacher-dashboard-page {
    display: flex;
    flex-direction: column;
    gap: 14px;
}

.hero-card {
    background: #fff;
    border: 1px solid #f0e8e0;
    border-radius: 14px;
    padding: 18px;
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
    gap: 12px;
}

.eyebrow {
    margin: 0;
    color: #9CA3AF;
    text-transform: uppercase;
    letter-spacing: 0.08em;
    font-size: 12px;
    font-weight: 700;
}

h1 {
    margin: 4px 0;
    font-size: 28px;
    color: #2D2D2D;
}

.hero-copy {
    margin: 0;
    color: #64748B;
    font-size: 16px;
    line-height: 1.5;
}

.hero-status {
    border-radius: 999px;
    padding: 6px 10px;
    font-size: 12px;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 0.05em;
    white-space: nowrap;
}

.hero-status.verified {
    background: #dcfce7;
    color: #15803d;
}

.hero-status.pending {
    background: #fef3c7;
    color: #d97706;
}

.notice-banner {
    border-radius: 10px;
    border: 1px solid #fcd34d;
    background: #fffbeb;
    color: #92400e;
    font-size: 14px;
    padding: 10px 12px;
}

.stats-grid {
    display: grid;
    grid-template-columns: repeat(5, minmax(0, 1fr));
    gap: 10px;
}

.stat-card {
    border: 1px solid #f0e8e0;
    border-radius: 12px;
    background: #fff;
    padding: 12px;
    display: flex;
    flex-direction: column;
    gap: 6px;
}

.stat-card span {
    font-size: 12px;
    color: #9CA3AF;
}

.stat-card strong {
    font-size: 20px;
    color: #2D2D2D;
}

.panel {
    border: 1px solid #f0e8e0;
    border-radius: 14px;
    background: #fff;
    padding: 14px;
}

.panel-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 10px;
}

.panel-header h2 {
    margin: 0;
    font-size: 20px;
    color: #2D2D2D;
}

.panel-header a {
    color: #E8553E;
    font-size: 14px;
    font-weight: 700;
    text-decoration: none;
}

.session-list {
    display: flex;
    flex-direction: column;
    gap: 8px;
}

.session-item {
    border: 1px solid #f0e8e0;
    border-radius: 10px;
    padding: 10px 12px;
    display: flex;
    justify-content: space-between;
    gap: 12px;
    align-items: center;
}

.session-student {
    margin: 0;
    font-size: 17px;
    font-weight: 700;
    color: #2D2D2D;
}

.session-meta {
    margin: 2px 0 0;
    font-size: 14px;
    color: #64748B;
}

.session-status {
    border-radius: 999px;
    padding: 4px 9px;
    font-size: 12px;
    font-weight: 700;
    text-transform: capitalize;
}

.session-status.status-confirmed {
    background: #dcfce7;
    color: #15803d;
}

.session-status.status-pending {
    background: #fef3c7;
    color: #d97706;
}

.session-status.status-default {
    background: #e2e8f0;
    color: #334155;
}

.empty-copy {
    margin: 0;
    color: #64748B;
    font-size: 14px;
}

.quick-grid {
    display: grid;
    grid-template-columns: repeat(2, minmax(0, 1fr));
    gap: 10px;
}

.quick-card {
    border: 1px solid #f0e8e0;
    border-radius: 10px;
    background: #fff8f0;
    padding: 12px;
    text-decoration: none;
    display: flex;
    flex-direction: column;
    gap: 4px;
}

.quick-card strong {
    color: #2D2D2D;
    font-size: 16px;
}

.quick-card span {
    color: #64748B;
    font-size: 14px;
    line-height: 1.4;
}

@media (max-width: 1100px) {
    .stats-grid {
        grid-template-columns: repeat(2, minmax(0, 1fr));
    }

    .quick-grid {
        grid-template-columns: 1fr;
    }

    .hero-card {
        flex-direction: column;
    }
}
</style>
