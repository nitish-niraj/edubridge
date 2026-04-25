<script setup>
import AdminLayout from '@/Layouts/AdminLayout.vue';
import { onBeforeUnmount, onMounted, ref, watch } from 'vue';
import axios from 'axios';
import {
    ChatBubbleLeftRightIcon,
    MagnifyingGlassIcon,
    ReceiptRefundIcon,
    XMarkIcon,
} from '@heroicons/vue/24/outline';

const urlQuery = new URLSearchParams(window.location.search);
const disputes = ref({ data: [] });
const searchTerm = ref(urlQuery.get('search') || '');
const showDrawer = ref(false);
const drawerData = ref(null);
const drawerLoading = ref(false);
const adminNote = ref('');
const partialAmount = ref('');
const showPartial = ref(false);
const actionLoading = ref(false);
const pageError = ref('');
const pageNotice = ref('');
let searchDebounceTimer = null;

const fetchDisputes = async () => {
    pageError.value = '';
    try {
        const { data } = await axios.get('/api/admin/disputes', {
            params: {
                search: searchTerm.value.trim() || undefined,
            },
        });
        disputes.value = data;
    } catch (error) {
        pageError.value = error?.response?.data?.message || 'Unable to load disputes right now.';
    }
};

const openDrawer = async (id) => {
    pageError.value = '';
    pageNotice.value = '';
    drawerLoading.value = true;
    drawerData.value = null;
    showDrawer.value = true;

    try {
        const { data } = await axios.get(`/api/admin/disputes/${id}`);
        drawerData.value = data;
        adminNote.value = '';
        partialAmount.value = '';
        showPartial.value = false;
    } catch (error) {
        closeDrawer();
        pageError.value = error?.response?.data?.message || 'Unable to open this dispute.';
    } finally {
        drawerLoading.value = false;
    }
};

const closeDrawer = () => {
    showDrawer.value = false;
    drawerData.value = null;
    drawerLoading.value = false;
};

const handleEscape = (event) => {
    if (event.key === 'Escape' && showDrawer.value) {
        closeDrawer();
    }
};

onMounted(() => {
    document.body.setAttribute('data-portal', 'admin');
    fetchDisputes();
    window.addEventListener('keydown', handleEscape);
});

onBeforeUnmount(() => {
    clearTimeout(searchDebounceTimer);
    window.removeEventListener('keydown', handleEscape);
});

watch(searchTerm, () => {
    clearTimeout(searchDebounceTimer);
    searchDebounceTimer = window.setTimeout(fetchDisputes, 300);
});

const fullRefund = async () => {
    if (!drawerData.value) return;
    if (!canRunRefundActions()) {
        pageError.value = 'This dispute payment is already finalized and cannot be refunded again.';
        return;
    }
    if (!window.confirm('Process full refund?')) return;

    actionLoading.value = true;
    pageError.value = '';
    pageNotice.value = '';

    try {
        const { data } = await axios.post(`/api/admin/disputes/${drawerData.value.booking.id}/full-refund`, { note: adminNote.value });
        pageNotice.value = data?.message || 'Full refund processed.';
        closeDrawer();
        await fetchDisputes();
    } catch (error) {
        pageError.value = error?.response?.data?.message || 'Unable to process full refund.';
    } finally {
        actionLoading.value = false;
    }
};

const partialRefund = async () => {
    if (!drawerData.value) return;
    if (!canRunRefundActions()) {
        pageError.value = 'This dispute payment is already finalized and cannot be partially refunded.';
        return;
    }

    const amount = Number(partialAmount.value);
    if (!Number.isFinite(amount) || amount <= 0) {
        pageError.value = 'Enter a valid partial refund amount.';
        return;
    }

    actionLoading.value = true;
    pageError.value = '';
    pageNotice.value = '';

    try {
        const { data } = await axios.post(`/api/admin/disputes/${drawerData.value.booking.id}/partial-refund`, {
            amount,
            note: adminNote.value,
        });
        pageNotice.value = data?.message || 'Partial refund processed.';
        closeDrawer();
        await fetchDisputes();
    } catch (error) {
        pageError.value = error?.response?.data?.message || 'Unable to process partial refund.';
    } finally {
        actionLoading.value = false;
    }
};

const release = async () => {
    if (!drawerData.value) return;
    if (!canRunReleaseAction()) {
        pageError.value = 'This dispute cannot release payment in its current state.';
        return;
    }
    if (!window.confirm('Release payment to teacher?')) return;

    actionLoading.value = true;
    pageError.value = '';
    pageNotice.value = '';

    try {
        const { data } = await axios.post(`/api/admin/disputes/${drawerData.value.booking.id}/release`, { note: adminNote.value });
        pageNotice.value = data?.message || 'Payment released to teacher.';
        closeDrawer();
        await fetchDisputes();
    } catch (error) {
        pageError.value = error?.response?.data?.message || 'Unable to release payment.';
    } finally {
        actionLoading.value = false;
    }
};

const close = async () => {
    if (!drawerData.value) return;

    actionLoading.value = true;
    pageError.value = '';
    pageNotice.value = '';

    try {
        const { data } = await axios.post(`/api/admin/disputes/${drawerData.value.booking.id}/close`, { note: adminNote.value });
        pageNotice.value = data?.message || 'Dispute closed.';
        closeDrawer();
        await fetchDisputes();
    } catch (error) {
        pageError.value = error?.response?.data?.message || 'Unable to close dispute.';
    } finally {
        actionLoading.value = false;
    }
};

const daysSince = (date) => (date ? Math.floor((Date.now() - new Date(date)) / 86400000) : '—');

const formatDate = (value) => {
    if (!value) return '—';
    return new Date(value).toLocaleDateString(undefined, { year: 'numeric', month: 'short', day: 'numeric' });
};

const paymentStatusTone = (status) => {
    const map = { held: 'amber', paid: 'blue', refunded: 'emerald', released: 'emerald', failed: 'rose' };
    return map[status] || 'slate';
};

const conversationId = () => {
    return drawerData.value?.booking?.conversation_id || drawerData.value?.booking?.conversation?.id || null;
};

const currentPaymentStatus = () => {
    return String(drawerData.value?.booking?.payment?.status || drawerData.value?.booking?.payment_status || '').toLowerCase();
};

const canRunRefundActions = () => {
    const status = currentPaymentStatus();
    return status !== 'refunded' && status !== 'released';
};

const canRunReleaseAction = () => {
    const status = currentPaymentStatus();
    return status === 'held' || status === 'paid';
};
</script>

<template>
    <AdminLayout>
        <div class="disputes-page">
            <div class="page-header">
                <div>
                    <p class="eyebrow">Content moderation</p>
                    <h1>Disputes</h1>
                </div>
                <div class="summary-chip">
                    <ReceiptRefundIcon class="chip-icon" />
                    {{ disputes.data.length }} open items
                </div>
            </div>

            <label class="search-field" aria-label="Search disputes">
                <MagnifyingGlassIcon class="icon-18 search-icon" />
                <input
                    v-model="searchTerm"
                    type="text"
                    class="text-input"
                    placeholder="Search by booking id, subject, student, or teacher"
                />
            </label>

            <p v-if="pageError" class="error-banner">{{ pageError }}</p>
            <p v-if="pageNotice" class="notice-banner">{{ pageNotice }}</p>

            <div class="card table-card">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Student</th>
                            <th>Teacher</th>
                            <th>Cancellation</th>
                            <th>Payment</th>
                            <th>Amount</th>
                            <th>Session date</th>
                            <th>Reports</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="dispute in disputes.data" :key="dispute.id" class="table-row" @click="openDrawer(dispute.id)">
                            <td class="id-cell">#{{ dispute.id }}</td>
                            <td>{{ dispute.student?.name }}</td>
                            <td>{{ dispute.teacher?.name }}</td>
                            <td>
                                <span class="status-pill" :class="paymentStatusTone(dispute.cancellation_status || dispute.status)">
                                    {{ dispute.cancellation_status || dispute.status || '—' }}
                                </span>
                            </td>
                            <td>
                                <span class="status-pill" :class="paymentStatusTone(dispute.payment_status || dispute.payment?.status)">
                                    {{ dispute.payment_status || dispute.payment?.status || '—' }}
                                </span>
                            </td>
                            <td>₹{{ dispute.price }}</td>
                            <td>{{ formatDate(dispute.start_at) }}</td>
                            <td>
                                <span v-if="dispute.reports_count" class="report-pill">{{ dispute.reports_count }} linked</span>
                                <span v-else class="muted">0</span>
                            </td>
                        </tr>
                        <tr v-if="!disputes.data.length">
                            <td colspan="8" class="empty-state">No disputes found.</td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <div v-if="showDrawer" class="drawer-overlay" @click.self="closeDrawer">
                <aside class="drawer-panel" role="dialog" aria-modal="true">
                    <header class="drawer-header">
                        <div>
                            <p class="eyebrow">Dispute drawer</p>
                            <h2>Dispute #{{ drawerData?.booking?.id }}</h2>
                        </div>
                        <button class="icon-button" type="button" @click="closeDrawer">
                            <XMarkIcon class="icon-18" />
                        </button>
                    </header>

                    <div v-if="drawerLoading" class="drawer-body">
                        <div class="drawer-loading">Loading dispute details...</div>
                    </div>

                    <div v-else-if="drawerData" class="drawer-body">
                        <section class="section">
                            <div class="section-title">Booking summary</div>
                            <div class="meta-grid">
                                <div class="meta-cell">
                                    <span>Student</span>
                                    <strong>{{ drawerData.booking.student?.name }}</strong>
                                </div>
                                <div class="meta-cell">
                                    <span>Teacher</span>
                                    <strong>{{ drawerData.booking.teacher?.name }}</strong>
                                </div>
                                <div class="meta-cell">
                                    <span>Amount</span>
                                    <strong>₹{{ drawerData.booking.price }}</strong>
                                </div>
                                <div class="meta-cell">
                                    <span>Payment status</span>
                                    <strong>{{ drawerData.booking.payment?.status || '—' }}</strong>
                                </div>
                            </div>
                        </section>

                        <section class="section">
                            <div class="section-title">Decision guide</div>
                            <div class="decision-grid">
                                <article class="decision-card">
                                    <strong>Use full refund when</strong>
                                    <p>Session could not be delivered or major quality issues are clearly validated.</p>
                                </article>
                                <article class="decision-card">
                                    <strong>Use partial refund when</strong>
                                    <p>Session was delivered only in part, or both sides share responsibility.</p>
                                </article>
                                <article class="decision-card">
                                    <strong>Release to teacher when</strong>
                                    <p>Evidence confirms session completion and dispute claim is not substantiated.</p>
                                </article>
                            </div>
                        </section>

                        <section class="section">
                            <div class="section-title">Case context</div>
                            <div class="context-list">
                                <div>
                                    <span>Session date</span>
                                    <strong>{{ formatDate(drawerData.booking.start_at) }}</strong>
                                </div>
                                <div>
                                    <span>Days since session</span>
                                    <strong>{{ daysSince(drawerData.booking.start_at) }}</strong>
                                </div>
                                <div>
                                    <span>Current dispute status</span>
                                    <strong>{{ drawerData.booking.status || '—' }}</strong>
                                </div>
                            </div>
                            <p v-if="!canRunRefundActions() && !canRunReleaseAction()" class="muted" style="margin-top: 8px;">
                                This dispute payment is already finalized. Refund or release actions are disabled.
                            </p>
                        </section>

                        <section class="section">
                            <div class="section-title">Timeline</div>
                            <div class="timeline">
                                <div v-for="event in drawerData.events" :key="`${event.id}-${event.event}`" class="timeline-row">
                                    <span class="timeline-dot" />
                                    <div>
                                        <div class="timeline-event">{{ event.event }}</div>
                                        <div class="timeline-date">{{ formatDate(event.created_at) }}</div>
                                    </div>
                                </div>
                            </div>
                        </section>

                        <section class="section">
                            <div class="section-title">Linked reports</div>
                            <div v-if="drawerData.reports?.length" class="linked-list">
                                <div v-for="report in drawerData.reports" :key="report.id" class="linked-row">
                                    <span>#{{ report.id }} - {{ report.type }}</span>
                                    <span class="muted">{{ report.reason }}</span>
                                </div>
                            </div>
                            <p v-else class="muted">No linked reports found.</p>
                        </section>

                        <section class="section">
                            <div class="section-title">Conversation</div>
                            <div class="conversation-link disabled" role="note" aria-disabled="true">
                                <ChatBubbleLeftRightIcon class="icon-18" />
                                Conversation access is limited to participants
                            </div>
                            <p v-if="conversationId()" class="muted">
                                Conversation #{{ conversationId() }} can be reviewed by student and teacher participants.
                            </p>
                            <p v-else class="muted">No conversation is linked to this booking.</p>
                        </section>

                        <section class="section">
                            <div class="section-title">Admin note</div>
                            <textarea v-model="adminNote" rows="3" class="textarea" placeholder="Add a note for this dispute" />
                            <p class="muted" style="margin: 8px 0 0;">
                                Include the reason for decision so finance and moderation teams can audit this case later.
                            </p>
                        </section>

                        <section v-if="showPartial" class="section">
                            <div class="section-title">Partial refund amount</div>
                            <input v-model="partialAmount" type="number" class="text-input" placeholder="Enter amount" />
                        </section>
                    </div>

                    <footer class="drawer-footer">
                        <button class="secondary-button" type="button" :disabled="actionLoading || !canRunRefundActions()" @click="fullRefund">
                            Full refund
                        </button>
                        <button class="secondary-button" type="button" :disabled="actionLoading || !canRunRefundActions()" @click="showPartial = !showPartial">
                            Partial refund
                        </button>
                        <button class="secondary-button" type="button" :disabled="actionLoading || !canRunReleaseAction()" @click="release">
                            Release to teacher
                        </button>
                        <button class="primary-button" type="button" :disabled="actionLoading" @click="close">
                            Close dispute
                        </button>
                    </footer>
                    <div v-if="showPartial" class="drawer-footer footer-inline">
                        <button class="primary-button" type="button" :disabled="actionLoading" @click="partialRefund">
                            Submit partial refund
                        </button>
                    </div>
                </aside>
            </div>
        </div>
    </AdminLayout>
</template>

<style scoped>
.disputes-page {
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
}

.search-field {
    display: flex;
    align-items: center;
    gap: 8px;
    max-width: 440px;
}

.search-icon {
    color: #9CA3AF;
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

.drawer-loading {
    border: 1px dashed #dbe3ef;
    border-radius: 10px;
    padding: 12px;
    color: #475569;
    font-size: 14px;
}

.chip-icon,
.icon-18 {
    width: 18px;
    height: 18px;
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
    border-top: 1px solid #eef2f7;
    cursor: pointer;
}

.data-table td {
    padding: 12px 16px;
    color: #2D2D2D;
    vertical-align: middle;
}

.id-cell {
    font-weight: 800;
    color: #E8553E;
}

.status-pill,
.report-pill {
    display: inline-flex;
    align-items: center;
    padding: 5px 10px;
    border-radius: 999px;
    font-size: 12px;
    font-weight: 800;
    text-transform: capitalize;
}

.status-pill.amber {
    background: #fffbeb;
    color: #b45309;
}

.status-pill.blue {
    background: #eff6ff;
    color: #D44433;
}

.status-pill.emerald {
    background: #ecfdf5;
    color: #047857;
}

.status-pill.rose {
    background: #fff1f2;
    color: #be123c;
}

.status-pill.slate {
    background: #f1f5f9;
    color: #2D2D2D;
}

.report-pill {
    background: #fff1f2;
    color: #be123c;
}

.muted {
    color: #9CA3AF;
    font-size: 13px;
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

.drawer-header {
    padding: 18px 20px;
    display: flex;
    align-items: flex-start;
    justify-content: space-between;
    border-bottom: 1px solid #e5ebf3;
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

.drawer-body {
    flex: 1;
    overflow: auto;
    display: flex;
    flex-direction: column;
    gap: 16px;
    padding: 18px 20px 20px;
}

.section {
    border: 1px solid #e5ebf3;
    border-radius: 18px;
    padding: 16px;
}

.section-title {
    margin-bottom: 10px;
    font-size: 12px;
    font-weight: 800;
    letter-spacing: 0.12em;
    text-transform: uppercase;
    color: #9CA3AF;
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

.decision-grid {
    display: grid;
    gap: 10px;
}

.decision-card {
    border: 1px solid #e5ebf3;
    border-radius: 14px;
    padding: 12px;
    background: #f8fbff;
}

.decision-card strong {
    display: block;
    font-size: 13px;
    color: #2D2D2D;
}

.decision-card p {
    margin: 6px 0 0;
    font-size: 13px;
    line-height: 1.5;
    color: #9CA3AF;
}

.context-list {
    display: grid;
    grid-template-columns: repeat(3, minmax(0, 1fr));
    gap: 10px;
}

.context-list > div {
    border: 1px solid #e5ebf3;
    border-radius: 12px;
    padding: 10px;
    background: #f8fbff;
}

.context-list span {
    display: block;
    font-size: 11px;
    font-weight: 800;
    letter-spacing: 0.08em;
    text-transform: uppercase;
    color: #9CA3AF;
}

.context-list strong {
    display: block;
    margin-top: 5px;
    font-size: 13px;
    color: #2D2D2D;
}

.timeline {
    display: flex;
    flex-direction: column;
    gap: 12px;
}

.timeline-row {
    display: flex;
    gap: 12px;
}

.timeline-dot {
    width: 10px;
    height: 10px;
    border-radius: 999px;
    background: #E8553E;
    margin-top: 6px;
    flex: 0 0 auto;
}

.timeline-event {
    font-size: 14px;
    font-weight: 800;
    color: #2D2D2D;
}

.timeline-date {
    font-size: 13px;
    color: #9CA3AF;
}

.linked-list {
    display: flex;
    flex-direction: column;
    gap: 10px;
}

.linked-row {
    padding: 12px 14px;
    border-radius: 14px;
    background: #f8fbff;
}

.linked-row span {
    display: block;
}

.conversation-link {
    display: inline-flex;
    align-items: center;
    gap: 8px;
    color: #E8553E;
    font-size: 13px;
    font-weight: 800;
    text-decoration: none;
}

.conversation-link.disabled {
    pointer-events: none;
    opacity: 0.45;
}

.textarea,
.text-input {
    width: 100%;
    border: 1px solid #dbe3ef;
    border-radius: 14px;
    padding: 12px 14px;
    font: inherit;
    resize: vertical;
    outline: none;
}

.text-input {
    resize: none;
}

.drawer-footer {
    padding: 18px 20px;
    border-top: 1px solid #e5ebf3;
    display: grid;
    grid-template-columns: repeat(4, minmax(0, 1fr));
    gap: 10px;
}

.footer-inline {
    grid-template-columns: 1fr;
    border-top: none;
    padding-top: 0;
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

@media (max-width: 900px) {
    .page-header {
        flex-direction: column;
    }

    .context-list,
    .meta-grid,
    .drawer-footer {
        grid-template-columns: 1fr;
    }

    .drawer-panel {
        width: 100%;
    }
}
</style>
