<script setup>
import TeacherLayout from '@/Layouts/TeacherLayout.vue';
import axios from 'axios';
import { Head, Link } from '@inertiajs/vue3';
import { ref, onMounted, computed } from 'vue';

const availabilities = ref([]);
const loading = ref(false);
const saving = ref(false);
const statusMessage = ref('');
const statusType = ref('');
const errors = ref({});

const showForm = ref(false);
const editingId = ref(null);

const form = ref({
    day_of_week: 'monday',
    start_time: '09:00',
    end_time: '17:00',
    is_recurring: true,
    specific_date: '',
});

const dayMeta = [
    { key: 'monday', label: 'Monday' },
    { key: 'tuesday', label: 'Tuesday' },
    { key: 'wednesday', label: 'Wednesday' },
    { key: 'thursday', label: 'Thursday' },
    { key: 'friday', label: 'Friday' },
    { key: 'saturday', label: 'Saturday' },
    { key: 'sunday', label: 'Sunday' },
];

const setStatus = (type, message) => {
    statusType.value = type;
    statusMessage.value = message;
    setTimeout(() => { statusMessage.value = ''; }, 5000);
};

const fetchAvailability = async () => {
    loading.value = true;
    try {
        const response = await axios.get('/api/teacher/availability');
        availabilities.value = response.data.data || [];
    } catch (e) {
        setStatus('error', 'Failed to load availability.');
    } finally {
        loading.value = false;
    }
};

const openAddForm = () => {
    editingId.value = null;
    form.value = {
        day_of_week: 'monday',
        start_time: '09:00',
        end_time: '17:00',
        is_recurring: true,
        specific_date: '',
    };
    errors.value = {};
    showForm.value = true;
};

const openEditForm = (avail) => {
    editingId.value = avail.id;
    form.value = {
        day_of_week: avail.day_of_week || 'monday',
        start_time: avail.start_time.slice(0, 5),
        end_time: avail.end_time.slice(0, 5),
        is_recurring: avail.is_recurring,
        specific_date: avail.specific_date ? avail.specific_date.slice(0, 10) : '',
    };
    errors.value = {};
    showForm.value = true;
};

const saveSlot = async () => {
    saving.value = true;
    errors.value = {};
    
    try {
        const payload = { ...form.value };
        if (payload.is_recurring) {
            payload.specific_date = null;
        } else {
            payload.day_of_week = null;
        }

        if (editingId.value) {
            await axios.patch(`/api/teacher/availability/${editingId.value}`, payload);
            setStatus('success', 'Time slot updated successfully.');
        } else {
            await axios.post('/api/teacher/availability', payload);
            setStatus('success', 'Time slot added successfully.');
        }
        
        showForm.value = false;
        fetchAvailability();
    } catch (error) {
        if (error?.response?.status === 422) {
            if (error.response.data.errors) {
                errors.value = error.response.data.errors;
            } else if (error.response.data.message) {
                setStatus('error', error.response.data.message);
            }
        } else {
            setStatus('error', 'An error occurred while saving.');
        }
    } finally {
        saving.value = false;
    }
};

const deleteSlot = async (id) => {
    if (!confirm('Are you sure you want to delete this time slot? Future unbooked slots will be removed.')) return;
    
    try {
        await axios.delete(`/api/teacher/availability/${id}`);
        setStatus('success', 'Time slot deleted.');
        fetchAvailability();
    } catch (error) {
        setStatus('error', 'Failed to delete time slot.');
    }
};

const recurringSlots = computed(() => availabilities.value.filter(a => a.is_recurring));
const specificSlots = computed(() => availabilities.value.filter(a => !a.is_recurring));

onMounted(() => {
    fetchAvailability();
});
</script>

<template>
    <Head title="Teacher Availability" />

    <TeacherLayout page-title="Availability">
        <div class="availability-page">
            <section class="hero-panel">
                <p class="eyebrow">Weekly Schedule</p>
                <h1>Set your teaching hours</h1>
                <p>
                    Students can only book inside your enabled slots. Set your recurring weekly schedule or add one-time exceptions for specific dates.
                </p>
            </section>

            <section class="panel">
                <header class="panel-header">
                    <h2>Your Availability Slots</h2>
                    <button class="add-btn" @click="openAddForm">+ Add Slot</button>
                </header>

                <p v-if="statusMessage" class="status-banner" :class="statusType === 'success' ? 'ok' : 'error'">
                    {{ statusMessage }}
                </p>

                <div v-if="showForm" class="form-card">
                    <h3>{{ editingId ? 'Edit Time Slot' : 'Add Time Slot' }}</h3>
                    
                    <div class="form-row">
                        <label>
                            <span>Type</span>
                            <select v-model="form.is_recurring">
                                <option :value="true">Recurring Weekly</option>
                                <option :value="false">Specific Date</option>
                            </select>
                        </label>
                        
                        <label v-if="form.is_recurring">
                            <span>Day of Week</span>
                            <select v-model="form.day_of_week">
                                <option v-for="day in dayMeta" :key="day.key" :value="day.key">{{ day.label }}</option>
                            </select>
                            <small class="error-copy" v-if="errors.day_of_week">{{ errors.day_of_week[0] }}</small>
                        </label>

                        <label v-else>
                            <span>Date</span>
                            <input type="date" v-model="form.specific_date" />
                            <small class="error-copy" v-if="errors.specific_date">{{ errors.specific_date[0] }}</small>
                        </label>
                    </div>

                    <div class="form-row">
                        <label>
                            <span>Start Time</span>
                            <input type="time" v-model="form.start_time" />
                            <small class="error-copy" v-if="errors.start_time">{{ errors.start_time[0] }}</small>
                        </label>

                        <label>
                            <span>End Time</span>
                            <input type="time" v-model="form.end_time" />
                            <small class="error-copy" v-if="errors.end_time">{{ errors.end_time[0] }}</small>
                        </label>
                    </div>

                    <div class="form-actions">
                        <button class="cancel-btn" @click="showForm = false">Cancel</button>
                        <button class="save-btn" :disabled="saving" @click="saveSlot">{{ saving ? 'Saving...' : 'Save Slot' }}</button>
                    </div>
                </div>

                <div v-if="loading" class="loading">Loading...</div>
                
                <div v-else-if="!availabilities.length && !showForm" class="empty">
                    No availability slots configured yet. Click "+ Add Slot" to get started.
                </div>

                <div v-else class="slots-container">
                    <div v-if="recurringSlots.length > 0">
                        <h3 class="section-title">Recurring Weekly</h3>
                        <div class="slots-grid">
                            <article v-for="slot in recurringSlots" :key="slot.id" class="slot-card">
                                <div class="slot-info">
                                    <h4>{{ slot.day_of_week.charAt(0).toUpperCase() + slot.day_of_week.slice(1) }}</h4>
                                    <p>{{ slot.start_time.slice(0, 5) }} - {{ slot.end_time.slice(0, 5) }}</p>
                                </div>
                                <div class="slot-actions">
                                    <button class="edit-btn" @click="openEditForm(slot)">Edit</button>
                                    <button class="delete-btn" @click="deleteSlot(slot.id)">Delete</button>
                                </div>
                            </article>
                        </div>
                    </div>

                    <div v-if="specificSlots.length > 0">
                        <h3 class="section-title">Specific Dates</h3>
                        <div class="slots-grid">
                            <article v-for="slot in specificSlots" :key="slot.id" class="slot-card">
                                <div class="slot-info">
                                    <h4>{{ slot.specific_date }}</h4>
                                    <p>{{ slot.start_time.slice(0, 5) }} - {{ slot.end_time.slice(0, 5) }}</p>
                                </div>
                                <div class="slot-actions">
                                    <button class="edit-btn" @click="openEditForm(slot)">Edit</button>
                                    <button class="delete-btn" @click="deleteSlot(slot.id)">Delete</button>
                                </div>
                            </article>
                        </div>
                    </div>
                </div>
            </section>
        </div>
    </TeacherLayout>
</template>

<style scoped>
.availability-page {
    display: flex;
    flex-direction: column;
    gap: 14px;
}

.hero-panel {
    border-radius: 14px;
    border: 1px solid #f0e8e0;
    background: #fff;
    padding: 18px;
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

.hero-panel p {
    margin: 0;
    color: #64748B;
    font-size: 15px;
    line-height: 1.5;
}

.panel {
    border-radius: 14px;
    border: 1px solid #f0e8e0;
    background: #fff;
    padding: 18px;
}

.panel-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 20px;
}

.panel-header h2 {
    margin: 0;
    color: #2D2D2D;
    font-size: 20px;
}

.add-btn {
    border: none;
    border-radius: 999px;
    padding: 8px 16px;
    background: #E8553E;
    color: #fff;
    font-size: 14px;
    font-weight: 700;
    cursor: pointer;
}

.form-card {
    background: #fff8f0;
    border: 1px solid #f8d5c8;
    border-radius: 10px;
    padding: 16px;
    margin-bottom: 20px;
}

.form-card h3 {
    margin-top: 0;
    margin-bottom: 15px;
    color: #2D2D2D;
    font-size: 18px;
}

.form-row {
    display: flex;
    gap: 15px;
    margin-bottom: 15px;
}

label {
    display: flex;
    flex-direction: column;
    gap: 5px;
    flex: 1;
}

label span {
    font-size: 13px;
    color: #64748B;
    font-weight: 700;
}

input, select {
    border: 1px solid #e5e7eb;
    border-radius: 8px;
    padding: 9px;
    font-size: 14px;
    background: #fff;
    color: #1f2937;
}

.error-copy {
    font-size: 12px;
    color: #dc2626;
}

.form-actions {
    display: flex;
    justify-content: flex-end;
    gap: 10px;
    margin-top: 20px;
}

.cancel-btn {
    border: 1px solid #e5e7eb;
    background: #fff;
    color: #4b5563;
    padding: 8px 16px;
    border-radius: 999px;
    font-weight: 700;
    cursor: pointer;
}

.save-btn {
    border: none;
    background: #E8553E;
    color: #fff;
    padding: 8px 16px;
    border-radius: 999px;
    font-weight: 700;
    cursor: pointer;
}

.section-title {
    margin-top: 20px;
    margin-bottom: 10px;
    color: #4b5563;
    font-size: 16px;
    border-bottom: 1px solid #f0e8e0;
    padding-bottom: 5px;
}

.slots-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
    gap: 15px;
}

.slot-card {
    border: 1px solid #f0e8e0;
    border-radius: 10px;
    padding: 15px;
    display: flex;
    justify-content: space-between;
    align-items: center;
    background: #fff;
}

.slot-info h4 {
    margin: 0 0 5px 0;
    color: #1f2937;
    font-size: 16px;
}

.slot-info p {
    margin: 0;
    color: #6b7280;
    font-size: 14px;
}

.slot-actions {
    display: flex;
    gap: 8px;
}

.edit-btn, .delete-btn {
    border: none;
    background: transparent;
    font-size: 13px;
    font-weight: 700;
    cursor: pointer;
    padding: 5px;
}

.edit-btn {
    color: #3b82f6;
}

.delete-btn {
    color: #ef4444;
}

.status-banner {
    border-radius: 8px;
    padding: 10px;
    margin-bottom: 15px;
    font-size: 14px;
}

.status-banner.ok {
    background: #ecfdf3;
    color: #166534;
    border: 1px solid #86efac;
}

.status-banner.error {
    background: #fef2f2;
    color: #991b1b;
    border: 1px solid #fecaca;
}

.empty {
    padding: 30px;
    text-align: center;
    color: #6b7280;
    background: #f9fafb;
    border-radius: 10px;
    border: 1px dashed #e5e7eb;
}
</style>
