<script setup>
import TeacherLayout from '@/Layouts/TeacherLayout.vue';
import EmptyState from '@/Components/Shared/EmptyState.vue';
import { computed, ref } from 'vue';
import { Head, Link } from '@inertiajs/vue3';

const props = defineProps({
    earnings: {
        type: Object,
        default: () => ({ data: [], links: [] }),
    },
    summary: {
        type: Object,
        default: () => ({
            this_month: 0,
            this_week: 0,
            total: 0,
            pending: 0,
            released_count: 0,
            pending_count: 0,
        }),
    },
    free_sessions: {
        type: Number,
        default: 0,
    },
    commission_rate: {
        type: Number,
        default: 0.12,
    },
    payout_delay_hours: {
        type: Number,
        default: 24,
    },
});

const statusFilter = ref('all');

const filteredEarnings = computed(() => {
    const rows = props.earnings?.data || [];
    if (statusFilter.value === 'all') return rows;
    return rows.filter((row) => (row.status || 'released') === statusFilter.value);
});

const formatINR = (amount) => {
    return new Intl.NumberFormat('en-IN', {
        style: 'currency',
        currency: 'INR',
        maximumFractionDigits: 2,
    }).format(Number(amount || 0));
};

const formatDate = (value) => {
    if (!value) return '—';
    return new Date(value).toLocaleDateString(undefined, {
        year: 'numeric',
        month: 'short',
        day: 'numeric',
    });
};

const formatDateTime = (value) => {
    if (!value) return '—';
    return new Date(value).toLocaleString(undefined, {
        year: 'numeric',
        month: 'short',
        day: 'numeric',
        hour: '2-digit',
        minute: '2-digit',
    });
};

const statusBadgeClass = (status) => {
    if (status === 'released') return 'earnings-badge earnings-badge--released';
    if (status === 'pending') return 'earnings-badge earnings-badge--pending';
    return 'earnings-badge earnings-badge--neutral';
};

const payoutLabel = (row) => {
    if (row.status === 'pending') return 'Awaiting session completion + payout delay';
    return row.payout_date ? formatDate(row.payout_date) : formatDate(row.created_at);
};

const summaryCards = computed(() => [
    {
        label: 'This month',
        value: formatINR(props.summary.this_month),
        hint: 'Released earnings so far this month',
        tone: 'primary',
    },
    {
        label: 'This week',
        value: formatINR(props.summary.this_week),
        hint: 'Released earnings in the current calendar week',
        tone: 'info',
    },
    {
        label: 'Pending payout',
        value: formatINR(props.summary.pending),
        hint: `${props.summary.pending_count || 0} session(s) awaiting release`,
        tone: 'warning',
    },
    {
        label: 'Lifetime earnings',
        value: formatINR(props.summary.total),
        hint: `${props.summary.released_count || 0} released payout(s)`,
        tone: 'success',
    },
    {
        label: 'Free sessions',
        value: String(props.free_sessions || 0),
        hint: 'Volunteer sessions completed',
        tone: 'neutral',
    },
]);

const hasRows = computed(() => filteredEarnings.value.length > 0);

const paginationLinks = computed(() => props.earnings?.links || []);
</script>

<template>
    <Head title="Earnings" />

    <TeacherLayout page-title="Earnings">
        <div class="earnings-page">
            <section class="hero-card">
                <div>
                    <p class="eyebrow">Payouts</p>
                    <h1>Earnings &amp; payouts</h1>
                    <p class="hero-copy">
                        Track released earnings, pending payouts, and your volunteer sessions. Funds are released
                        {{ payout_delay_hours }} hours after a session is marked complete.
                    </p>
                </div>
                <div class="hero-status">
                    Platform fee: <strong>{{ Math.round(commission_rate * 100) }}%</strong>
                </div>
            </section>

            <section class="stats-grid">
                <article
                    v-for="card in summaryCards"
                    :key="card.label"
                    class="stat-card"
                    :class="`stat-card--${card.tone}`"
                >
                    <span class="stat-label">{{ card.label }}</span>
                    <strong class="stat-value">{{ card.value }}</strong>
                    <p class="stat-hint">{{ card.hint }}</p>
                </article>
            </section>

            <section class="panel">
                <header class="panel-header">
                    <h2>Payout history</h2>
                    <div class="filter-group" role="tablist" aria-label="Earnings status filter">
                        <button
                            type="button"
                            class="filter-chip"
                            :class="{ 'filter-chip--active': statusFilter === 'all' }"
                            @click="statusFilter = 'all'"
                        >
                            All
                        </button>
                        <button
                            type="button"
                            class="filter-chip"
                            :class="{ 'filter-chip--active': statusFilter === 'released' }"
                            @click="statusFilter = 'released'"
                        >
                            Released
                        </button>
                        <button
                            type="button"
                            class="filter-chip"
                            :class="{ 'filter-chip--active': statusFilter === 'pending' }"
                            @click="statusFilter = 'pending'"
                        >
                            Pending
                        </button>
                    </div>
                </header>

                <div v-if="hasRows" class="earnings-table-wrapper">
                    <table class="earnings-table">
                        <thead>
                            <tr>
                                <th scope="col">Session</th>
                                <th scope="col">Student</th>
                                <th scope="col">Subject</th>
                                <th scope="col">Gross</th>
                                <th scope="col">Platform fee</th>
                                <th scope="col">Net payout</th>
                                <th scope="col">Status</th>
                                <th scope="col">Payout date</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="row in filteredEarnings" :key="row.id">
                                <td>
                                    <span class="cell-session">#{{ row.booking_id }}</span>
                                    <span class="cell-sub">{{ formatDateTime(row.booking?.start_at) }}</span>
                                </td>
                                <td>{{ row.booking?.student?.name || '—' }}</td>
                                <td>
                                    <span v-if="row.booking?.subject" class="cell-tag">{{ row.booking.subject }}</span>
                                    <span v-else class="cell-muted">—</span>
                                </td>
                                <td>{{ formatINR(row.gross_amount) }}</td>
                                <td class="cell-fee">{{ formatINR(row.platform_fee) }}</td>
                                <td class="cell-net">{{ formatINR(row.net_amount) }}</td>
                                <td>
                                    <span :class="statusBadgeClass(row.status)">{{ row.status || 'released' }}</span>
                                </td>
                                <td>{{ payoutLabel(row) }}</td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <EmptyState
                    v-else
                    title="No earnings yet"
                    description="Once a paid session is marked complete, the platform fee is deducted and the net amount appears here within 24 hours."
                />

                <nav v-if="paginationLinks.length > 3" class="pagination" aria-label="Earnings pagination">
                    <template v-for="(link, idx) in paginationLinks" :key="`${idx}-${link.label}`">
                        <Link
                            v-if="link.url"
                            :href="link.url"
                            class="pagination-link"
                            :class="{ 'pagination-link--active': link.active }"
                            v-html="link.label"
                        />
                        <span v-else class="pagination-link pagination-link--disabled" v-html="link.label" />
                    </template>
                </nav>
            </section>
        </div>
    </TeacherLayout>
</template>

<style scoped>
.earnings-page {
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
    max-width: 560px;
}

.hero-status {
    border-radius: 999px;
    padding: 6px 12px;
    font-size: 12px;
    font-weight: 700;
    background: #FFE7DD;
    color: var(--s-coral-dark, #B53A2D);
    text-transform: uppercase;
    letter-spacing: 0.05em;
    white-space: nowrap;
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
    padding: 14px;
    display: flex;
    flex-direction: column;
    gap: 4px;
}

.stat-label {
    font-size: 12px;
    color: #9CA3AF;
    text-transform: uppercase;
    letter-spacing: 0.05em;
    font-weight: 700;
}

.stat-value {
    font-size: 22px;
    color: #2D2D2D;
}

.stat-hint {
    margin: 0;
    font-size: 12px;
    color: #64748B;
}

.stat-card--primary { border-top: 3px solid #E8553E; }
.stat-card--info { border-top: 3px solid #2563EB; }
.stat-card--warning { border-top: 3px solid #D97706; }
.stat-card--success { border-top: 3px solid #15803D; }
.stat-card--neutral { border-top: 3px solid #6B7280; }

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
    gap: 8px;
    flex-wrap: wrap;
}

.panel-header h2 {
    margin: 0;
    font-size: 20px;
    color: #2D2D2D;
}

.filter-group {
    display: flex;
    gap: 6px;
}

.filter-chip {
    min-height: 36px;
    padding: 0 14px;
    border: 1px solid #f0e8e0;
    border-radius: 999px;
    background: #fff;
    color: #2D2D2D;
    font-weight: 600;
    font-size: 13px;
    cursor: pointer;
    transition: background-color 150ms ease, color 150ms ease, border-color 150ms ease;
}

.filter-chip:hover { background: #FFF3EF; }

.filter-chip--active {
    background: #E8553E;
    color: #fff;
    border-color: #E8553E;
}

.earnings-table-wrapper {
    overflow-x: auto;
}

.earnings-table {
    width: 100%;
    border-collapse: collapse;
    font-size: 14px;
}

.earnings-table th,
.earnings-table td {
    text-align: left;
    padding: 10px 12px;
    border-bottom: 1px solid #f0e8e0;
    vertical-align: top;
}

.earnings-table th {
    font-size: 12px;
    color: #9CA3AF;
    text-transform: uppercase;
    letter-spacing: 0.05em;
}

.earnings-table tr:last-child td {
    border-bottom: none;
}

.cell-session {
    display: block;
    font-weight: 700;
    color: #2D2D2D;
}

.cell-sub {
    display: block;
    font-size: 12px;
    color: #64748B;
    margin-top: 2px;
}

.cell-tag {
    display: inline-block;
    padding: 2px 8px;
    background: #FFF3EF;
    color: #B53A2D;
    border-radius: 6px;
    font-size: 12px;
    font-weight: 600;
}

.cell-muted { color: #9CA3AF; }

.cell-fee { color: #B91C1C; }

.cell-net {
    color: #15803D;
    font-weight: 700;
}

.earnings-badge {
    display: inline-block;
    padding: 2px 8px;
    border-radius: 999px;
    font-size: 12px;
    font-weight: 700;
    text-transform: capitalize;
}

.earnings-badge--released {
    background: #DCFCE7;
    color: #15803D;
}

.earnings-badge--pending {
    background: #FEF3C7;
    color: #D97706;
}

.earnings-badge--neutral {
    background: #E2E8F0;
    color: #334155;
}

.pagination {
    margin-top: 12px;
    display: flex;
    gap: 4px;
    flex-wrap: wrap;
    justify-content: center;
}

.pagination-link {
    min-width: 36px;
    height: 36px;
    padding: 0 10px;
    border: 1px solid #f0e8e0;
    border-radius: 6px;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    text-decoration: none;
    color: #2D2D2D;
    font-size: 13px;
    font-weight: 600;
}

.pagination-link:hover { background: #FFF3EF; }

.pagination-link--active {
    background: #E8553E;
    color: #fff;
    border-color: #E8553E;
}

.pagination-link--disabled {
    opacity: 0.4;
    pointer-events: none;
}

@media (max-width: 1100px) {
    .stats-grid { grid-template-columns: repeat(2, minmax(0, 1fr)); }
    .hero-card { flex-direction: column; }
}
</style>
