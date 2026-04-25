<script setup>
import TeacherLayout from '@/Layouts/TeacherLayout.vue';
import axios from 'axios';
import { Head, Link } from '@inertiajs/vue3';
import { ref } from 'vue';

const props = defineProps({
    availability: {
        type: Object,
        default: () => ({}),
    },
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

const normalizeTime = (value) => (typeof value === 'string' ? value.slice(0, 5) : '');

const days = ref(
    dayMeta.map((day) => {
        const existing = props.availability?.[day.key];

        return {
            day_of_week: day.key,
            label: day.label,
            enabled: Boolean(existing),
            start_time: normalizeTime(existing?.start_time) || '09:00',
            end_time: normalizeTime(existing?.end_time) || '17:00',
        };
    }),
);

const saving = ref(false);
const statusMessage = ref('');
const statusType = ref('');
const errors = ref({});

const setStatus = (type, message) => {
    statusType.value = type;
    statusMessage.value = message;
};

const fieldError = (index, field) => errors.value?.[`days.${index}.${field}`]?.[0] || '';

const validateLocally = () => {
    const localErrors = {};

    days.value.forEach((day, index) => {
        if (!day.enabled) {
            return;
        }

        if (!day.start_time) {
            localErrors[`days.${index}.start_time`] = ['Start time is required.'];
        }

        if (!day.end_time) {
            localErrors[`days.${index}.end_time`] = ['End time is required.'];
        }

        if (day.start_time && day.end_time && day.end_time <= day.start_time) {
            localErrors[`days.${index}.end_time`] = ['End time must be after start time.'];
        }
    });

    errors.value = localErrors;
    return Object.keys(localErrors).length === 0;
};

const toggleDay = (day) => {
    day.enabled = !day.enabled;
    if (day.enabled) {
        day.start_time = day.start_time || '09:00';
        day.end_time = day.end_time || '17:00';
    }
};

const save = async () => {
    setStatus('', '');

    if (!validateLocally()) {
        setStatus('error', 'Please fix the highlighted time fields before saving.');
        return;
    }

    saving.value = true;

    try {
        const payload = {
            days: days.value.map((day) => ({
                day_of_week: day.day_of_week,
                enabled: day.enabled,
                start_time: day.enabled ? day.start_time : null,
                end_time: day.enabled ? day.end_time : null,
            })),
        };

        const response = await axios.post(route('teacher.availability.store'), payload);
        errors.value = {};
        setStatus('success', response?.data?.message || 'Availability saved successfully.');
    } catch (error) {
        if (error?.response?.status === 422) {
            errors.value = error.response.data?.errors || {};
            setStatus('error', 'Please fix the highlighted fields and try again.');
        } else {
            setStatus('error', error?.response?.data?.message || 'Unable to save availability right now.');
        }
    } finally {
        saving.value = false;
    }
};
</script>

<template>
    <Head title="Teacher Availability" />

    <TeacherLayout page-title="Availability">
        <div class="availability-page">
            <section class="hero-panel">
                <p class="eyebrow">Weekly Schedule</p>
                <h1>Set your teaching hours</h1>
                <p>
                    Students can only book inside your enabled slots. Keep this schedule realistic so your bookings and attendance stay consistent.
                </p>
            </section>

            <section class="panel">
                <header class="panel-header">
                    <h2>Weekly availability</h2>
                    <Link :href="route('teacher.dashboard')">Back to dashboard</Link>
                </header>

                <div class="days-grid">
                    <article v-for="(day, index) in days" :key="day.day_of_week" class="day-card" :class="day.enabled ? 'active' : 'inactive'">
                        <div class="day-top-row">
                            <div>
                                <h3>{{ day.label }}</h3>
                                <p>{{ day.enabled ? 'Available for booking' : 'Unavailable' }}</p>
                            </div>
                            <button class="toggle-btn" type="button" @click="toggleDay(day)">
                                {{ day.enabled ? 'Disable' : 'Enable' }}
                            </button>
                        </div>

                        <div v-if="day.enabled" class="time-row">
                            <label>
                                <span>Start time</span>
                                <input v-model="day.start_time" type="time" />
                                <small v-if="fieldError(index, 'start_time')" class="error-copy">{{ fieldError(index, 'start_time') }}</small>
                            </label>

                            <label>
                                <span>End time</span>
                                <input v-model="day.end_time" type="time" />
                                <small v-if="fieldError(index, 'end_time')" class="error-copy">{{ fieldError(index, 'end_time') }}</small>
                            </label>
                        </div>
                    </article>
                </div>

                <p v-if="statusMessage" class="status-banner" :class="statusType === 'success' ? 'ok' : 'error'">
                    {{ statusMessage }}
                </p>

                <div class="actions">
                    <button type="button" class="save-btn" :disabled="saving" @click="save">
                        {{ saving ? 'Saving...' : 'Save Availability' }}
                    </button>
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
    color: #2D2D2D;
    font-size: 20px;
}

.panel-header a {
    color: #E8553E;
    text-decoration: none;
    font-weight: 700;
    font-size: 14px;
}

.days-grid {
    display: grid;
    grid-template-columns: repeat(2, minmax(0, 1fr));
    gap: 10px;
}

.day-card {
    border-radius: 10px;
    border: 1px solid #f0e8e0;
    padding: 12px;
    background: #fff;
}

.day-card.active {
    background: #fff8f0;
    border-color: #f8d5c8;
}

.day-top-row {
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
    gap: 12px;
}

.day-top-row h3 {
    margin: 0;
    color: #2D2D2D;
    font-size: 17px;
}

.day-top-row p {
    margin: 3px 0 0;
    font-size: 13px;
    color: #64748B;
}

.toggle-btn {
    border: 1px solid #f2b7a8;
    background: #fff;
    color: #E8553E;
    border-radius: 999px;
    padding: 5px 10px;
    font-weight: 700;
    font-size: 12px;
    cursor: pointer;
}

.time-row {
    margin-top: 10px;
    display: grid;
    grid-template-columns: repeat(2, minmax(0, 1fr));
    gap: 10px;
}

label {
    display: flex;
    flex-direction: column;
    gap: 5px;
}

label span {
    font-size: 12px;
    color: #64748B;
    font-weight: 700;
}

input[type='time'] {
    border: 1px solid #e5e7eb;
    border-radius: 8px;
    padding: 7px 8px;
    font-size: 14px;
    color: #1f2937;
    background: #fff;
}

.error-copy {
    font-size: 12px;
    color: #dc2626;
}

.status-banner {
    margin-top: 12px;
    border-radius: 8px;
    border: 1px solid transparent;
    padding: 9px 10px;
    font-size: 14px;
}

.status-banner.ok {
    background: #ecfdf3;
    border-color: #86efac;
    color: #166534;
}

.status-banner.error {
    background: #fef2f2;
    border-color: #fecaca;
    color: #991b1b;
}

.actions {
    margin-top: 12px;
    display: flex;
    justify-content: flex-end;
}

.save-btn {
    border: none;
    border-radius: 999px;
    padding: 10px 16px;
    background: #E8553E;
    color: #fff;
    font-size: 14px;
    font-weight: 700;
    cursor: pointer;
}

.save-btn:disabled {
    opacity: 0.7;
    cursor: not-allowed;
}

@media (max-width: 980px) {
    .days-grid {
        grid-template-columns: 1fr;
    }

    .time-row {
        grid-template-columns: 1fr;
    }
}
</style>
