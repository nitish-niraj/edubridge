<script setup>
import axios from 'axios';
import dayjs from 'dayjs';
import relativeTime from 'dayjs/plugin/relativeTime';
import { ArrowPathIcon, ExclamationTriangleIcon } from '@heroicons/vue/24/outline';
import { computed, onBeforeUnmount, onMounted, ref, watch } from 'vue';
import AdminDrawer from '@/Components/Admin/AdminDrawer.vue';
import AdminStatCard from '@/Components/Admin/AdminStatCard.vue';
import AdminStatusBadge from '@/Components/Admin/AdminStatusBadge.vue';
import { useAdminChart } from '@/composables/useAdminChart';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import { Link } from '@inertiajs/vue3';

dayjs.extend(relativeTime);

const props = defineProps({
    stats: {
        type: Object,
        default: () => ({}),
    },
    recent_signups: {
        type: Array,
        default: () => [],
    },
    pending_actions: {
        type: Array,
        default: () => [],
    },
    sessions_chart: {
        type: Array,
        default: () => [],
    },
    new_users_chart: {
        type: Array,
        default: () => [],
    },
});

const { createChart, createLineDataset, destroyChart, colors } = useAdminChart();

const defaultPayload = {
    stats: {
        total_active_users: 0,
        sessions_today: 0,
        pending_verifications: 0,
        unread_reports: 0,
        revenue_this_month: 0,
    },
    recent_signups: [],
    pending_actions: [],
    sessions_chart: [],
    new_users_chart: [],
};

const normalizePayload = (payload) => ({
    stats: {
        ...defaultPayload.stats,
        ...(payload?.stats || {}),
    },
    recent_signups: Array.isArray(payload?.recent_signups) ? payload.recent_signups : [],
    pending_actions: Array.isArray(payload?.pending_actions) ? payload.pending_actions : [],
    sessions_chart: Array.isArray(payload?.sessions_chart) ? payload.sessions_chart : [],
    new_users_chart: Array.isArray(payload?.new_users_chart) ? payload.new_users_chart : [],
});

const dashboardData = ref(normalizePayload(props));
const refreshing = ref(false);
const statPulseSeed = ref(0);
const chartCanvas = ref(null);

const drawerOpen = ref(false);
const drawerLoading = ref(false);
const drawerUser = ref(null);
const drawerTimeline = ref([]);
const dashboardError = ref('');
const drawerError = ref('');

const actionPulseIds = ref([]);
const newSignupIds = ref([]);
let actionPulseTimer = null;
let signupPulseTimer = null;
let pollTimer = null;
const knownActionIds = ref(new Set(dashboardData.value.pending_actions.map((action) => action.id)));
const knownSignupIds = ref(new Set(dashboardData.value.recent_signups.map((signup) => signup.id)));

let dashboardChart = null;

watch(
    () => [props.stats, props.recent_signups, props.pending_actions, props.sessions_chart, props.new_users_chart],
    () => {
        dashboardData.value = normalizePayload(props);
        knownActionIds.value = new Set(dashboardData.value.pending_actions.map((action) => action.id));
        knownSignupIds.value = new Set(dashboardData.value.recent_signups.map((signup) => signup.id));
    },
    { deep: true }
);

const recentSignups = computed(() => {
    return [...dashboardData.value.recent_signups].sort((left, right) => {
        const leftTime = new Date(left.created_at || 0).getTime();
        const rightTime = new Date(right.created_at || 0).getTime();
        return rightTime - leftTime;
    });
});

const pendingVerificationCount = computed(() => Number(dashboardData.value.stats.pending_verifications || 0));
const unreadReportsCount = computed(() => Number(dashboardData.value.stats.unread_reports || 0));

const showAlertStrip = computed(() => pendingVerificationCount.value > 0 || unreadReportsCount.value > 0);

const sessionTrend = computed(() => {
    const points = dashboardData.value.sessions_chart;

    if (points.length < 2) {
        return 0;
    }

    const last = Number(points.at(-1)?.count || 0);
    const previous = Number(points.at(-2)?.count || 0);

    if (previous <= 0) {
        return last > 0 ? 10 : 0;
    }

    return Math.round(((last - previous) / previous) * 100);
});

const statCards = computed(() => [
    {
        label: 'Total Active Users',
        value: Number(dashboardData.value.stats.total_active_users || 0),
        trend: Math.max(2, Math.round(sessionTrend.value / 2)),
    },
    {
        label: 'Sessions Today',
        value: Number(dashboardData.value.stats.sessions_today || 0),
        trend: sessionTrend.value,
    },
    {
        label: 'Revenue This Month',
        value: Number(dashboardData.value.stats.revenue_this_month || 0),
        trend: sessionTrend.value >= 0 ? Math.max(2, Math.round(sessionTrend.value / 2)) : Math.round(sessionTrend.value / 2),
        prefix: 'INR ',
    },
    {
        label: 'Unread Reports',
        value: Number(dashboardData.value.stats.unread_reports || 0),
        trend: dashboardData.value.stats.unread_reports > 0 ? -Math.min(40, Number(dashboardData.value.stats.unread_reports || 0) * 2) : 4,
    },
]);

const renderChart = () => {
    destroyChart(dashboardChart);

    dashboardChart = createChart(chartCanvas.value, {
        type: 'line',
        data: {
            labels: dashboardData.value.sessions_chart.map((point) => point.date),
            datasets: [
                createLineDataset(
                    'Sessions',
                    dashboardData.value.sessions_chart.map((point) => Number(point.count || 0)),
                    colors.blue
                ),
                createLineDataset(
                    'New users',
                    dashboardData.value.new_users_chart.map((point) => Number(point.count || 0)),
                    colors.green
                ),
            ],
        },
        options: {
            interaction: {
                mode: 'index',
                intersect: false,
            },
            animation: {
                x: {
                    duration: 600,
                    easing: 'easeOutQuart',
                    from: 0,
                },
                y: {
                    duration: 400,
                    easing: 'easeOutQuart',
                },
            },
            plugins: {
                tooltip: {
                    callbacks: {
                        title: (items) => dayjs(items[0]?.label).format('MMM D, YYYY'),
                        label: (context) => `${context.dataset.label}: ${context.parsed.y}`,
                    },
                },
            },
            scales: {
                y: {
                    beginAtZero: true,
                },
            },
        },
    });
};

watch(
    () => [dashboardData.value.sessions_chart, dashboardData.value.new_users_chart],
    () => {
        renderChart();
    },
    { deep: true }
);

const getRoleTone = (role) => String(role || '').toLowerCase() === 'teacher' ? 'teacher' : 'student';

const getUrgencyTone = (urgency) => {
    const normalized = String(urgency || '').toLowerCase();

    if (normalized === 'high') return 'high';
    if (normalized === 'medium') return 'medium';
    return 'low';
};

const markNewActions = (actions) => {
    const previousSet = knownActionIds.value;
    const nextSet = new Set(actions.map((action) => action.id));
    const addedIds = actions.filter((action) => !previousSet.has(action.id)).map((action) => action.id);

    knownActionIds.value = nextSet;

    if (!addedIds.length) {
        return;
    }

    actionPulseIds.value = addedIds;
    clearTimeout(actionPulseTimer);
    actionPulseTimer = window.setTimeout(() => {
        actionPulseIds.value = [];
    }, 450);
};

const markNewSignups = (signups) => {
    const previousSet = knownSignupIds.value;
    const nextSet = new Set(signups.map((signup) => signup.id));
    const addedIds = signups.filter((signup) => !previousSet.has(signup.id)).map((signup) => signup.id);

    knownSignupIds.value = nextSet;

    if (!addedIds.length) {
        return;
    }

    newSignupIds.value = addedIds;
    clearTimeout(signupPulseTimer);
    signupPulseTimer = window.setTimeout(() => {
        newSignupIds.value = [];
    }, 3000);
};

const refreshDashboard = async ({ silent = false } = {}) => {
    if (refreshing.value) {
        return;
    }

    refreshing.value = true;
    dashboardError.value = '';

    try {
        const { data } = await axios.get('/api/admin/dashboard/summary');
        const nextPayload = normalizePayload(data);

        markNewSignups(nextPayload.recent_signups);
        markNewActions(nextPayload.pending_actions);
        dashboardData.value = nextPayload;

        if (!silent) {
            statPulseSeed.value += 1;
        }
    } catch (error) {
        dashboardError.value = error?.response?.status === 401
            ? 'Session expired for admin API calls. Refresh the page after logging in again.'
            : 'Unable to refresh dashboard data right now.';
    } finally {
        refreshing.value = false;
    }
};

const joinedAgo = (value) => {
    if (!value) {
        return '—';
    }

    return dayjs(value).fromNow();
};

const initials = (name = '') => {
    const parts = String(name).trim().split(/\s+/).filter(Boolean);
    return (parts.slice(0, 2).map((part) => part[0]).join('') || '?').toUpperCase();
};

const openUserDrawer = async (signup) => {
    drawerOpen.value = true;
    drawerLoading.value = true;
    drawerUser.value = null;
    drawerTimeline.value = [];
    drawerError.value = '';

    try {
        const { data } = await axios.get(`/api/admin/users/${signup.id}`);
        drawerUser.value = data.user || null;
        drawerTimeline.value = data.timeline || [];
    } catch (error) {
        drawerError.value = error?.response?.status === 401
            ? 'Not authorized to load profile details. Please refresh and sign in again.'
            : 'Failed to load this profile. Please try again.';
    } finally {
        drawerLoading.value = false;
    }
};

const closeDrawer = () => {
    drawerOpen.value = false;
    drawerLoading.value = false;
    drawerUser.value = null;
    drawerTimeline.value = [];
    drawerError.value = '';
};

const formatDate = (value) => {
    if (!value) {
        return '—';
    }

    return dayjs(value).format('MMM D, YYYY');
};

onMounted(() => {
    renderChart();

    pollTimer = window.setInterval(() => {
        if (document.visibilityState === 'visible') {
            refreshDashboard({ silent: true });
        }
    }, 30000);
});

onBeforeUnmount(() => {
    destroyChart(dashboardChart);
    clearTimeout(actionPulseTimer);
    clearTimeout(signupPulseTimer);
    clearInterval(pollTimer);
});
</script>

<template>
    <AdminLayout page-title="Dashboard" :breadcrumb="['Admin', 'Dashboard']">
        <div class="dashboard-page">
            <section v-if="showAlertStrip" class="alert-strip">
                <div class="alert-main">
                    <ExclamationTriangleIcon class="alert-icon" />
                    <div>
                        <p>
                            <span class="alert-warning">⚠</span>
                            You have {{ pendingVerificationCount }} pending teacher verifications and
                            {{ unreadReportsCount }} unread reports requiring attention.
                        </p>
                        <p class="alert-hint">
                            Recommended order: triage high-risk reports first, then clear verification backlog within 48 hours.
                        </p>
                    </div>
                </div>
                <div class="alert-links">
                    <Link :href="route('admin.verifications')">Open verifications</Link>
                    <Link :href="route('admin.reports')">Open reports</Link>
                </div>
            </section>

            <section class="dashboard-toolbar">
                <div>
                    <h2>Operations snapshot</h2>
                    <p>Problems first, then platform health and activity.</p>
                </div>
                <button type="button" class="admin-btn admin-btn-secondary" :disabled="refreshing" @click="refreshDashboard">
                    <ArrowPathIcon class="icon-16" :class="{ spinning: refreshing }" />
                    {{ refreshing ? 'Refreshing...' : 'Refresh' }}
                </button>
            </section>

            <p v-if="dashboardError" class="error-copy">{{ dashboardError }}</p>

            <section class="stats-grid">
                <AdminStatCard
                    v-for="card in statCards"
                    :key="card.label"
                    :label="card.label"
                    :value="card.value"
                    :trend="card.trend"
                    :prefix="card.prefix || ''"
                    :pulse-seed="statPulseSeed"
                />
            </section>

            <section class="content-grid">
                <article class="panel">
                    <header class="panel-header">
                        <h3>Recent signups</h3>
                        <p>Latest activity feed</p>
                    </header>

                    <div class="signup-table-wrap">
                        <table class="signup-table">
                            <thead>
                                <tr>
                                    <th>User</th>
                                    <th>Role</th>
                                    <th>Joined</th>
                                    <th>Status</th>
                                </tr>
                            </thead>
                            <TransitionGroup tag="tbody" name="activity-row">
                                <tr
                                    v-for="signup in recentSignups"
                                    :key="signup.id"
                                    :class="{ 'new-activity-row': newSignupIds.includes(signup.id) }"
                                    @click="openUserDrawer(signup)"
                                >
                                    <td>
                                        <div class="signup-user">
                                            <span class="signup-avatar">{{ initials(signup.name) }}</span>
                                            <span class="signup-name">{{ signup.name }}</span>
                                        </div>
                                    </td>
                                    <td>
                                        <span class="role-pill" :class="getRoleTone(signup.role)">
                                            {{ signup.role }}
                                        </span>
                                    </td>
                                    <td class="joined-cell">{{ joinedAgo(signup.created_at) }}</td>
                                    <td>
                                        <AdminStatusBadge :status="signup.status" />
                                    </td>
                                </tr>
                                <tr v-if="!recentSignups.length" key="empty-signups">
                                    <td colspan="4" class="empty-cell">No recent signups.</td>
                                </tr>
                            </TransitionGroup>
                        </table>
                    </div>
                </article>

                <article class="panel">
                    <header class="panel-header">
                        <h3>Pending actions</h3>
                        <p>Immediate tasks by urgency</p>
                    </header>

                    <div v-if="dashboardData.pending_actions.length" class="actions-stack">
                        <Link
                            v-for="action in dashboardData.pending_actions"
                            :key="action.id"
                            :href="action.url"
                            class="action-card"
                            :class="[
                                `urgency-${getUrgencyTone(action.urgency)}`,
                                actionPulseIds.includes(action.id) ? 'new-action' : '',
                            ]"
                        >
                            <strong>{{ action.label }}</strong>
                            <span>{{ action.sub }}</span>
                        </Link>
                    </div>

                    <p v-else class="empty-copy">No pending actions right now.</p>
                </article>
            </section>

            <section class="panel chart-panel">
                <header class="chart-header">
                    <h3>Sessions and new users</h3>
                    <div class="custom-legend">
                        <span><i class="legend-dot sessions" /> Sessions</span>
                        <span><i class="legend-dot users" /> New users</span>
                    </div>
                </header>
                <div class="chart-wrap">
                    <canvas ref="chartCanvas" />
                </div>
            </section>
        </div>

        <AdminDrawer
            :open="drawerOpen"
            :loading="drawerLoading"
            :title="drawerUser?.name || 'User profile'"
            :subtitle="drawerUser?.email || ''"
            @close="closeDrawer"
        >
            <p v-if="drawerError" class="drawer-error-copy">{{ drawerError }}</p>

            <template v-if="drawerUser">
                <section class="drawer-profile">
                    <span class="drawer-avatar">{{ initials(drawerUser.name) }}</span>
                    <div>
                        <h4>{{ drawerUser.name }}</h4>
                        <p>{{ drawerUser.email }}</p>
                    </div>
                </section>

                <section class="drawer-meta">
                    <article>
                        <span>Role</span>
                        <strong>{{ drawerUser.role }}</strong>
                    </article>
                    <article>
                        <span>Status</span>
                        <strong>{{ drawerUser.status }}</strong>
                    </article>
                    <article>
                        <span>Phone</span>
                        <strong>{{ drawerUser.phone || '—' }}</strong>
                    </article>
                    <article>
                        <span>Joined</span>
                        <strong>{{ formatDate(drawerUser.created_at) }}</strong>
                    </article>
                </section>

                <section class="drawer-timeline">
                    <h5>Timeline</h5>
                    <ul>
                        <li v-for="event in drawerTimeline" :key="`${event.event}-${event.date}`">
                            <strong>{{ event.event }}</strong>
                            <span>{{ formatDate(event.date) }}</span>
                        </li>
                        <li v-if="!drawerTimeline.length" class="empty-timeline">No timeline entries available.</li>
                    </ul>
                </section>
            </template>

            <template #footer>
                <button type="button" class="admin-btn admin-btn-secondary" @click="closeDrawer">
                    Close
                </button>
            </template>
        </AdminDrawer>
    </AdminLayout>
</template>

<style scoped>
.dashboard-page {
    display: flex;
    flex-direction: column;
    gap: 14px;
}

.error-copy,
.drawer-error-copy {
    margin: 0;
    padding: 10px 12px;
    border-radius: 8px;
    border: 1px solid #fecaca;
    background: #fff1f2;
    color: #b91c1c;
    font-size: 13px;
    font-weight: 600;
}

.drawer-error-copy {
    margin-bottom: 10px;
}

.alert-strip {
    border-left: 4px solid #d97706;
    background: #fffbeb;
    border-radius: 8px;
    padding: 10px 14px;
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 12px;
}

.alert-main {
    display: inline-flex;
    align-items: center;
    gap: 10px;
    min-width: 0;
}

.alert-icon {
    width: 18px;
    height: 18px;
    color: #d97706;
    flex: 0 0 auto;
}

.alert-main p {
    margin: 0;
    color: #92400e;
    font-size: 13px;
    font-weight: 600;
}

.alert-hint {
    margin-top: 4px;
    font-size: 12px;
    font-weight: 600;
    color: #b45309;
}

.alert-warning {
    margin-right: 4px;
}

.alert-links {
    display: inline-flex;
    align-items: center;
    gap: 10px;
}

.alert-links a {
    color: #92400e;
    font-size: 12px;
    font-weight: 700;
    text-decoration: none;
    white-space: nowrap;
}

.dashboard-toolbar {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 10px;
}

.dashboard-toolbar h2 {
    margin: 0;
    font-size: 18px;
    color: #2D2D2D;
    font-weight: 700;
}

.dashboard-toolbar p {
    margin: 2px 0 0;
    font-size: 12px;
    color: #9CA3AF;
}

.stats-grid {
    display: grid;
    grid-template-columns: repeat(4, minmax(0, 1fr));
    gap: 10px;
}

.content-grid {
    display: grid;
    grid-template-columns: minmax(0, 2fr) minmax(0, 1fr);
    gap: 12px;
}

.panel {
    background: #ffffff;
    border: 1px solid #e2e8f0;
    border-radius: 8px;
    padding: 14px;
}

.panel-header h3,
.chart-header h3 {
    margin: 0;
    font-size: 15px;
    color: #2D2D2D;
    font-weight: 700;
}

.panel-header p {
    margin: 2px 0 0;
    font-size: 12px;
    color: #9CA3AF;
}

.signup-table-wrap {
    margin-top: 8px;
    border: 1px solid #e2e8f0;
    border-radius: 8px;
    overflow: hidden;
}

.signup-table {
    width: 100%;
    border-collapse: collapse;
}

.signup-table thead {
    background: #FFF8F0;
}

.signup-table th {
    text-align: left;
    font-size: 11px;
    letter-spacing: 0.06em;
    text-transform: uppercase;
    color: #9CA3AF;
    font-weight: 600;
    padding: 8px 12px;
    border-bottom: 1px solid #e2e8f0;
}

.signup-table td {
    height: 40px;
    padding: 0 12px;
    border-bottom: 1px solid #f1f5f9;
    font-size: 13px;
    color: #2D2D2D;
}

.signup-table tr:last-child td {
    border-bottom: none;
}

.signup-table tbody tr {
    cursor: pointer;
    transition: background-color 100ms ease;
}

.signup-table tbody tr:hover {
    background: #FFF8F0;
}

.activity-row-enter-active {
    transition: transform 280ms cubic-bezier(0.16, 1, 0.3, 1), opacity 220ms ease;
}

.activity-row-enter-from {
    opacity: 0;
    transform: translateY(-14px);
}

.activity-row-move {
    transition: transform 280ms cubic-bezier(0.16, 1, 0.3, 1);
}

.new-activity-row {
    box-shadow: inset 3px 0 0 #E8553E;
    animation: activity-entry-accent 280ms ease forwards;
}

.signup-user {
    display: inline-flex;
    align-items: center;
    gap: 8px;
}

.signup-avatar,
.drawer-avatar {
    border-radius: 999px;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    font-weight: 700;
}

.signup-avatar {
    width: 28px;
    height: 28px;
    background: #FFE7DD;
    color: #D44433;
    font-size: 11px;
}

.signup-name {
    font-weight: 600;
}

.role-pill {
    display: inline-flex;
    align-items: center;
    border-radius: 999px;
    padding: 2px 8px;
    font-size: 11px;
    font-weight: 700;
    text-transform: capitalize;
}

.role-pill.teacher {
    background: #FFE7DD;
    color: #E8553E;
}

.role-pill.student {
    background: #dcfce7;
    color: #16a34a;
}

.joined-cell {
    color: #9CA3AF;
}

.empty-cell {
    text-align: center;
    color: #9CA3AF;
}

.actions-stack {
    margin-top: 8px;
    display: flex;
    flex-direction: column;
    gap: 8px;
}

.action-card {
    border: 1px solid #e2e8f0;
    border-radius: 8px;
    border-left-width: 4px;
    padding: 12px 16px;
    text-decoration: none;
    background: #ffffff;
}

.action-card strong {
    display: block;
    color: #2D2D2D;
    font-size: 13px;
    font-weight: 600;
}

.action-card span {
    margin-top: 3px;
    display: block;
    color: #9CA3AF;
    font-size: 12px;
}

.action-card.urgency-high {
    border-left-color: #dc2626;
}

.action-card.urgency-medium {
    border-left-color: #d97706;
}

.action-card.urgency-low {
    border-left-color: #E8553E;
}

.action-card.new-action {
    animation: action-border-pulse 280ms ease;
}

.empty-copy {
    margin: 8px 0 0;
    color: #9CA3AF;
    font-size: 13px;
}

.chart-panel {
    padding-bottom: 10px;
}

.chart-header {
    margin-bottom: 10px;
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 10px;
}

.custom-legend {
    display: inline-flex;
    align-items: center;
    gap: 10px;
    color: #9CA3AF;
    font-size: 12px;
}

.custom-legend span {
    display: inline-flex;
    align-items: center;
    gap: 6px;
}

.legend-dot {
    width: 8px;
    height: 8px;
    border-radius: 999px;
    display: inline-block;
}

.legend-dot.sessions {
    background: #E8553E;
}

.legend-dot.users {
    background: #16a34a;
}

.chart-wrap {
    height: 220px;
}

.icon-16 {
    width: 16px;
    height: 16px;
}

.spinning {
    animation: spin 280ms linear infinite;
}

.drawer-profile {
    display: flex;
    align-items: center;
    gap: 10px;
    margin-bottom: 14px;
}

.drawer-avatar {
    width: 42px;
    height: 42px;
    background: #D44433;
    color: #ffffff;
    font-size: 13px;
}

.drawer-profile h4 {
    margin: 0;
    color: #2D2D2D;
    font-size: 16px;
    font-weight: 700;
}

.drawer-profile p {
    margin: 2px 0 0;
    color: #9CA3AF;
    font-size: 13px;
}

.drawer-meta {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 10px;
    margin-bottom: 16px;
}

.drawer-meta article {
    border: 1px solid #e2e8f0;
    border-radius: 8px;
    padding: 10px;
}

.drawer-meta span {
    display: block;
    color: #9CA3AF;
    font-size: 11px;
}

.drawer-meta strong {
    margin-top: 4px;
    display: block;
    color: #2D2D2D;
    font-size: 13px;
    text-transform: capitalize;
}

.drawer-timeline h5 {
    margin: 0 0 8px;
    font-size: 13px;
    color: #2D2D2D;
}

.drawer-timeline ul {
    list-style: none;
    margin: 0;
    padding: 0;
}

.drawer-timeline li {
    padding: 8px 0;
    border-bottom: 1px solid #f1f5f9;
}

.drawer-timeline li:last-child {
    border-bottom: none;
}

.drawer-timeline strong {
    display: block;
    font-size: 13px;
    color: #2D2D2D;
}

.drawer-timeline span {
    margin-top: 2px;
    display: block;
    font-size: 12px;
    color: #9CA3AF;
}

.empty-timeline {
    color: #9CA3AF;
    font-size: 12px;
}

@keyframes action-border-pulse {
    0% {
        border-left-color: #ffffff;
    }
    100% {
        border-left-color: inherit;
    }
}

@keyframes activity-entry-accent {
    0% {
        background: #eff6ff;
        box-shadow: inset 3px 0 0 #E8553E;
    }

    100% {
        background: transparent;
        box-shadow: inset 0 0 0 transparent;
    }
}

@keyframes spin {
    from {
        transform: rotate(0deg);
    }
    to {
        transform: rotate(360deg);
    }
}

@media (max-width: 1260px) {
    .stats-grid {
        grid-template-columns: repeat(2, minmax(0, 1fr));
    }

    .content-grid {
        grid-template-columns: 1fr;
    }
}

@media (max-width: 900px) {
    .alert-strip,
    .dashboard-toolbar,
    .chart-header {
        flex-direction: column;
        align-items: flex-start;
    }

    .drawer-meta {
        grid-template-columns: 1fr;
    }
}
</style>
