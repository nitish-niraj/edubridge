<script setup>
import TeacherLayout from '@/Layouts/TeacherLayout.vue';
import { useForm } from '@inertiajs/vue3';
import { onMounted, ref } from 'vue';
import { validateTimeRange } from '@/composables/useFormValidation';

onMounted(() => { document.body.setAttribute('data-portal', 'teacher'); });

const props = defineProps({
    profile: { type: Object, default: () => ({}) },
});

const days = ['Monday','Tuesday','Wednesday','Thursday','Friday','Saturday','Sunday'];

const hours = Array.from({length: 24}, (_, i) => {
    const h = i.toString().padStart(2, '0');
    return `${h}:00`;
});

const form = useForm({
    availability: days.reduce((acc, day) => {
        acc[day] = props.profile?.availability?.[day] || { enabled: false, start: '09:00', end: '17:00' };
        return acc;
    }, {}),
});

// Per-day time errors (Rulebook §9: end must be after start)
const timeErrors = ref({});

const validateDayTime = (day) => {
    const { enabled, start, end } = form.availability[day];
    if (!enabled) { delete timeErrors.value[day]; return; }
    const result = validateTimeRange(start, end);
    if (!result.valid) {
        timeErrors.value[day] = result.error;
    } else {
        delete timeErrors.value[day];
    }
};

const submit = (saveForLater = false) => {
    // Check all enabled days
    days.forEach(validateDayTime);
    if (Object.keys(timeErrors.value).length > 0) return;

    const anyEnabled = days.some(d => form.availability[d].enabled);
    if (!anyEnabled && !saveForLater) {
        timeErrors.value['_global'] = 'Please enable at least one day of availability.';
        return;
    }
    delete timeErrors.value['_global'];

    form.post(route('teacher.profile.step4.save'), {
        data: { ...form.data(), save_for_later: saveForLater },
    });
};
</script>

<template>
    <TeacherLayout>
        <div style="padding:36px; background:#FFF8F0; min-height:100vh;">
            <div style="max-width:700px; margin:0 auto;">
                <div style="font-family:'Fredoka One',cursive; font-size:26px; color:#E8553E; margin-bottom:8px;">Step 4 of 5</div>
                <div style="display:flex; gap:8px; margin-bottom:36px;">
                    <div v-for="i in 5" :key="i" :style="`flex:1; height:6px; border-radius:3px; background:${i<=4 ? '#E8553E' : '#F0E8E0'};`"></div>
                </div>
                <h2 style="font-family:'Fredoka One',cursive; font-size:28px; color:#E8553E; margin-bottom:28px;">Your Availability</h2>

                <div style="background:#fff; border:1px solid #F0E8E0; border-radius:10px; padding:32px;">
                    <div v-for="day in days" :key="day"
                        style="display:flex; flex-direction:column; padding:16px 0; border-bottom:1px solid #F0F5F2; min-height:60px;">
                        <div style="display:flex; align-items:center; gap:20px;">
                            <!-- Day name -->
                            <span style="font-family:'Fredoka One',cursive; font-size:18px; font-weight:bold; color:#333; width:110px; flex-shrink:0;">{{ day }}</span>

                            <!-- Toggle -->
                            <label :aria-label="`Toggle ${day} availability`" style="position:relative; display:inline-block; width:52px; height:28px; flex-shrink:0; cursor:pointer;">
                                <input type="checkbox" v-model="form.availability[day].enabled"
                                    @change="validateDayTime(day)"
                                    style="opacity:0; width:0; height:0;" />
                                <span :style="`position:absolute; top:0;left:0;right:0;bottom:0; border-radius:14px; background:${form.availability[day].enabled ? '#E8553E' : '#ccc'}; transition:background 0.2s;`">
                                    <span :style="`position:absolute; height:22px; width:22px; left:${form.availability[day].enabled ? '26px' : '3px'}; bottom:3px; background:#fff; border-radius:50%; transition:left 0.2s;`"></span>
                                </span>
                            </label>

                            <!-- Time selects (only visible when enabled) -->
                            <template v-if="form.availability[day].enabled">
                                <select v-model="form.availability[day].start"
                                    :aria-label="`${day} start time`"
                                    @change="validateDayTime(day)"
                                    style="padding:10px 14px; border:2px solid #F0E8E0; border-radius:8px; font-family:'Nunito',sans-serif; font-size:18px; min-height:56px; background:#fff; color:#333;">
                                    <option v-for="h in hours" :key="h" :value="h">{{ h }}</option>
                                </select>
                                <span style="font-family:'Nunito',sans-serif; font-size:18px; color:#666;">to</span>
                                <select v-model="form.availability[day].end"
                                    :aria-label="`${day} end time`"
                                    @change="validateDayTime(day)"
                                    style="padding:10px 14px; border:2px solid #F0E8E0; border-radius:8px; font-family:'Nunito',sans-serif; font-size:18px; min-height:56px; background:#fff; color:#333;">
                                    <option v-for="h in hours" :key="h" :value="h">{{ h }}</option>
                                </select>
                            </template>
                            <span v-else style="font-family:'Nunito',sans-serif; font-size:18px; color:#aaa;">Not available</span>
                        </div>
                        <!-- Per-day time error (Rulebook §9) -->
                        <div v-if="timeErrors[day]" role="alert"
                            style="color:#c0392b; font-size:15px; margin-top:6px; margin-left:130px;">
                            {{ timeErrors[day] }}
                        </div>
                    </div>

                    <div style="margin-top:32px;">
                        <button type="button" @click="submit(false)" :disabled="form.processing"
                            style="width:100%; padding:18px; background:#E8553E; color:#fff; border:none; border-radius:8px; font-family:'Nunito',sans-serif; font-size:20px; font-weight:bold; cursor:pointer; min-height:56px; margin-bottom:16px;">
                            {{ form.processing ? 'Saving...' : 'Save & Continue →' }}
                        </button>
                        <div style="text-align:center;">
                            <button type="button" @click="submit(true)"
                                style="background:none; border:none; font-family:'Nunito',sans-serif; font-size:18px; color:#E8553E; cursor:pointer; text-decoration:underline; min-height:44px;">
                                Save for Later
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </TeacherLayout>
</template>
