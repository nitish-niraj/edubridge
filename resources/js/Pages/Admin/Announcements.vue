<script setup>
import AdminLayout from '@/Layouts/AdminLayout.vue';
import { computed, onMounted, ref } from 'vue';
import axios from 'axios';
import Quill from 'quill';
import 'quill/dist/quill.snow.css';
import {
    MegaphoneIcon,
    TrashIcon,
} from '@heroicons/vue/24/outline';

const announcements = ref({ data: [] });
const form = ref({
    title: '',
    message: '',
    target_role: 'all',
    delivery_type: 'banner',
    starts_at: new Date().toISOString().slice(0, 16),
    ends_at: '',
});
const saving = ref(false);
const scheduleMode = ref('now');
const editorEl = ref(null);
let quill = null;

const fetchAnnouncements = async () => {
    const { data } = await axios.get('/api/admin/announcements');
    announcements.value = data;
};

const updateDelivery = (value, checked) => {
    const current = new Set(form.value.delivery_type === 'both' ? ['banner', 'email'] : [form.value.delivery_type]);
    if (checked) current.add(value);
    else current.delete(value);

    if (current.size === 2) {
        form.value.delivery_type = 'both';
    } else if (current.has('banner')) {
        form.value.delivery_type = 'banner';
    } else if (current.has('email')) {
        form.value.delivery_type = 'email';
    } else {
        current.add('banner');
        form.value.delivery_type = 'banner';
    }
};

const setQuillMessage = () => {
    form.value.message = quill?.root.innerHTML || '';
};

onMounted(() => {
    document.body.setAttribute('data-portal', 'admin');
    fetchAnnouncements();

    if (editorEl.value) {
        quill = new Quill(editorEl.value, {
            theme: 'snow',
            modules: {
                toolbar: [
                    ['bold', 'italic'],
                    [{ list: 'bullet' }],
                ],
            },
        });
        quill.on('text-change', setQuillMessage);
    }
});

const resetForm = () => {
    form.value = {
        title: '',
        message: '',
        target_role: 'all',
        delivery_type: 'banner',
        starts_at: new Date().toISOString().slice(0, 16),
        ends_at: '',
    };
    scheduleMode.value = 'now';
    if (quill) {
        quill.setText('');
    }
};

const submit = async () => {
    saving.value = true;
    const payload = {
        ...form.value,
        starts_at: scheduleMode.value === 'now' ? new Date().toISOString() : form.value.starts_at,
        ends_at: form.value.ends_at || null,
    };

    try {
        await axios.post('/api/admin/announcements', payload);
        resetForm();
        await fetchAnnouncements();
    } catch (error) {
        window.alert(error.response?.data?.message || 'Unable to create announcement.');
    } finally {
        saving.value = false;
    }
};

const remove = async (id) => {
    if (!window.confirm('Delete this announcement?')) return;
    await axios.delete(`/api/admin/announcements/${id}`);
    await fetchAnnouncements();
};

const isBannerSelected = computed(() => form.value.delivery_type === 'banner' || form.value.delivery_type === 'both');
const isEmailSelected = computed(() => form.value.delivery_type === 'email' || form.value.delivery_type === 'both');
</script>

<template>
    <AdminLayout>
        <div class="announcements-page">
            <div class="page-header">
                <div>
                    <p class="eyebrow">Communications</p>
                    <h1>Announcements</h1>
                </div>
                <div class="summary-chip">
                    <MegaphoneIcon class="chip-icon" />
                    Broadcasts
                </div>
            </div>

            <div class="guidance-strip">
                <p>
                    Use announcements for time-sensitive platform updates. Keep titles concise, target the correct audience,
                    and prefer banner + email for critical notices.
                </p>
            </div>

            <div class="card composer-card">
                <div class="composer-grid">
                    <div class="field-group full">
                        <label>Title</label>
                        <input v-model="form.title" type="text" class="text-input" placeholder="Announcement title" />
                        <p class="helper">Recommended: 8-12 words with one clear action.</p>
                    </div>

                    <div class="field-group full">
                        <label>Message</label>
                        <div ref="editorEl" class="quill-shell" />
                        <p class="helper">Keep the first sentence decision-ready so users can understand it quickly.</p>
                    </div>

                    <div class="field-group">
                        <label>Target role</label>
                        <div class="radio-row">
                            <label v-for="role in ['all', 'student', 'teacher']" :key="role" class="radio-chip">
                                <input v-model="form.target_role" type="radio" :value="role" />
                                <span>{{ role }}</span>
                            </label>
                        </div>
                        <p class="helper">All = students and teachers. Choose a role to avoid unnecessary notifications.</p>
                    </div>

                    <div class="field-group">
                        <label>Delivery type</label>
                        <div class="radio-row">
                            <label class="radio-chip">
                                <input :checked="isBannerSelected" type="checkbox" @change="updateDelivery('banner', $event.target.checked)" />
                                <span>Banner</span>
                            </label>
                            <label class="radio-chip">
                                <input :checked="isEmailSelected" type="checkbox" @change="updateDelivery('email', $event.target.checked)" />
                                <span>Email</span>
                            </label>
                        </div>
                        <p class="helper">Banner appears in-app. Email reaches inbox directly. Selecting both maximizes visibility.</p>
                    </div>

                    <div class="field-group">
                        <label>Schedule</label>
                        <div class="radio-row">
                            <label class="radio-chip">
                                <input v-model="scheduleMode" type="radio" value="now" />
                                <span>Now</span>
                            </label>
                            <label class="radio-chip">
                                <input v-model="scheduleMode" type="radio" value="later" />
                                <span>Scheduled</span>
                            </label>
                        </div>
                        <p class="helper">Use "Now" for urgent alerts; use "Scheduled" for planned maintenance or launches.</p>
                    </div>

                    <div class="field-group" v-if="scheduleMode === 'later'">
                        <label>Start date & time</label>
                        <input v-model="form.starts_at" type="datetime-local" class="text-input" />
                    </div>

                    <div class="field-group" v-if="scheduleMode === 'later'">
                        <label>End date & time</label>
                        <input v-model="form.ends_at" type="datetime-local" class="text-input" />
                    </div>

                    <div class="field-group full">
                        <label>Preview</label>
                        <div class="preview-card">
                            <p class="preview-eyebrow">{{ form.target_role === 'all' ? 'Audience: all users' : `Audience: ${form.target_role}s` }}</p>
                            <h2 class="preview-title">{{ form.title || 'Your announcement title will appear here' }}</h2>
                            <p class="preview-copy" v-html="form.message || 'Your announcement message preview will appear here once you start typing.'" />
                            <p class="preview-meta">Delivery: {{ form.delivery_type }} · {{ scheduleMode === 'now' ? 'Send now' : 'Scheduled' }}</p>
                        </div>
                    </div>
                </div>

                <div class="composer-actions">
                    <button class="primary-button" type="button" :disabled="saving || !form.title || !form.message" @click="submit">
                        {{ saving ? 'Sending...' : 'Create announcement' }}
                    </button>
                </div>
            </div>

            <div class="card table-card">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Title</th>
                            <th>Target</th>
                            <th>Delivery</th>
                            <th>Sent</th>
                            <th>Created</th>
                            <th>Active</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="announcement in announcements.data" :key="announcement.id" class="table-row">
                            <td>
                                <div class="title-cell">
                                    <strong>{{ announcement.title }}</strong>
                                    <span>{{ announcement.message?.slice(0, 80) }}</span>
                                </div>
                            </td>
                            <td class="capitalize">{{ announcement.target_role }}</td>
                            <td class="capitalize">{{ announcement.delivery_type }}</td>
                            <td>{{ announcement.sent_count ?? 0 }}</td>
                            <td>{{ new Date(announcement.created_at).toLocaleDateString() }}</td>
                            <td>
                                <span class="status-dot" :class="{ active: announcement.is_active }" />
                            </td>
                            <td class="table-actions">
                                <button class="danger-link" type="button" @click="remove(announcement.id)">
                                    <TrashIcon class="icon-18" />
                                    Delete
                                </button>
                            </td>
                        </tr>
                        <tr v-if="!announcements.data.length">
                            <td colspan="7" class="empty-state">No announcements yet.</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </AdminLayout>
</template>

<style scoped>
.announcements-page {
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

h1 {
    margin: 0;
    font-size: 28px;
    font-weight: 800;
    color: #2D2D2D;
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

.guidance-strip {
    border: 1px solid #e5ebf3;
    border-radius: 14px;
    background: #f8fbff;
    padding: 12px 14px;
}

.guidance-strip p {
    margin: 0;
    color: #2D2D2D;
    font-size: 13px;
    line-height: 1.55;
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

.composer-card {
    padding: 18px;
}

.composer-grid {
    display: grid;
    grid-template-columns: repeat(2, minmax(0, 1fr));
    gap: 16px;
}

.field-group.full {
    grid-column: 1 / -1;
}

.field-group {
    display: flex;
    flex-direction: column;
    gap: 8px;
}

.field-group label {
    font-size: 12px;
    font-weight: 800;
    letter-spacing: 0.08em;
    text-transform: uppercase;
    color: #9CA3AF;
}

.text-input {
    min-height: 44px;
    padding: 0 14px;
    border: 1px solid #dbe3ef;
    border-radius: 14px;
    background: #fff;
    font: inherit;
}

.radio-row {
    display: flex;
    flex-wrap: wrap;
    gap: 10px;
}

.radio-chip {
    display: inline-flex;
    align-items: center;
    gap: 8px;
    padding: 10px 12px;
    border: 1px solid #dbe3ef;
    border-radius: 14px;
    background: #f8fbff;
    font-size: 13px;
    font-weight: 700;
    color: #2D2D2D;
}

.helper {
    margin: 0;
    font-size: 12px;
    color: #9CA3AF;
}

.quill-shell {
    min-height: 180px;
    background: #fff;
    border-radius: 14px;
    overflow: hidden;
}

.preview-card {
    border: 1px solid #dbe3ef;
    border-radius: 14px;
    padding: 14px;
    background: #fff;
}

.preview-eyebrow {
    margin: 0;
    font-size: 11px;
    font-weight: 800;
    letter-spacing: 0.08em;
    text-transform: uppercase;
    color: #9CA3AF;
}

.preview-title {
    margin: 8px 0 6px;
    font-size: 16px;
    color: #2D2D2D;
    line-height: 1.3;
}

.preview-copy {
    margin: 0;
    font-size: 13px;
    color: #2D2D2D;
    line-height: 1.55;
}

.preview-meta {
    margin: 10px 0 0;
    font-size: 12px;
    color: #9CA3AF;
    font-weight: 700;
}

.composer-actions {
    margin-top: 16px;
    display: flex;
    justify-content: flex-end;
}

.primary-button,
.danger-link {
    min-height: 44px;
    padding: 0 14px;
    border-radius: 14px;
    border: 1px solid transparent;
    font-size: 13px;
    font-weight: 800;
    cursor: pointer;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: 8px;
}

.primary-button {
    background: #E8553E;
    color: #fff;
}

.primary-button:disabled {
    opacity: 0.6;
    cursor: not-allowed;
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
}

.data-table td {
    padding: 12px 16px;
    vertical-align: middle;
    color: #2D2D2D;
}

.title-cell {
    display: flex;
    flex-direction: column;
    gap: 4px;
}

.title-cell span {
    color: #9CA3AF;
    font-size: 13px;
}

.capitalize {
    text-transform: capitalize;
}

.status-dot {
    width: 10px;
    height: 10px;
    border-radius: 999px;
    background: #d1d5db;
    display: inline-block;
}

.status-dot.active {
    background: #16a34a;
}

.table-actions {
    text-align: right;
}

.danger-link {
    background: #fff1f2;
    color: #be123c;
    border-color: #fecdd3;
}

.empty-state {
    padding: 34px 16px;
    text-align: center;
    color: #9CA3AF;
}

@media (max-width: 900px) {
    .page-header,
    .composer-grid {
        grid-template-columns: 1fr;
    }
}
</style>
