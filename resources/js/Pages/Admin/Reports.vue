<script setup>
import AdminLayout from '@/Layouts/AdminLayout.vue';
import { computed, onBeforeUnmount, onMounted, ref, watch } from 'vue';
import axios from 'axios';
import {
    MagnifyingGlassIcon,
    FlagIcon,
    XMarkIcon,
} from '@heroicons/vue/24/outline';

const urlQuery = new URLSearchParams(window.location.search);
const statusFilter = ref(urlQuery.get('status') || 'all');
const searchTerm = ref(urlQuery.get('search') || '');
const reports = ref({ data: [] });
const loading = ref(false);
const drawerOpen = ref(false);
const drawerReport = ref(null);
const adminNote = ref('');
const pageError = ref('');
const pageNotice = ref('');
const actionLoading = ref(false);
const drawerLoading = ref(false);
let searchDebounceTimer = null;

const tabs = ['all', 'pending', 'reviewed', 'dismissed', 'action_taken'];

const fetchReports = async () => {
    loading.value = true;
    pageError.value = '';
    try {
        const { data } = await axios.get('/api/admin/reports', {
            params: {
                status: statusFilter.value === 'all' ? undefined : statusFilter.value,
                search: searchTerm.value.trim() || undefined,
            },
        });
        reports.value = data;
    } catch (error) {
        pageError.value = error?.response?.data?.message || 'Unable to load reports right now.';
    } finally {
        loading.value = false;
    }
};

const openDrawer = async (id) => {
    pageError.value = '';
    pageNotice.value = '';
    drawerLoading.value = true;
    drawerReport.value = null;
    drawerOpen.value = true;

    try {
        const { data } = await axios.get(`/api/admin/reports/${id}`);
        drawerReport.value = data.report || data;
        adminNote.value = '';
    } catch (error) {
        closeDrawer();
        pageError.value = error?.response?.data?.message || 'Unable to open this report.';
    } finally {
        drawerLoading.value = false;
    }
};

const closeDrawer = () => {
    drawerOpen.value = false;
    drawerReport.value = null;
    adminNote.value = '';
    drawerLoading.value = false;
};

const handleEscape = (event) => {
    if (event.key === 'Escape' && drawerOpen.value) {
        closeDrawer();
    }
};

onMounted(() => {
    document.body.setAttribute('data-portal', 'admin');
    fetchReports();
    window.addEventListener('keydown', handleEscape);
});

onBeforeUnmount(() => {
    clearTimeout(searchDebounceTimer);
    window.removeEventListener('keydown', handleEscape);
});

watch(statusFilter, fetchReports);

watch(searchTerm, () => {
    clearTimeout(searchDebounceTimer);
    searchDebounceTimer = window.setTimeout(fetchReports, 300);
});

const badgeTone = (status) => {
    const map = {
        pending: 'amber',
        reviewed: 'blue',
        dismissed: 'slate',
        action_taken: 'emerald',
    };

    return map[status] || 'slate';
};

const typeTone = (type) => {
    const map = {
        message: 'violet',
        review: 'amber',
        profile: 'blue',
        other: 'slate',
    };

    return map[type] || 'slate';
};

const formatDate = (value) => {
    if (!value) return '—';
    return new Date(value).toLocaleDateString(undefined, { year: 'numeric', month: 'short', day: 'numeric' });
};

const contentSummary = computed(() => {
    const report = drawerReport.value;
    if (!report) return '';
    if (report.message?.body) return report.message.body;
    if (report.review?.comment) return report.review.comment;
    if (report.reported_user) return `Reported profile: ${report.reported_user.name}`;
    return 'No linked content available.';
});

const contentTypeGuide = computed(() => {
    const type = drawerReport.value?.type;

    if (type === 'message') {
        return 'Message reports usually involve harassment, spam, or abusive communication in chat.';
    }

    if (type === 'review') {
        return 'Review reports usually involve misleading claims, abusive language, or irrelevant review content.';
    }

    if (type === 'profile') {
        return 'Profile reports usually involve inappropriate profile details, false credentials, or identity concerns.';
    }

    return 'Validate evidence quality, policy fit, and user-impact severity before taking action.';
});

const canTakeAction = computed(() => drawerReport.value?.status === 'pending');

const warnUser = async () => {
    if (!drawerReport.value) return;
    if (!canTakeAction.value) {
        pageError.value = 'This report is already resolved.';
        return;
    }

    actionLoading.value = true;
    pageError.value = '';
    pageNotice.value = '';

    try {
        const { data } = await axios.post(`/api/admin/reports/${drawerReport.value.id}/warn`, { note: adminNote.value });
        pageNotice.value = data?.message || 'Warning sent.';
        closeDrawer();
        await fetchReports();
    } catch (error) {
        pageError.value = error?.response?.data?.message || 'Unable to warn user for this report.';
    } finally {
        actionLoading.value = false;
    }
};

const removeContent = async () => {
    if (!drawerReport.value) return;
    if (!canTakeAction.value) {
        pageError.value = 'This report is already resolved.';
        return;
    }
    if (!window.confirm('Remove the reported content?')) return;

    actionLoading.value = true;
    pageError.value = '';
    pageNotice.value = '';

    try {
        const { data } = await axios.post(`/api/admin/reports/${drawerReport.value.id}/remove-content`);
        pageNotice.value = data?.message || 'Content removed.';
        closeDrawer();
        await fetchReports();
    } catch (error) {
        pageError.value = error?.response?.data?.message || 'Unable to remove content for this report.';
    } finally {
        actionLoading.value = false;
    }
};

const suspendReportedUser = async () => {
    if (!drawerReport.value) return;
    if (!canTakeAction.value) {
        pageError.value = 'This report is already resolved.';
        return;
    }
    if (!window.confirm('Suspend this user?')) return;

    actionLoading.value = true;
    pageError.value = '';
    pageNotice.value = '';

    try {
        const { data } = await axios.post(`/api/admin/reports/${drawerReport.value.id}/suspend-user`, { note: adminNote.value });
        pageNotice.value = data?.message || 'User suspended.';
        closeDrawer();
        await fetchReports();
    } catch (error) {
        pageError.value = error?.response?.data?.message || 'Unable to suspend user for this report.';
    } finally {
        actionLoading.value = false;
    }
};

const dismiss = async () => {
    if (!drawerReport.value) return;
    if (!canTakeAction.value) {
        pageError.value = 'This report is already resolved.';
        return;
    }

    actionLoading.value = true;
    pageError.value = '';
    pageNotice.value = '';

    try {
        const { data } = await axios.post(`/api/admin/reports/${drawerReport.value.id}/dismiss`, { note: adminNote.value });
        pageNotice.value = data?.message || 'Report dismissed.';
        closeDrawer();
        await fetchReports();
    } catch (error) {
        pageError.value = error?.response?.data?.message || 'Unable to dismiss this report.';
    } finally {
        actionLoading.value = false;
    }
};
</script>

<template>
    <AdminLayout>
        <div class="reports-page">
            <div class="page-header">
                <div>
                    <p class="eyebrow">Moderation</p>
                    <h1>Reports</h1>
                </div>
                <div class="summary-chip">
                    <FlagIcon class="chip-icon" />
                    {{ reports.data.length }} records
                </div>
            </div>

            <p v-if="pageError" class="error-banner">{{ pageError }}</p>
            <p v-if="pageNotice" class="notice-banner">{{ pageNotice }}</p>

            <div class="tabs">
                <button
                    v-for="tab in tabs"
                    :key="tab"
                    class="tab-button"
                    :class="{ active: statusFilter === tab }"
                    @click="statusFilter = tab"
                >
                    {{ tab === 'all' ? 'All' : tab.replace('_', ' ') }}
                </button>
            </div>

            <label class="search-field" aria-label="Search reports">
                <MagnifyingGlassIcon class="icon-18 search-icon" />
                <input
                    v-model="searchTerm"
                    type="text"
                    class="text-input"
                    placeholder="Search by reason, type, reporter, or reported user"
                />
            </label>

            <div class="card table-card">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Type</th>
                            <th>Reporter</th>
                            <th>Reported user</th>
                            <th>Reason</th>
                            <th>Date</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="report in reports.data" :key="report.id" class="table-row" @click="openDrawer(report.id)">
                            <td>
                                <span class="type-pill" :class="typeTone(report.type)">{{ report.type }}</span>
                            </td>
                            <td>
                                <div class="cell-stack">
                                    <span class="cell-title">{{ report.reporter?.name }}</span>
                                    <span class="muted">{{ report.reporter?.email }}</span>
                                </div>
                            </td>
                            <td>
                                <div class="cell-stack">
                                    <span class="cell-title">{{ report.reported_user?.name || '—' }}</span>
                                    <span class="muted">{{ report.reported_user?.email || '—' }}</span>
                                </div>
                            </td>
                            <td class="reason-cell">{{ report.reason }}</td>
                            <td>{{ formatDate(report.created_at) }}</td>
                            <td>
                                <span class="status-pill" :class="badgeTone(report.status)">
                                    {{ report.status }}
                                </span>
                            </td>
                        </tr>
                        <tr v-if="!reports.data.length && !loading">
                            <td colspan="6" class="empty-state">No reports match this filter.</td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <div v-if="drawerOpen" class="drawer-overlay" @click.self="closeDrawer">
                <aside class="drawer-panel" role="dialog" aria-modal="true">
                    <header class="drawer-header">
                        <div>
                            <p class="eyebrow">Report drawer</p>
                            <h2>Report #{{ drawerReport?.id }}</h2>
                        </div>
                        <button class="icon-button" type="button" @click="closeDrawer" aria-label="Close">
                            <XMarkIcon class="icon-18" />
                        </button>
                    </header>

                    <div v-if="drawerLoading" class="drawer-body">
                        <p class="muted">Loading report details...</p>
                    </div>

                    <div v-else-if="drawerReport" class="drawer-body">
                        <section class="profile-card">
                            <div class="meta-grid">
                                <div class="meta-cell">
                                    <span>Type</span>
                                    <strong class="text-capitalize">{{ drawerReport.type }}</strong>
                                </div>
                                <div class="meta-cell">
                                    <span>Status</span>
                                    <strong class="text-capitalize">{{ drawerReport.status }}</strong>
                                </div>
                                <div class="meta-cell">
                                    <span>Reporter</span>
                                    <strong>{{ drawerReport.reporter?.name }}</strong>
                                </div>
                                <div class="meta-cell">
                                    <span>Reported user</span>
                                    <strong>{{ drawerReport.reported_user?.name || '—' }}</strong>
                                </div>
                            </div>
                        </section>

                        <section class="section">
                            <div class="section-title">Review guide</div>
                            <p class="body-copy">{{ contentTypeGuide }}</p>
                            <ul class="guide-list">
                                <li>Confirm the selected reason matches the linked evidence.</li>
                                <li>Escalate severe or repeated abuse patterns quickly.</li>
                                <li>Document rationale in admin notes for audit clarity.</li>
                            </ul>
                        </section>

                        <section class="section">
                            <div class="section-title">Full content</div>
                            <p class="body-copy">{{ contentSummary }}</p>
                        </section>

                        <section class="section">
                            <div class="section-title">Details</div>
                            <div class="detail-list">
                                <div><span>Reason</span><strong>{{ drawerReport.reason }}</strong></div>
                                <div><span>Reporter email</span><strong>{{ drawerReport.reporter?.email || '—' }}</strong></div>
                                <div><span>Reported email</span><strong>{{ drawerReport.reported_user?.email || '—' }}</strong></div>
                            </div>
                        </section>

                        <section class="section">
                            <div class="section-title">Admin note</div>
                            <textarea v-model="adminNote" rows="4" class="textarea" placeholder="Add a resolution note" />
                        </section>

                        <section v-if="!canTakeAction" class="section">
                            <div class="section-title">Resolution status</div>
                            <p class="body-copy">This report is already resolved. Further moderation actions are disabled.</p>
                        </section>
                    </div>

                    <footer class="drawer-footer">
                        <button class="secondary-button" type="button" :disabled="actionLoading || !canTakeAction" @click="dismiss">
                            Dismiss
                        </button>
                        <button class="secondary-button" type="button" :disabled="actionLoading || !canTakeAction" @click="warnUser">
                            Warn user
                        </button>
                        <button class="secondary-button" type="button" :disabled="actionLoading || !canTakeAction" @click="removeContent">
                            Remove content
                        </button>
                        <button class="primary-button" type="button" :disabled="actionLoading || !canTakeAction" @click="suspendReportedUser">
                            Suspend user
                        </button>
                    </footer>
                </aside>
            </div>
        </div>
    </AdminLayout>
</template>

<style scoped>
.reports-page {
    display: flex;
    flex-direction: column;
    gap: 18px;
}

.page-header {
    display: flex;
    align-items: start;
    justify-content: space-between;
    gap: 16px;
}

.eyebrow {
    margin: 0 0 6px;
    font-size: 11px;
    font-weight: 800;
    letter-spacing: 0.14em;
    text-transform: uppercase;
    color: #9CA3AF;
}

h1, h2 {
    margin: 0;
    color: #2D2D2D;
}

h1 {
    font-size: 28px;
    font-weight: 800;
}

h2 {
    font-size: 18px;
    font-weight: 800;
}

.summary-chip {
    display: inline-flex;
    align-items: center;
    gap: 8px;
    padding: 10px 14px;
    border-radius: 999px;
    background: #eff6ff;
    color: #D44433;
    font-size: 13px;
    font-weight: 700;
    white-space: nowrap;
}

.error-banner,
.notice-banner {
    margin: 0;
    padding: 10px 12px;
    border-radius: 10px;
    font-size: 14px;
    font-weight: 700;
}

.error-banner {
    border: 1px solid #fecaca;
    background: #fff1f2;
    color: #b91c1c;
}

.notice-banner {
    border: 1px solid #86efac;
    background: #ecfdf3;
    color: #166534;
}

.chip-icon,
.icon-18 {
    width: 18px;
    height: 18px;
}

.tabs {
    display: flex;
    flex-wrap: wrap;
    gap: 8px;
}

.search-field {
    display: flex;
    align-items: center;
    gap: 8px;
    max-width: 420px;
}

.search-icon {
    color: #9CA3AF;
}

.tab-button {
    min-height: 40px;
    padding: 0 14px;
    border-radius: 999px;
    border: 1px solid #dbe3ef;
    background: #fff;
    color: #2D2D2D;
    font-size: 13px;
    font-weight: 800;
    cursor: pointer;
}

.tab-button.active {
    background: #D44433;
    border-color: #D44433;
    color: #fff;
}

.card {
    background: #fff;
    border: 1px solid #e5ebf3;
    border-radius: 18px;
    box-shadow: 0 14px 36px rgba(15, 23, 42, 0.06);
}

.table-card {
    overflow: hidden;
}

.data-table {
    width: 100%;
    border-collapse: collapse;
}

.data-table thead {
    background: #f8fbff;
}

.data-table th {
    padding: 14px 16px;
    text-align: left;
    font-size: 12px;
    text-transform: uppercase;
    letter-spacing: 0.08em;
    color: #9CA3AF;
}

.table-row {
    cursor: pointer;
    border-top: 1px solid #eef2f7;
}

.table-row:hover {
    background: #f8fbff;
}

.data-table td {
    padding: 12px 16px;
    vertical-align: middle;
    color: #2D2D2D;
}

.cell-stack {
    display: flex;
    flex-direction: column;
    gap: 4px;
}

.cell-title {
    font-weight: 800;
}

.muted {
    color: #9CA3AF;
    font-size: 13px;
}

.reason-cell {
    max-width: 220px;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
    color: #2D2D2D;
}

.status-pill,
.type-pill {
    display: inline-flex;
    align-items: center;
    padding: 5px 10px;
    border-radius: 999px;
    font-size: 12px;
    font-weight: 800;
    text-transform: capitalize;
}

.status-pill.amber,
.type-pill.amber {
    background: #fffbeb;
    color: #b45309;
}

.status-pill.blue,
.type-pill.blue {
    background: #eff6ff;
    color: #D44433;
}

.status-pill.slate,
.type-pill.slate {
    background: #f1f5f9;
    color: #2D2D2D;
}

.status-pill.emerald {
    background: #ecfdf5;
    color: #047857;
}

.type-pill.violet {
    background: #f5f3ff;
    color: #7c3aed;
}

.empty-state {
    padding: 34px 16px;
    text-align: center;
    color: #9CA3AF;
}

.drawer-overlay {
    position: fixed;
    inset: 0;
    z-index: 60;
    background: rgba(15, 23, 42, 0.36);
}

.drawer-panel {
    position: absolute;
    right: 0;
    top: 0;
    width: min(480px, 100vw);
    height: 100vh;
    background: #fff;
    display: flex;
    flex-direction: column;
    box-shadow: -24px 0 60px rgba(15, 23, 42, 0.18);
}

.drawer-header,
.drawer-body {
    padding: 18px 20px;
}

.drawer-header {
    display: flex;
    align-items: flex-start;
    justify-content: space-between;
    border-bottom: 1px solid #e5ebf3;
}

.drawer-body {
    flex: 1;
    overflow: auto;
    display: flex;
    flex-direction: column;
    gap: 18px;
}

.icon-button {
    width: 36px;
    height: 36px;
    border: 1px solid #dbe3ef;
    border-radius: 12px;
    background: #fff;
    color: #2D2D2D;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    cursor: pointer;
}

.profile-card,
.section {
    border: 1px solid #e5ebf3;
    border-radius: 18px;
    padding: 16px;
}

.meta-grid {
    display: grid;
    grid-template-columns: repeat(2, minmax(0, 1fr));
    gap: 12px;
}

.meta-cell {
    background: #f8fbff;
    border-radius: 14px;
    padding: 12px;
}

.meta-cell span {
    display: block;
    font-size: 11px;
    font-weight: 800;
    letter-spacing: 0.08em;
    text-transform: uppercase;
    color: #9CA3AF;
}

.meta-cell strong {
    display: block;
    margin-top: 4px;
    font-size: 14px;
    color: #2D2D2D;
}

.section-title {
    margin-bottom: 10px;
    font-size: 12px;
    font-weight: 800;
    letter-spacing: 0.12em;
    text-transform: uppercase;
    color: #9CA3AF;
}

.body-copy {
    margin: 0;
    font-size: 14px;
    line-height: 1.7;
    color: #2D2D2D;
    white-space: pre-wrap;
}

.guide-list {
    margin: 10px 0 0;
    padding-left: 18px;
    color: #9CA3AF;
    font-size: 13px;
    line-height: 1.6;
}

.detail-list {
    display: grid;
    gap: 12px;
}

.detail-list span {
    display: block;
    font-size: 11px;
    font-weight: 800;
    letter-spacing: 0.08em;
    text-transform: uppercase;
    color: #9CA3AF;
}

.detail-list strong {
    display: block;
    margin-top: 4px;
    color: #2D2D2D;
}

.textarea {
    width: 100%;
    border: 1px solid #dbe3ef;
    border-radius: 14px;
    padding: 12px 14px;
    font: inherit;
    resize: vertical;
    outline: none;
}

.drawer-footer {
    padding: 18px 20px;
    border-top: 1px solid #e5ebf3;
    display: grid;
    grid-template-columns: repeat(2, minmax(0, 1fr));
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
    background: #E8553E;
    color: #fff;
}

.secondary-button {
    background: #fff;
    color: #2D2D2D;
    border-color: #dbe3ef;
}

.text-capitalize {
    text-transform: capitalize;
}

@media (max-width: 900px) {
    .page-header {
        flex-direction: column;
    }

    .meta-grid,
    .drawer-footer {
        grid-template-columns: 1fr;
    }

    .drawer-panel {
        width: 100%;
    }
}
</style>
