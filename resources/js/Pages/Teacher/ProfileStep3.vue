<script setup>
import TeacherLayout from '@/Layouts/TeacherLayout.vue';
import { useForm } from '@inertiajs/vue3';
import { computed, onMounted, ref } from 'vue';
import { validateNumberRange } from '@/composables/useFormValidation';

onMounted(() => { document.body.setAttribute('data-portal', 'teacher'); });

const props = defineProps({
    profile: { type: Object, default: () => ({}) },
});

const form = useForm({
    is_free:     props.profile?.is_free ?? false,
    hourly_rate: props.profile?.hourly_rate || '',
});

const normalizeRate = () => {
    if (form.is_free) {
        form.hourly_rate = '';
        return;
    }

    const value = Number(form.hourly_rate);

    if (!Number.isFinite(value)) {
        form.hourly_rate = '';
        return;
    }

    form.hourly_rate = Math.min(50000, Math.max(1, Math.trunc(value)));
};

const selectFree = () => {
    form.is_free = true;
    form.hourly_rate = '';
    rateError.value = '';
};

const selectPaid = () => {
    form.is_free = false;
    if (!form.hourly_rate) {
        form.hourly_rate = 1;
    }
    normalizeRate();
};

const preview = computed(() => {
    if (form.is_free) return 'Students will see: Free sessions (Volunteer)';
    if (form.hourly_rate) return `Students will see: ₹${form.hourly_rate} per hour session.`;
    return '';
});

// Rate validation (Rulebook §10)
const rateError = ref('');
const validateRate = () => {
    if (form.is_free) { rateError.value = ''; return; }
    normalizeRate();
    const result = validateNumberRange(form.hourly_rate, 1, 50000, 'Hourly rate');
    rateError.value = result.error || '';
};

const submit = (saveForLater = false) => {
    normalizeRate();
    validateRate();
    if (rateError.value) return;
    form.post(route('teacher.profile.step3.save'), {
        data: { ...form.data(), save_for_later: saveForLater },
    });
};
</script>

<template>
    <TeacherLayout>
        <div style="padding:36px; background:#FFF8F0; min-height:100vh;">
            <div style="max-width:700px; margin:0 auto;">
                <div style="font-family:'Fredoka One',cursive; font-size:26px; color:#E8553E; margin-bottom:8px;">Step 3 of 5</div>
                <div style="display:flex; gap:8px; margin-bottom:36px;">
                    <div v-for="i in 5" :key="i" :style="`flex:1; height:6px; border-radius:3px; background:${i<=3 ? '#E8553E' : '#F0E8E0'};`"></div>
                </div>
                <h2 style="font-family:'Fredoka One',cursive; font-size:28px; color:#E8553E; margin-bottom:28px;">Set your rate</h2>

                <div style="background:#fff; border:1px solid #F0E8E0; border-radius:10px; padding:32px;">
                    <!-- Two option cards -->
                    <div style="display:grid; grid-template-columns:1fr 1fr; gap:20px; margin-bottom:28px;">
                        <!-- Free -->
                        <div class="option-free-toggle" @click="selectFree"
                            :style="`padding:28px; border:${form.is_free ? '3px solid #E8553E' : '2px solid #F0E8E0'}; background:${form.is_free ? '#FFF3EF' : '#fff'}; border-radius:10px; cursor:pointer; text-align:center;`">
                            <div style="font-size:36px; margin-bottom:10px;">💚</div>
                            <div style="font-family:'Fredoka One',cursive; font-size:20px; color:#E8553E; font-weight:bold;">Teach for Free</div>
                            <div style="font-family:'Nunito',sans-serif; font-size:16px; color:#888; margin-top:8px;">Volunteer and give back to students</div>
                        </div>
                        <!-- Paid -->
                        <div @click="selectPaid"
                            :style="`padding:28px; border:${!form.is_free ? '3px solid #E8553E' : '2px solid #F0E8E0'}; background:${!form.is_free ? '#FFF3EF' : '#fff'}; border-radius:10px; cursor:pointer; text-align:center;`">
                            <div style="font-size:36px; margin-bottom:10px;">💰</div>
                            <div style="font-family:'Fredoka One',cursive; font-size:20px; color:#E8553E; font-weight:bold;">Charge Students</div>
                            <div style="font-family:'Nunito',sans-serif; font-size:16px; color:#888; margin-top:8px;">Set your hourly rate in ₹</div>
                        </div>
                    </div>

                    <!-- Rate input (only when paid) -->
                    <div v-if="!form.is_free" style="margin-bottom:24px;">
                        <label for="step3-rate" style="font-family:'Nunito',sans-serif; font-size:20px; font-weight:bold; color:#333; display:block; margin-bottom:10px;">
                            Your hourly rate (₹) <span style="color:#E8553E;">*</span>
                        </label>
                        <div style="display:flex; align-items:center; gap:12px;">
                            <span style="font-family:'Fredoka One',cursive; font-size:28px; color:#E8553E;">₹</span>
                            <input id="step3-rate" v-model.number="form.hourly_rate" type="number" min="1" max="50000" placeholder="200"
                                :aria-invalid="(form.errors.hourly_rate || rateError) ? 'true' : 'false'"
                                aria-describedby="step3-rate-error"
                                style="width:180px; padding:14px 16px; border:2px solid #F0E8E0; border-radius:8px; font-family:'Fredoka One',cursive; font-size:28px; min-height:56px; outline:none; text-align:center;"
                                @input="normalizeRate"
                                @focus="$event.target.style.borderColor='#E8553E'"
                                @blur="validateRate; $event.target.style.borderColor='#F0E8E0'" />
                            <span style="font-family:'Nunito',sans-serif; font-size:18px; color:#888;">per hour</span>
                        </div>
                        <div id="step3-rate-error" role="alert" style="color:#c0392b; font-size:16px; margin-top:6px; min-height:20px;">
                            {{ form.errors.hourly_rate || rateError }}
                        </div>
                    </div>

                    <!-- Preview -->
                    <div v-if="preview"
                        style="background:#FFF3EF; border:1px solid #F0E8E0; border-radius:8px; padding:16px 20px; font-family:'Nunito',sans-serif; font-size:18px; color:#E8553E; margin-bottom:28px;">
                        {{ preview }}
                    </div>

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
    </TeacherLayout>
</template>
