<script setup>
import axios from 'axios';
import {
    ArrowDownTrayIcon,
    MagnifyingGlassIcon,
    PauseCircleIcon,
    PlayCircleIcon,
    TrashIcon,
    UsersIcon,
} from '@heroicons/vue/24/outline';
import { computed, onBeforeUnmount, onMounted, ref, watch } from 'vue';
import AdminDataTable from '@/Components/Admin/AdminDataTable.vue';
import AdminDrawer from '@/Components/Admin/AdminDrawer.vue';
import AdminStatusBadge from '@/Components/Admin/AdminStatusBadge.vue';
import AdminLayout from '@/Layouts/AdminLayout.vue';

const urlQuery = new URLSearchParams(window.location.search);
const currentRole = ref(new URLSearchParams(window.location.search).get('role') === 'teacher' ? 'teacher' : 'student');
const statusFilter = ref(urlQuery.get('status') || '');
const search = ref(urlQuery.get('search') || '');
const loading = ref(false);
const selectedIds = ref([]);
const sortState = ref({ key: 'name', direction: 'asc' });

const users = ref({ data: [] });
const pageError = ref('');

const drawerOpen = ref(false);
const drawerLoading = ref(false);
const drawerUser = ref(null);
const drawerTimeline = ref([]);
const drawerError = ref('');

let debounceTimer = null;

const statusOptions = ['', 'active', 'pending', 'suspended', 'verified'];

const availableStatusOptions = computed(() => {
    if (currentRole.value === 'teacher') {
        return statusOptions;
    }

    return statusOptions.filter((status) => status !== 'verified');
});

const displayStatus = (user) => {
    if (user?.role === 'teacher') {
        if (String(user?.status || '').toLowerCase() === 'suspended') {
            return 'suspended';
        }

        return Boolean(user?.teacher_verified) ? 'verified' : 'pending';
    }

    return user?.status || 'unknown';
};

const visibilityMeta = (user) => {
    if (user?.role !== 'teacher') {
        return null;
    }

    return user?.student_visibility || null;
};

const isVisibleToStudents = (user) => Boolean(visibilityMeta(user)?.is_visible_to_students);

const visibilityLabel = (user) => {
    if (user?.role !== 'teacher') {
        return 'Not applicable';
    }

    return isVisibleToStudents(user) ? 'Visible to students' : 'Hidden from students';
};

const visibilityReason = (user) => {
    const reasons = visibilityMeta(user)?.reasons || [];
    return reasons.length ? reasons[0]?.message || '' : '';
};

const totalSessions = (user) => Number(user.bookings_as_student_count || 0) + Number(user.bookings_as_teacher_count || 0);

const userColumns = [
    { key: 'name', label: 'Name', sortable: true },
    { key: 'email', label: 'Email', sortable: true, width: '220px' },
    { key: 'phone', label: 'Phone', width: '130px' },
    { key: 'created_at', label: 'Registered', sortable: true, width: '150px' },
    { key: 'sessions', label: 'Sessions', sortable: true, width: '110px', align: 'right', sortAccessor: (row) => totalSessions(row) },
    { key: 'status', label: 'Status', sortable: true, width: '120px' },
];

const fetchUsers = async () => {
    loading.value = true;
    pageError.value = '';

    try {
        const { data } = await axios.get('/api/admin/users', {
            params: {
                role: currentRole.value,
                status: statusFilter.value || undefined,
                search: search.value || undefined,
            },
        });

        users.value = data;

        const visibleIds = new Set((data.data || []).map((user) => user.id));
        selectedIds.value = selectedIds.value.filter((id) => visibleIds.has(id));
    } catch (error) {
        users.value = { data: [] };
        pageError.value = error?.response?.status === 401
            ? 'Admin API session expired. Refresh the page and sign in again.'
            : 'Unable to load users right now.';
    } finally {
        loading.value = false;
    }
};

const openDrawer = async (row) => {
    drawerOpen.value = true;
    drawerLoading.value = true;
    drawerUser.value = null;
    drawerTimeline.value = [];
    drawerError.value = '';

    try {
        const { data } = await axios.get(`/api/admin/users/${row.id}`);
        drawerUser.value = data.user || null;
        drawerTimeline.value = data.timeline || data.user?.timeline || [];
    } catch (error) {
        drawerError.value = error?.response?.status === 401
            ? 'Not authorized to view this user profile.'
            : 'Failed to load profile details.';
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

const switchRole = (role) => {
    if (currentRole.value === role) {
        return;
    }

    currentRole.value = role;
    if (role !== 'teacher' && statusFilter.value === 'verified') {
        statusFilter.value = '';
    }
    selectedIds.value = [];
};

const initials = (name = '') => {
    const parts = String(name).trim().split(/\s+/).filter(Boolean);
    return (parts.slice(0, 2).map((part) => part[0]).join('') || '?').toUpperCase();
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

const suspendUser = async (user) => {
    await axios.post(`/api/admin/users/${user.id}/suspend`);
    await fetchUsers();

    if (drawerUser.value?.id === user.id) {
        closeDrawer();
    }
};

const activateUser = async (user) => {
    await axios.post(`/api/admin/users/${user.id}/activate`);
    await fetchUsers();

    if (drawerUser.value?.id === user.id) {
        closeDrawer();
    }
};

const deleteUser = async (user) => {
    if (!user) {
        return;
    }

    const confirmed = window.confirm(`Delete ${user.email}? This action cannot be undone.`);
    if (!confirmed) {
        return;
    }

    await axios.delete(`/api/admin/users/${user.id}`, {
        data: {
            confirm_email: user.email,
        },
    });

    await fetchUsers();

    if (drawerUser.value?.id === user.id) {
        closeDrawer();
    }
};

const suspendSelected = async () => {
    if (!selectedIds.value.length) {
        return;
    }

    const confirmed = window.confirm(`Suspend ${selectedIds.value.length} selected users?`);
    if (!confirmed) {
        return;
    }

    await axios.post('/api/admin/users/bulk-suspend', {
        user_ids: selectedIds.value,
    });

    selectedIds.value = [];
    await fetchUsers();
};

const clearSelected = () => {
    selectedIds.value = [];
};

const exportCsv = async () => {
    await axios.post('/api/admin/users/export', {
        role: currentRole.value,
    });

    window.alert('Export queued. A download link will be emailed when ready.');
};

watch([statusFilter, currentRole], fetchUsers);

watch(search, () => {
    clearTimeout(debounceTimer);
    debounceTimer = window.setTimeout(fetchUsers, 300);
});

onMounted(() => {
    fetchUsers();
});

onBeforeUnmount(() => {
    clearTimeout(debounceTimer);
});

const usersCount = computed(() => users.value.data?.length || 0);
</script>

<template>
    <AdminLayout page-title="Users" :breadcrumb="['Admin', 'Users']">
        <div class="users-page">
            <section class="users-header">
                <div>
                    <p class="eyebrow">User management</p>
                    <h2>Students and teachers</h2>
                </div>

                <div class="header-actions">
                    <div class="summary-chip">
                        <UsersIcon class="icon-16" />
                        {{ usersCount }} records
                    </div>
                    <button type="button" class="admin-btn admin-btn-secondary" @click="exportCsv">
                        <ArrowDownTrayIcon class="icon-16" />
                        Export {{ currentRole }} CSV
                    </button>
                </div>
            </section>

            <section class="users-controls">
                <div class="role-tabs">
                    <button
                        type="button"
                        class="role-tab"
                        :class="{ active: currentRole === 'student' }"
                        @click="switchRole('student')"
                    >
                        Students
                    </button>
                    <button
                        type="button"
                        class="role-tab"
                        :class="{ active: currentRole === 'teacher' }"
                        @click="switchRole('teacher')"
                    >
                        Teachers
                    </button>
                </div>

                <div class="filters">
                    <label class="search-field">
                        <MagnifyingGlassIcon class="icon-16 search-icon" />
                        <input v-model="search" class="admin-field" type="text" placeholder="Search name or email" />
                    </label>

                    <select v-model="statusFilter" class="admin-select status-select">
                        <option v-for="status in availableStatusOptions" :key="status" :value="status">
                            {{ status ? `${status.charAt(0).toUpperCase()}${status.slice(1)}` : 'All statuses' }}
                        </option>
                    </select>
                </div>
            </section>

            <p v-if="pageError" class="error-banner">{{ pageError }}</p>

            <p v-if="loading" class="loading-copy">Loading users...</p>

            <AdminDataTable
                :columns="userColumns"
                :rows="users.data"
                row-key="id"
                selectable
                :selected-keys="selectedIds"
                :selected-row-key="drawerUser?.id"
                :sort-state="sortState"
                empty-text="No users found for this filter."
                @update:selected-keys="selectedIds = $event"
                @sort-change="sortState = $event"
                @row-click="openDrawer"
            >
                <template #cell-name="{ row }">
                    <div class="name-cell">
                        <span class="name-avatar">{{ initials(row.name) }}</span>
                        <div class="name-copy">
                            <strong>{{ row.name }}</strong>
                            <span>{{ row.role }}</span>
                            <span
                                v-if="row.role === 'teacher'"
                                class="visibility-badge"
                                :class="isVisibleToStudents(row) ? 'visible' : 'hidden'"
                            >
                                {{ visibilityLabel(row) }}
                            </span>
                            <span v-if="row.role === 'teacher' && visibilityReason(row)" class="visibility-reason">
                                {{ visibilityReason(row) }}
                            </span>
                        </div>
                    </div>
                </template>

                <template #cell-email="{ row }">
                    <div class="email-cell">
                        <span>{{ row.email }}</span>
                    </div>
                </template>

                <template #cell-phone="{ row }">
                    {{ row.phone || '-' }}
                </template>

                <template #cell-created_at="{ value }">
                    {{ formatDate(value) }}
                </template>

                <template #cell-sessions="{ row }">
                    {{ totalSessions(row) }}
                </template>

                <template #cell-status="{ row }">
                    <AdminStatusBadge :status="displayStatus(row)" />
                </template>

                <template #bulk-actions>
                    <button type="button" class="admin-btn admin-btn-danger" @click="suspendSelected">
                        <PauseCircleIcon class="icon-16" />
                        Suspend selected
                    </button>
                    <button type="button" class="admin-btn admin-btn-secondary" @click="clearSelected">
                        Clear selection
                    </button>
                </template>
            </AdminDataTable>
        </div>

        <AdminDrawer
            :open="drawerOpen"
            :loading="drawerLoading"
            :title="drawerUser?.name || 'User details'"
            :subtitle="drawerUser?.email || ''"
            @close="closeDrawer"
        >
            <p v-if="drawerError" class="drawer-error-banner">{{ drawerError }}</p>

            <template v-if="drawerUser">
                <section class="drawer-profile">
                    <div class="drawer-avatar">{{ initials(drawerUser.name) }}</div>
                    <div>
                        <h3>{{ drawerUser.name }}</h3>
                        <p>{{ drawerUser.email }}</p>
                    </div>
                </section>

                <section class="drawer-meta-grid">
                    <article>
                        <span>Status</span>
                        <strong>{{ displayStatus(drawerUser) }}</strong>
                    </article>
                    <article>
                        <span>Role</span>
                        <strong>{{ drawerUser.role }}</strong>
                    </article>
                    <article>
                        <span>Phone</span>
                        <strong>{{ drawerUser.phone || '-' }}</strong>
                    </article>
                    <article>
                        <span>Total sessions</span>
                        <strong>{{ totalSessions(drawerUser) }}</strong>
                    </article>
                    <article v-if="drawerUser.role === 'teacher'">
                        <span>Student visibility</span>
                        <strong>{{ visibilityLabel(drawerUser) }}</strong>
                    </article>
                </section>

                <section v-if="drawerUser.role === 'teacher' && visibilityReason(drawerUser)" class="drawer-section">
                    <h4>Visibility reason</h4>
                    <p class="visibility-reason-drawer">{{ visibilityReason(drawerUser) }}</p>
                </section>

                <section class="drawer-section">
                    <h4>Timeline</h4>
                    <ul class="timeline">
                        <li v-for="event in drawerTimeline" :key="`${event.event}-${event.date}`">
                            <strong>{{ event.event }}</strong>
                            <span>{{ formatDate(event.date) }}</span>
                        </li>
                        <li v-if="!drawerTimeline.length" class="timeline-empty">
                            No timeline entries available.
                        </li>
                    </ul>
                </section>
            </template>

            <template #footer>
                <button
                    v-if="drawerUser?.status !== 'suspended'"
                    type="button"
                    class="admin-btn admin-btn-danger"
                    @click="suspendUser(drawerUser)"
                >
                    <PauseCircleIcon class="icon-16" />
                    Suspend
                </button>
                <button
                    v-else
                    type="button"
                    class="admin-btn admin-btn-primary"
                    @click="activateUser(drawerUser)"
                >
                    <PlayCircleIcon class="icon-16" />
                    Activate
                </button>
                <button type="button" class="admin-btn admin-btn-secondary" @click="closeDrawer">
                    Close
                </button>
                <button type="button" class="admin-btn admin-btn-danger" @click="deleteUser(drawerUser)">
                    <TrashIcon class="icon-16" />
                    Delete user
                </button>
            </template>
        </AdminDrawer>
    </AdminLayout>
</template>

<style scoped>
.users-page {
    display: flex;
    flex-direction: column;
    gap: 14px;
}

.error-banner,
.drawer-error-banner {
    margin: 0;
    padding: 10px 12px;
    border-radius: 8px;
    border: 1px solid #fecaca;
    background: #fff1f2;
    color: #b91c1c;
    font-size: 13px;
    font-weight: 600;
}

.drawer-error-banner {
    margin-bottom: 10px;
}

.users-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 12px;
}

.header-actions {
    display: flex;
    align-items: center;
    gap: 8px;
}

.eyebrow {
    margin: 0;
    color: #9CA3AF;
    text-transform: uppercase;
    letter-spacing: 0.08em;
    font-size: 11px;
    font-weight: 600;
}

.users-header h2 {
    margin: 3px 0 0;
    color: #2D2D2D;
    font-size: 20px;
    line-height: 1.2;
    font-weight: 700;
}

.summary-chip {
    min-height: 34px;
    border-radius: 999px;
    background: #eff6ff;
    color: #D44433;
    padding: 0 10px;
    display: inline-flex;
    align-items: center;
    gap: 6px;
    font-size: 12px;
    font-weight: 700;
}

.users-controls {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 12px;
}

.role-tabs {
    display: inline-flex;
    border: 1px solid #F0E8E0;
    border-radius: 8px;
    overflow: hidden;
}

.role-tab {
    min-height: 34px;
    padding: 0 14px;
    border: none;
    border-right: 1px solid #F0E8E0;
    background: #ffffff;
    color: #2D2D2D;
    font-size: 13px;
    font-weight: 600;
    cursor: pointer;
}

.role-tab:last-child {
    border-right: none;
}

.role-tab.active {
    background: #eff6ff;
    color: #D44433;
}

.filters {
    display: flex;
    align-items: center;
    gap: 8px;
}

.search-field {
    min-width: 280px;
    display: flex;
    align-items: center;
    gap: 8px;
}

.search-icon {
    color: #9CA3AF;
}

.search-field .admin-field {
    padding-left: 0;
}

.status-select {
    width: 156px;
}

.loading-copy {
    margin: 0;
    font-size: 13px;
    color: #9CA3AF;
}

.name-cell {
    display: inline-flex;
    align-items: center;
    gap: 10px;
}

.name-avatar,
.drawer-avatar {
    border-radius: 999px;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    font-weight: 700;
}

.name-avatar {
    width: 28px;
    height: 28px;
    font-size: 11px;
    background: #FFE7DD;
    color: #D44433;
}

.name-copy {
    display: flex;
    flex-direction: column;
    min-width: 0;
}

.name-copy strong {
    font-size: 13px;
    font-weight: 600;
    color: #2D2D2D;
}

.name-copy span {
    margin-top: 2px;
    text-transform: capitalize;
    font-size: 12px;
    color: #9CA3AF;
}

.visibility-badge {
    margin-top: 4px;
    display: inline-flex;
    align-items: center;
    width: fit-content;
    border-radius: 999px;
    padding: 2px 8px;
    font-size: 11px;
    font-weight: 700;
    text-transform: none;
}

.visibility-badge.visible {
    background: #dcfce7;
    color: #166534;
}

.visibility-badge.hidden {
    background: #fef3c7;
    color: #92400e;
}

.visibility-reason {
    margin-top: 4px;
    font-size: 11px;
    color: #7f1d1d;
    text-transform: none;
}

.email-cell {
    max-width: 220px;
    overflow: hidden;
    text-overflow: ellipsis;
}

.icon-16 {
    width: 16px;
    height: 16px;
}

.drawer-profile {
    display: flex;
    align-items: center;
    gap: 12px;
    margin-bottom: 16px;
}

.drawer-avatar {
    width: 44px;
    height: 44px;
    font-size: 14px;
    background: #D44433;
    color: #ffffff;
}

.drawer-profile h3 {
    margin: 0;
    font-size: 17px;
    color: #2D2D2D;
    font-weight: 700;
}

.drawer-profile p {
    margin: 3px 0 0;
    color: #9CA3AF;
    font-size: 13px;
}

.drawer-meta-grid {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 10px;
    margin-bottom: 18px;
}

.drawer-meta-grid article {
    border: 1px solid #e2e8f0;
    border-radius: 8px;
    padding: 10px;
}

.drawer-meta-grid span {
    display: block;
    color: #9CA3AF;
    font-size: 11px;
}

.drawer-meta-grid strong {
    display: block;
    margin-top: 4px;
    color: #2D2D2D;
    font-size: 13px;
    text-transform: capitalize;
}

.drawer-section h4 {
    margin: 0 0 8px;
    font-size: 13px;
    color: #2D2D2D;
}

.visibility-reason-drawer {
    margin: 0;
    padding: 10px;
    border-radius: 8px;
    border: 1px solid #fef3c7;
    background: #fffbeb;
    color: #92400e;
    font-size: 12px;
    line-height: 1.5;
}

.timeline {
    list-style: none;
    margin: 0;
    padding: 0;
}

.timeline li {
    padding: 8px 0;
    border-bottom: 1px solid #f1f5f9;
}

.timeline li:last-child {
    border-bottom: none;
}

.timeline strong {
    display: block;
    font-size: 13px;
    color: #2D2D2D;
}

.timeline span {
    margin-top: 2px;
    display: block;
    font-size: 12px;
    color: #9CA3AF;
}

.timeline-empty {
    color: #9CA3AF;
    font-size: 12px;
}

@media (max-width: 1080px) {
    .users-header {
        flex-direction: column;
        align-items: stretch;
    }

    .header-actions {
        justify-content: space-between;
    }

    .users-controls {
        flex-direction: column;
        align-items: stretch;
    }

    .filters {
        width: 100%;
    }

    .search-field {
        min-width: 0;
        flex: 1;
    }
}
</style>
