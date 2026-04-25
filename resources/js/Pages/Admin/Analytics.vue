<script setup>
import axios from 'axios';
import dayjs from 'dayjs';
import { ArrowPathIcon } from '@heroicons/vue/24/outline';
import { computed, nextTick, onBeforeUnmount, onMounted, ref } from 'vue';
import AdminAnimatedValue from '@/Components/Admin/AdminAnimatedValue.vue';
import AdminDateRangePicker from '@/Components/Admin/AdminDateRangePicker.vue';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import { useAdminChart } from '@/composables/useAdminChart';

const { createChart, createLineDataset, createBarDataset, destroyChart, replayChart, colors } = useAdminChart();

const tabs = ['overview', 'users', 'revenue', 'sessions'];
const activeTab = ref('overview');

const preset = ref('30d');
const customFrom = ref('');
const customTo = ref('');

const loading = ref(false);
const data = ref({});

const overviewCanvas = ref(null);
const usersCanvas = ref(null);
const revenueLineCanvas = ref(null);
const revenueDoughnutCanvas = ref(null);
const sessionsCanvas = ref(null);

const hiddenRevenueIndexes = ref([]);

const charts = {
    overview: null,
    users: null,
    revenueLine: null,
    revenueDoughnut: null,
    sessions: null,
};

const getDateRange = () => {
    const today = dayjs();

    if (preset.value === 'custom') {
        return {
            from: customFrom.value || today.format('YYYY-MM-DD'),
            to: customTo.value || today.format('YYYY-MM-DD'),
        };
    }

    const daysMap = {
        today: 0,
        '7d': 6,
        '30d': 29,
        '90d': 89,
    };

    const days = daysMap[preset.value] ?? 29;

    return {
        from: today.subtract(days, 'day').format('YYYY-MM-DD'),
        to: today.format('YYYY-MM-DD'),
    };
};

const endpointForTab = (tab) => {
    const map = {
        overview: 'overview',
        users: 'users',
        revenue: 'revenue',
        sessions: 'sessions',
    };

    return map[tab] || 'overview';
};

const formatNumber = (value) => {
    const numeric = Number(value);
    return Number.isFinite(numeric) ? numeric : 0;
};

const updateChart = (chart) => {
    chart.options.animation = {
        ...(chart.options.animation || {}),
        duration: 600,
        easing: 'easeOutQuart',
    };
    chart.update();
};

const destroyAllCharts = () => {
    Object.keys(charts).forEach((key) => {
        destroyChart(charts[key]);
        charts[key] = null;
    });
};

const renderOverviewChart = () => {
    const labels = Object.keys(data.value.daily_users || {});

    charts.overview = createChart(overviewCanvas.value, {
        type: 'line',
        data: {
            labels,
            datasets: [
                createLineDataset('New users', Object.values(data.value.daily_users || {}), colors.blue),
                createLineDataset('Sessions', labels.map((label) => data.value.daily_sessions?.[label] || 0), colors.green),
            ],
        },
        options: {
            interaction: { mode: 'index', intersect: false },
            scales: {
                y: { beginAtZero: true },
            },
        },
    });
};

const updateOverviewChart = () => {
    if (!charts.overview) {
        renderOverviewChart();
        return;
    }

    const labels = Object.keys(data.value.daily_users || {});
    charts.overview.data.labels = labels;
    charts.overview.data.datasets[0].data = Object.values(data.value.daily_users || {});
    charts.overview.data.datasets[1].data = labels.map((label) => data.value.daily_sessions?.[label] || 0);
    updateChart(charts.overview);
};

const renderUsersChart = () => {
    const labels = (data.value.weekly_students || []).map((item) => item.week);

    charts.users = createChart(usersCanvas.value, {
        type: 'bar',
        data: {
            labels,
            datasets: [
                createBarDataset('Students', (data.value.weekly_students || []).map((item) => item.count), colors.blue),
                createBarDataset('Teachers', (data.value.weekly_teachers || []).map((item) => item.count), colors.green),
            ],
        },
        options: {
            scales: {
                y: { beginAtZero: true },
            },
        },
    });
};

const updateUsersChart = () => {
    if (!charts.users) {
        renderUsersChart();
        return;
    }

    const labels = (data.value.weekly_students || []).map((item) => item.week);
    charts.users.data.labels = labels;
    charts.users.data.datasets[0].data = (data.value.weekly_students || []).map((item) => item.count);
    charts.users.data.datasets[1].data = (data.value.weekly_teachers || []).map((item) => item.count);
    updateChart(charts.users);
};

const renderRevenueLineChart = () => {
    charts.revenueLine = createChart(revenueLineCanvas.value, {
        type: 'line',
        data: {
            labels: (data.value.daily_revenue || []).map((item) => item.date),
            datasets: [
                createLineDataset('Revenue', (data.value.daily_revenue || []).map((item) => item.total), colors.blue),
            ],
        },
        options: {
            scales: {
                y: { beginAtZero: true },
            },
        },
    });
};

const updateRevenueLineChart = () => {
    if (!charts.revenueLine) {
        renderRevenueLineChart();
        return;
    }

    charts.revenueLine.data.labels = (data.value.daily_revenue || []).map((item) => item.date);
    charts.revenueLine.data.datasets[0].data = (data.value.daily_revenue || []).map((item) => item.total);
    updateChart(charts.revenueLine);
};

const renderRevenueDoughnutChart = () => {
    charts.revenueDoughnut = createChart(revenueDoughnutCanvas.value, {
        type: 'doughnut',
        data: {
            labels: ['Paid sessions', 'Free sessions'],
            datasets: [
                {
                    data: [formatNumber(data.value.paid_sessions), formatNumber(data.value.free_sessions)],
                    backgroundColor: [colors.blue, colors.green],
                    borderRadius: 8,
                    hoverOffset: 12,
                },
            ],
        },
        options: {
            cutout: '66%',
            scales: {
                x: { display: false },
                y: { display: false },
            },
        },
    });

    hiddenRevenueIndexes.value.forEach((index) => {
        charts.revenueDoughnut?.toggleDataVisibility(index);
    });

    charts.revenueDoughnut?.update('none');
};

const updateRevenueDoughnutChart = () => {
    if (!charts.revenueDoughnut) {
        renderRevenueDoughnutChart();
        return;
    }

    charts.revenueDoughnut.data.datasets[0].data = [
        formatNumber(data.value.paid_sessions),
        formatNumber(data.value.free_sessions),
    ];

    updateChart(charts.revenueDoughnut);
};

const renderSessionsChart = () => {
    const subjects = Object.entries(data.value.by_subject || {});

    charts.sessions = createChart(sessionsCanvas.value, {
        type: 'bar',
        data: {
            labels: subjects.map(([label]) => label),
            datasets: [
                createBarDataset('Sessions', subjects.map(([, value]) => value), colors.green),
            ],
        },
        options: {
            indexAxis: 'y',
            scales: {
                x: { beginAtZero: true },
            },
        },
    });
};

const updateSessionsChart = () => {
    if (!charts.sessions) {
        renderSessionsChart();
        return;
    }

    const subjects = Object.entries(data.value.by_subject || {});
    charts.sessions.data.labels = subjects.map(([label]) => label);
    charts.sessions.data.datasets[0].data = subjects.map(([, value]) => value);
    updateChart(charts.sessions);
};

const renderActiveTabCharts = () => {
    if (activeTab.value === 'overview') {
        renderOverviewChart();
        return;
    }

    if (activeTab.value === 'users') {
        renderUsersChart();
        return;
    }

    if (activeTab.value === 'revenue') {
        renderRevenueLineChart();
        renderRevenueDoughnutChart();
        return;
    }

    renderSessionsChart();
};

const updateActiveTabCharts = () => {
    if (activeTab.value === 'overview') {
        updateOverviewChart();
        return;
    }

    if (activeTab.value === 'users') {
        updateUsersChart();
        return;
    }

    if (activeTab.value === 'revenue') {
        updateRevenueLineChart();
        updateRevenueDoughnutChart();
        return;
    }

    updateSessionsChart();
};

const fetchData = async (mode = 'range') => {
    loading.value = true;

    try {
        const { from, to } = getDateRange();
        const endpoint = endpointForTab(activeTab.value);
        const { data: response } = await axios.get(`/api/admin/analytics/${endpoint}`, {
            params: { from, to },
        });

        data.value = response;
        await nextTick();

        if (mode === 'tab' || mode === 'init') {
            destroyAllCharts();
            await replayChart(() => {
                renderActiveTabCharts();
            }, null, 80);
            return;
        }

        updateActiveTabCharts();
    } finally {
        loading.value = false;
    }
};

const onTabClick = (tab) => {
    if (activeTab.value === tab) {
        return;
    }

    activeTab.value = tab;
    fetchData('tab');
};

const onRangeChange = () => {
    fetchData('range');
};

const refresh = () => {
    fetchData('range');
};

const toggleRevenueLegend = (index) => {
    const hidden = new Set(hiddenRevenueIndexes.value);

    if (hidden.has(index)) {
        hidden.delete(index);
    } else {
        hidden.add(index);
    }

    hiddenRevenueIndexes.value = Array.from(hidden);

    if (!charts.revenueDoughnut) {
        return;
    }

    charts.revenueDoughnut.toggleDataVisibility(index);
    charts.revenueDoughnut.options.animation = {
        ...(charts.revenueDoughnut.options.animation || {}),
        duration: 320,
        easing: 'easeOutQuart',
    };
    charts.revenueDoughnut.update();
};

const revenueLegendItems = computed(() => [
    {
        index: 0,
        label: 'Paid sessions',
        color: colors.blue,
        hidden: hiddenRevenueIndexes.value.includes(0),
    },
    {
        index: 1,
        label: 'Free sessions',
        color: colors.green,
        hidden: hiddenRevenueIndexes.value.includes(1),
    },
]);

const topCities = computed(() => data.value.top_cities || []);
const totalStudents = computed(() => (data.value.weekly_students || []).reduce((sum, item) => sum + formatNumber(item.count), 0));
const totalTeachers = computed(() => (data.value.weekly_teachers || []).reduce((sum, item) => sum + formatNumber(item.count), 0));

onMounted(() => {
    fetchData('init');
});

onBeforeUnmount(() => {
    destroyAllCharts();
});
</script>

<template>
    <AdminLayout page-title="Analytics" :breadcrumb="['Admin', 'Analytics']">
        <div class="analytics-page">
            <section class="analytics-header">
                <div>
                    <p class="eyebrow">Performance</p>
                    <h2>Analytics workspace</h2>
                </div>
                <button type="button" class="admin-btn admin-btn-secondary" @click="refresh">
                    <ArrowPathIcon class="icon-16" />
                    Refresh
                </button>
            </section>

            <section class="controls-row">
                <div class="tab-row">
                    <button
                        v-for="tab in tabs"
                        :key="tab"
                        type="button"
                        class="tab-button"
                        :class="{ active: activeTab === tab }"
                        @click="onTabClick(tab)"
                    >
                        {{ tab }}
                    </button>
                </div>

                <AdminDateRangePicker
                    :preset="preset"
                    :from="customFrom"
                    :to="customTo"
                    @update:preset="preset = $event"
                    @update:from="customFrom = $event"
                    @update:to="customTo = $event"
                    @change="onRangeChange"
                />
            </section>

            <Transition name="analytics-tab" mode="out-in">
                <section :key="activeTab" class="tab-content">
                    <template v-if="activeTab === 'overview'">
                        <div class="metric-grid four-up">
                            <article class="metric-card">
                                <span>Active users</span>
                                <strong><AdminAnimatedValue :value="formatNumber(data.total_users)" /></strong>
                            </article>
                            <article class="metric-card">
                                <span>New registrations</span>
                                <strong><AdminAnimatedValue :value="formatNumber(data.new_registrations)" /></strong>
                            </article>
                            <article class="metric-card">
                                <span>Completed sessions</span>
                                <strong><AdminAnimatedValue :value="formatNumber(data.sessions_completed)" /></strong>
                            </article>
                            <article class="metric-card">
                                <span>Total revenue</span>
                                <strong><AdminAnimatedValue :value="formatNumber(data.revenue)" prefix="INR " /></strong>
                            </article>
                        </div>

                        <article class="panel">
                            <header class="panel-header">
                                <h3>Growth trend</h3>
                                <div class="custom-legend">
                                    <span><i class="legend-dot blue" /> New users</span>
                                    <span><i class="legend-dot green" /> Sessions</span>
                                </div>
                            </header>
                            <div class="chart-container">
                                <canvas ref="overviewCanvas" />
                                <div v-if="loading" class="chart-overlay"><span class="chart-spinner" /></div>
                            </div>
                        </article>
                    </template>

                    <template v-else-if="activeTab === 'users'">
                        <div class="metric-grid two-up">
                            <article class="metric-card">
                                <span>Students (period)</span>
                                <strong><AdminAnimatedValue :value="totalStudents" /></strong>
                            </article>
                            <article class="metric-card">
                                <span>Teachers (period)</span>
                                <strong><AdminAnimatedValue :value="totalTeachers" /></strong>
                            </article>
                        </div>

                        <div class="split-grid">
                            <article class="panel">
                                <header class="panel-header">
                                    <h3>User growth by week</h3>
                                    <div class="custom-legend">
                                        <span><i class="legend-dot blue" /> Students</span>
                                        <span><i class="legend-dot green" /> Teachers</span>
                                    </div>
                                </header>
                                <div class="chart-container">
                                    <canvas ref="usersCanvas" />
                                    <div v-if="loading" class="chart-overlay"><span class="chart-spinner" /></div>
                                </div>
                            </article>

                            <article class="panel">
                                <header class="panel-header">
                                    <h3>Top cities</h3>
                                </header>
                                <table class="mini-table">
                                    <thead>
                                        <tr>
                                            <th>City</th>
                                            <th>Users</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <tr v-for="city in topCities" :key="city.name || city.city">
                                            <td>{{ city.name || city.city }}</td>
                                            <td>{{ city.count ?? city.users ?? 0 }}</td>
                                        </tr>
                                        <tr v-if="!topCities.length">
                                            <td colspan="2" class="empty-inline">No city data available.</td>
                                        </tr>
                                    </tbody>
                                </table>
                            </article>
                        </div>
                    </template>

                    <template v-else-if="activeTab === 'revenue'">
                        <div class="metric-grid two-up">
                            <article class="metric-card">
                                <span>Platform fees</span>
                                <strong><AdminAnimatedValue :value="formatNumber(data.platform_fees)" prefix="INR " /></strong>
                            </article>
                            <article class="metric-card">
                                <span>Teacher payouts</span>
                                <strong><AdminAnimatedValue :value="formatNumber(data.teacher_payouts)" prefix="INR " /></strong>
                            </article>
                        </div>

                        <div class="split-grid">
                            <article class="panel">
                                <header class="panel-header">
                                    <h3>Revenue over time</h3>
                                    <div class="custom-legend">
                                        <span><i class="legend-dot blue" /> Revenue</span>
                                    </div>
                                </header>
                                <div class="chart-container">
                                    <canvas ref="revenueLineCanvas" />
                                    <div v-if="loading" class="chart-overlay"><span class="chart-spinner" /></div>
                                </div>
                            </article>

                            <article class="panel">
                                <header class="panel-header">
                                    <h3>Session split</h3>
                                </header>
                                <div class="chart-container doughnut-wrap">
                                    <canvas ref="revenueDoughnutCanvas" />
                                    <div v-if="loading" class="chart-overlay"><span class="chart-spinner" /></div>
                                </div>
                                <ul class="revenue-legend">
                                    <li v-for="item in revenueLegendItems" :key="item.index">
                                        <button
                                            type="button"
                                            class="legend-button"
                                            :class="{ hidden: item.hidden }"
                                            @click="toggleRevenueLegend(item.index)"
                                        >
                                            <i class="legend-dot" :style="{ background: item.color }" />
                                            {{ item.label }}
                                        </button>
                                    </li>
                                </ul>
                            </article>
                        </div>
                    </template>

                    <template v-else>
                        <div class="metric-grid three-up">
                            <article class="metric-card">
                                <span>Average duration</span>
                                <strong><AdminAnimatedValue :value="formatNumber(data.avg_duration)" suffix=" min" /></strong>
                            </article>
                            <article class="metric-card">
                                <span>Completion rate</span>
                                <strong><AdminAnimatedValue :value="formatNumber(data.completion_rate)" suffix="%" /></strong>
                            </article>
                            <article class="metric-card">
                                <span>Most active teacher</span>
                                <strong>{{ data.most_active_teacher?.name || 'N/A' }}</strong>
                            </article>
                        </div>

                        <article class="panel">
                            <header class="panel-header">
                                <h3>Sessions by subject</h3>
                                <div class="custom-legend">
                                    <span><i class="legend-dot green" /> Sessions</span>
                                </div>
                            </header>
                            <div class="chart-container">
                                <canvas ref="sessionsCanvas" />
                                <div v-if="loading" class="chart-overlay"><span class="chart-spinner" /></div>
                            </div>
                        </article>
                    </template>
                </section>
            </Transition>
        </div>
    </AdminLayout>
</template>

<style scoped>
.analytics-page {
    display: flex;
    flex-direction: column;
    gap: 14px;
}

.analytics-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 12px;
}

.eyebrow {
    margin: 0;
    color: #9CA3AF;
    text-transform: uppercase;
    letter-spacing: 0.08em;
    font-size: 11px;
    font-weight: 600;
}

.analytics-header h2 {
    margin: 3px 0 0;
    color: #2D2D2D;
    font-size: 20px;
    font-weight: 700;
}

.controls-row {
    display: flex;
    align-items: flex-start;
    justify-content: space-between;
    gap: 10px;
}

.tab-row {
    display: flex;
    align-items: center;
    gap: 6px;
    flex-wrap: wrap;
}

.tab-button {
    min-height: 34px;
    border: 1px solid #F0E8E0;
    border-radius: 8px;
    background: #ffffff;
    color: #2D2D2D;
    padding: 0 12px;
    font-size: 12px;
    font-weight: 600;
    text-transform: capitalize;
    cursor: pointer;
}

.tab-button.active {
    border-color: #E8553E;
    background: #eff6ff;
    color: #D44433;
}

.tab-content {
    display: flex;
    flex-direction: column;
    gap: 12px;
}

.metric-grid {
    display: grid;
    gap: 10px;
}

.metric-grid.four-up {
    grid-template-columns: repeat(4, minmax(0, 1fr));
}

.metric-grid.three-up {
    grid-template-columns: repeat(3, minmax(0, 1fr));
}

.metric-grid.two-up {
    grid-template-columns: repeat(2, minmax(0, 1fr));
}

.metric-card {
    border: 1px solid #e2e8f0;
    border-radius: 8px;
    background: #ffffff;
    padding: 14px;
}

.metric-card span {
    display: block;
    font-size: 12px;
    color: #9CA3AF;
}

.metric-card strong {
    margin-top: 6px;
    display: block;
    font-size: 24px;
    color: #2D2D2D;
    line-height: 1.1;
}

.panel {
    border: 1px solid #e2e8f0;
    border-radius: 8px;
    background: #ffffff;
    padding: 14px;
}

.panel-header {
    margin-bottom: 10px;
    display: flex;
    justify-content: space-between;
    align-items: center;
    gap: 8px;
}

.panel-header h3 {
    margin: 0;
    font-size: 15px;
    color: #2D2D2D;
    font-weight: 700;
}

.custom-legend {
    display: inline-flex;
    align-items: center;
    gap: 10px;
    flex-wrap: wrap;
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

.legend-dot.blue {
    background: #E8553E;
}

.legend-dot.green {
    background: #16a34a;
}

.chart-container {
    position: relative;
    height: 300px;
}

.doughnut-wrap {
    height: 220px;
}

.chart-overlay {
    position: absolute;
    inset: 0;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    background: rgba(255, 255, 255, 0.62);
}

.chart-spinner {
    width: 18px;
    height: 18px;
    border-radius: 999px;
    border: 2px solid #F0E8E0;
    border-top-color: #E8553E;
    animation: spin 280ms linear infinite;
}

.split-grid {
    display: grid;
    grid-template-columns: minmax(0, 2fr) minmax(0, 1fr);
    gap: 12px;
}

.mini-table {
    width: 100%;
    border-collapse: collapse;
}

.mini-table thead {
    background: #FFF8F0;
}

.mini-table th {
    text-align: left;
    padding: 10px 12px;
    border-bottom: 1px solid #e2e8f0;
    text-transform: uppercase;
    letter-spacing: 0.06em;
    font-size: 11px;
    color: #9CA3AF;
    font-weight: 600;
}

.mini-table td {
    padding: 10px 12px;
    border-bottom: 1px solid #f1f5f9;
    font-size: 13px;
    color: #2D2D2D;
}

.mini-table tr:last-child td {
    border-bottom: none;
}

.empty-inline {
    color: #9CA3AF;
    text-align: center;
}

.revenue-legend {
    margin: 12px 0 0;
    padding: 0;
    list-style: none;
    display: flex;
    flex-direction: column;
    gap: 8px;
}

.legend-button {
    width: 100%;
    border: 1px solid #e2e8f0;
    border-radius: 8px;
    background: #ffffff;
    min-height: 34px;
    padding: 0 10px;
    display: inline-flex;
    align-items: center;
    gap: 8px;
    font-size: 13px;
    color: #2D2D2D;
    cursor: pointer;
}

.legend-button.hidden {
    opacity: 0.5;
}

.analytics-tab-enter-active,
.analytics-tab-leave-active {
    transition: opacity 200ms ease, transform 200ms ease;
}

.analytics-tab-enter-from,
.analytics-tab-leave-to {
    opacity: 0;
    transform: translateY(8px);
}

.icon-16 {
    width: 16px;
    height: 16px;
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
    .metric-grid.four-up,
    .metric-grid.three-up {
        grid-template-columns: repeat(2, minmax(0, 1fr));
    }

    .split-grid {
        grid-template-columns: 1fr;
    }
}

@media (max-width: 900px) {
    .analytics-header,
    .controls-row {
        flex-direction: column;
        align-items: stretch;
    }

    .metric-grid.four-up,
    .metric-grid.three-up,
    .metric-grid.two-up {
        grid-template-columns: 1fr;
    }
}
</style>
