<script setup>
import axios from 'axios';
import { Head, router } from '@inertiajs/vue3';
import { computed, ref, watch } from 'vue';
import { MagnifyingGlassIcon } from '@heroicons/vue/24/outline';
import AdminDrawer from '@/Components/Admin/AdminDrawer.vue';
import AdminLayout from '@/Layouts/AdminLayout.vue';

const props = defineProps({
    teachers: {
        type: Array,
        default: () => [],
    },
    status: {
        type: String,
        default: 'all',
    },
    search: {
        type: String,
        default: '',
    },
});

const statusFilter = ref(props.status || 'all');
const searchTerm = ref(props.search || '');
const loading = ref(false);
const actionLoading = ref(false);
const pageError = ref('');
const pageNotice = ref('');
let searchDebounceTimer = null;

const drawerOpen = ref(false);
const selectedTeacher = ref(null);
const reviewNote = ref('');

watch(
    () => props.status,
    (value) => {
        if (value && value !== statusFilter.value) {
            statusFilter.value = value;
        }
    }
);

watch(
    () => props.search,
    (value) => {
        if ((value || '') !== searchTerm.value) {
            searchTerm.value = value || '';
        }
    }
);

const applyFilters = () => {
    loading.value = true;
    pageError.value = '';

    router.get(route('admin.verifications'), {
        status: statusFilter.value,
        search: searchTerm.value.trim() || undefined,
    }, {
        preserveState: true,
        preserveScroll: true,
        replace: true,
        only: ['teachers', 'status', 'search'],
        onFinish: () => {
            loading.value = false;
        },
    });
};

watch(statusFilter, (value) => {
    if (value !== props.status) {
        applyFilters();
    }
});

watch(searchTerm, (value) => {
    if ((value || '') === (props.search || '')) {
        return;
    }

    clearTimeout(searchDebounceTimer);
    searchDebounceTimer = window.setTimeout(() => {
        applyFilters();
    }, 300);
});

const verificationRows = computed(() => props.teachers || []);
const pendingCount = computed(() => verificationRows.value.filter((row) => row.verification_status === 'pending').length);

const openDrawer = (teacher) => {
    selectedTeacher.value = teacher;
    reviewNote.value = '';
    drawerOpen.value = true;
};

const closeDrawer = () => {
    drawerOpen.value = false;
    selectedTeacher.value = null;
    reviewNote.value = '';
};

const refreshList = () => {
    loading.value = true;
    pageError.value = '';

    router.reload({
        only: ['teachers', 'status'],
        onFinish: () => {
            loading.value = false;
        },
    });
};

const approveTeacher = async () => {
    if (!selectedTeacher.value) {
        return;
    }

    actionLoading.value = true;
    pageError.value = '';
    pageNotice.value = '';

    try {
        const { data } = await axios.post(route('admin.verifications.approve', selectedTeacher.value.id));
        pageNotice.value = data?.message || 'Teacher approved successfully.';
        refreshList();
        closeDrawer();
    } catch (error) {
        pageError.value = error?.response?.data?.message || 'Failed to approve teacher profile.';
    } finally {
        actionLoading.value = false;
    }
};

const rejectTeacher = async () => {
    if (!selectedTeacher.value) {
        return;
    }

    const reason = reviewNote.value.trim() || 'Your teacher profile was not approved after review. Please update your documents and re-apply.';

    actionLoading.value = true;
    pageError.value = '';
    pageNotice.value = '';

    try {
        const { data } = await axios.post(route('admin.verifications.reject', selectedTeacher.value.id), {
            reason,
        });
        pageNotice.value = data?.message || 'Teacher application rejected.';
        refreshList();
        closeDrawer();
    } catch (error) {
        pageError.value = error?.response?.data?.message || 'Failed to reject teacher profile.';
    } finally {
        actionLoading.value = false;
    }
};

const formatDate = (value) => {
    if (!value) {
        return '-';
    }

    return new Date(value).toLocaleDateString(undefined, {
        year: 'numeric',
        month: 'short',
        day: 'numeric',
    });
};

const formatCurrency = (amount) => {
    if (amount === null || amount === undefined || Number.isNaN(Number(amount))) {
        return 'Free';
    }

    return `INR ${Number(amount).toLocaleString()}`;
};

const formatBytes = (value) => {
    if (!value || value <= 0) {
        return '-';
    }

    if (value < 1024) {
        return `${value} B`;
    }

    if (value < 1024 * 1024) {
        return `${(value / 1024).toFixed(1)} KB`;
    }

    return `${(value / (1024 * 1024)).toFixed(1)} MB`;
};

const viewDocument = (document) => {
    if (document?.signed_url) {
        window.open(document.signed_url, '_blank', 'noopener,noreferrer');
    }
};
</script>

<template>
    <Head title="Admin Verifications" />

    <AdminLayout page-title="Verifications" :breadcrumb="['Admin', 'Verifications']">
        <div class="verifications-page">
            <section class="verifications-header">
                <div>
                    <p class="eyebrow">Verification queue</p>
                    <h2>Teacher profile checks</h2>
                    <p class="subcopy">{{ pendingCount }} profile(s) currently pending admin decision.</p>
                </div>

                <div class="header-actions">
                    <label class="search-field" aria-label="Search teachers">
                        <MagnifyingGlassIcon class="icon-16 search-icon" />
                        <input
                            v-model="searchTerm"
                            type="text"
                            class="admin-field"
                            placeholder="Search by name, email or phone"
                        />
                    </label>

                    <select v-model="statusFilter" class="admin-select">
                        <option value="all">All</option>
                        <option value="pending">Pending</option>
                        <option value="approved">Approved</option>
                        <option value="rejected">Rejected</option>
                    </select>
                    <button type="button" class="admin-btn admin-btn-secondary" @click="refreshList" :disabled="loading">
                        {{ loading ? 'Refreshing...' : 'Refresh list' }}
                    </button>
                </div>
            </section>

            <p v-if="pageError" class="error-banner">{{ pageError }}</p>
            <p v-if="pageNotice" class="notice-banner">{{ pageNotice }}</p>

            <section class="table-card">
                <table class="verification-table">
                    <thead>
                        <tr>
                            <th>Teacher</th>
                            <th>Subjects</th>
                            <th>Experience</th>
                            <th>Rate</th>
                            <th>Documents</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr
                            v-for="teacher in verificationRows"
                            :key="teacher.id"
                            class="table-row"
                            @click="openDrawer(teacher)"
                        >
                            <td>
                                <div class="teacher-cell">
                                    <strong>{{ teacher.name || 'Unknown teacher' }}</strong>
                                    <span>{{ teacher.email || '-' }}</span>
                                </div>
                            </td>
                            <td>{{ Array.isArray(teacher.subjects) ? teacher.subjects.join(', ') : '-' }}</td>
                            <td>{{ teacher.experience_years || 0 }} years</td>
                            <td>{{ teacher.is_free ? 'Free' : formatCurrency(teacher.hourly_rate) }}</td>
                            <td>{{ teacher.documents_count || 0 }}</td>
                            <td>
                                <span class="status-pill" :class="`status-${teacher.verification_status}`">
                                    {{ teacher.verification_status || 'pending' }}
                                </span>
                            </td>
                        </tr>

                        <tr v-if="!verificationRows.length">
                            <td colspan="6" class="empty-state">No teacher profiles found for this filter.</td>
                        </tr>
                    </tbody>
                </table>
            </section>
        </div>

        <AdminDrawer
            :open="drawerOpen"
            :loading="actionLoading"
            :title="selectedTeacher?.name || 'Review teacher profile'"
            :subtitle="selectedTeacher?.email || ''"
            @close="closeDrawer"
        >
            <template v-if="selectedTeacher">
                <section class="drawer-section">
                    <h4>Profile summary</h4>
                    <div class="meta-grid">
                        <article>
                            <span>Phone</span>
                            <strong>{{ selectedTeacher.phone || '-' }}</strong>
                        </article>
                        <article>
                            <span>Registered</span>
                            <strong>{{ formatDate(selectedTeacher.registered_at) }}</strong>
                        </article>
                        <article>
                            <span>Experience</span>
                            <strong>{{ selectedTeacher.experience_years || 0 }} years</strong>
                        </article>
                        <article>
                            <span>Rate</span>
                            <strong>{{ selectedTeacher.is_free ? 'Free' : formatCurrency(selectedTeacher.hourly_rate) }}</strong>
                        </article>
                    </div>
                </section>

                <section class="drawer-section">
                    <h4>Subjects and languages</h4>
                    <p><strong>Subjects:</strong> {{ (selectedTeacher.subjects || []).join(', ') || '-' }}</p>
                    <p><strong>Languages:</strong> {{ (selectedTeacher.languages || []).join(', ') || '-' }}</p>
                    <p><strong>Previous school:</strong> {{ selectedTeacher.previous_school || '-' }}</p>
                    <p><strong>Bio:</strong> {{ selectedTeacher.bio || '-' }}</p>
                </section>

                <section class="drawer-section">
                    <h4>Documents</h4>
                    <ul class="document-list">
                        <li v-for="doc in selectedTeacher.documents" :key="doc.id">
                            <div>
                                <strong>{{ doc.original_filename || doc.type }}</strong>
                                <p>{{ doc.type }} · {{ formatBytes(doc.file_size) }} · {{ doc.status }}</p>
                            </div>
                            <button type="button" class="admin-btn admin-btn-secondary" @click="viewDocument(doc)">View</button>
                        </li>
                        <li v-if="!selectedTeacher.documents?.length" class="empty-docs">No documents uploaded.</li>
                    </ul>
                </section>

                <section class="drawer-section">
                    <h4>Decision note</h4>
                    <textarea
                        v-model="reviewNote"
                        rows="3"
                        class="drawer-textarea"
                        placeholder="Add context for this verification decision"
                    />
                </section>
            </template>

            <template #footer>
                <button type="button" class="admin-btn admin-btn-secondary" @click="closeDrawer">Close</button>
                <button type="button" class="admin-btn admin-btn-danger" @click="rejectTeacher" :disabled="actionLoading">Reject</button>
                <button type="button" class="admin-btn admin-btn-primary" @click="approveTeacher" :disabled="actionLoading">Approve</button>
            </template>
        </AdminDrawer>
    </AdminLayout>
</template>

<style scoped>
.verifications-page {
    display: flex;
    flex-direction: column;
    gap: 14px;
}

.verifications-header {
    display: flex;
    justify-content: space-between;
    gap: 12px;
    align-items: flex-start;
}

.eyebrow {
    margin: 0;
    color: #9CA3AF;
    font-size: 12px;
    text-transform: uppercase;
    letter-spacing: 0.09em;
    font-weight: 700;
}

h2 {
    margin: 4px 0;
    font-size: 28px;
    color: #2D2D2D;
}

.subcopy {
    margin: 0;
    color: #667085;
}

.header-actions {
    display: flex;
    gap: 8px;
    align-items: center;
}

.search-field {
    min-width: 260px;
    display: flex;
    align-items: center;
    gap: 8px;
}

.search-icon {
    color: #9CA3AF;
}

.error-banner {
    margin: 0;
    padding: 10px 12px;
    border-radius: 10px;
    border: 1px solid #fecaca;
    background: #fff1f2;
    color: #b91c1c;
    font-weight: 600;
}

.notice-banner {
    margin: 0;
    padding: 10px 12px;
    border-radius: 10px;
    border: 1px solid #86efac;
    background: #ecfdf3;
    color: #166534;
    font-weight: 600;
}

.table-card {
    border: 1px solid #e5ebf3;
    border-radius: 16px;
    background: #fff;
    overflow: hidden;
    box-shadow: 0 14px 36px rgba(15, 23, 42, 0.06);
}

.verification-table {
    width: 100%;
    border-collapse: collapse;
}

.verification-table thead {
    background: #f8fbff;
}

.verification-table th {
    text-align: left;
    padding: 12px 14px;
    font-size: 12px;
    text-transform: uppercase;
    letter-spacing: 0.07em;
    color: #9CA3AF;
}

.verification-table td {
    padding: 12px 14px;
    border-top: 1px solid #eef2f7;
    color: #334155;
    font-size: 14px;
}

.table-row {
    cursor: pointer;
}

.table-row:hover {
    background: #f8fbff;
}

.teacher-cell {
    display: flex;
    flex-direction: column;
    gap: 2px;
}

.teacher-cell span {
    color: #64748B;
    font-size: 12px;
}

.status-pill {
    display: inline-flex;
    align-items: center;
    border-radius: 999px;
    padding: 3px 10px;
    font-size: 12px;
    text-transform: capitalize;
    font-weight: 700;
}

.status-pending {
    background: #fef3c7;
    color: #d97706;
}

.status-approved {
    background: #dcfce7;
    color: #15803d;
}

.status-rejected {
    background: #fee2e2;
    color: #dc2626;
}

.empty-state {
    text-align: center;
    color: #64748B;
    padding: 24px;
}

.drawer-section {
    margin-bottom: 18px;
}

.drawer-section h4 {
    margin: 0 0 8px;
    color: #2D2D2D;
}

.meta-grid {
    display: grid;
    grid-template-columns: repeat(2, minmax(0, 1fr));
    gap: 10px;
}

.meta-grid article {
    border: 1px solid #e2e8f0;
    border-radius: 10px;
    padding: 10px;
    display: flex;
    flex-direction: column;
    gap: 3px;
}

.meta-grid span {
    font-size: 12px;
    color: #9CA3AF;
}

.meta-grid strong {
    color: #2D2D2D;
}

.document-list {
    list-style: none;
    margin: 0;
    padding: 0;
    display: flex;
    flex-direction: column;
    gap: 8px;
}

.document-list li {
    border: 1px solid #e2e8f0;
    border-radius: 10px;
    padding: 10px;
    display: flex;
    justify-content: space-between;
    gap: 8px;
    align-items: center;
}

.document-list p {
    margin: 2px 0 0;
    font-size: 12px;
    color: #64748B;
}

.empty-docs {
    justify-content: center;
    color: #64748B;
    font-size: 13px;
}

.drawer-textarea {
    width: 100%;
    border: 1px solid #d6dfec;
    border-radius: 10px;
    padding: 10px;
    font-family: inherit;
    resize: vertical;
}
</style>
