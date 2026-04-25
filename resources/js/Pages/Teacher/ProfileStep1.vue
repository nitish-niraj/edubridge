<script setup>
import TeacherLayout from '@/Layouts/TeacherLayout.vue';
import { useForm } from '@inertiajs/vue3';
import { computed, onMounted } from 'vue';
import { charCount } from '@/composables/useFormValidation';

onMounted(() => { document.body.setAttribute('data-portal', 'teacher'); });

const props = defineProps({
    profile: { type: Object, default: () => ({}) },
});

const form = useForm({
    bio:              props.profile?.bio              || '',
    experience_years: props.profile?.experience_years ?? 0,
    previous_school:  props.profile?.previous_school  || '',
});

const bioCharCount    = computed(() => charCount(form.bio, 2000));
const bioIsShort      = computed(() => form.bio.length > 0 && form.bio.length < 50);
const bioHasError     = computed(() => form.errors.bio || bioIsShort.value);
const bioErrorMessage = computed(() => form.errors.bio || (bioIsShort.value ? `Bio must be at least 50 characters (currently ${form.bio.length}).` : ''));

const normalizeExperience = () => {
    const value = Number(form.experience_years);

    if (!Number.isFinite(value)) {
        form.experience_years = 0;
        return;
    }

    form.experience_years = Math.min(60, Math.max(0, Math.trunc(value)));
};

const submit = (saveForLater = false) => {
    normalizeExperience();

    form.post(route('teacher.profile.step1.save'), {
        data: { ...form.data(), save_for_later: saveForLater },
    });
};
</script>

<template>
    <TeacherLayout>
        <div style="padding:36px; background:#FFF8F0; min-height:100vh;">
            <div style="max-width:700px; margin:0 auto;">
                <!-- Step indicator -->
                <div style="font-family:'Fredoka One',cursive; font-size:26px; color:#E8553E; margin-bottom:8px;">Step 1 of 5</div>
                <div style="display:flex; gap:8px; margin-bottom:36px;">
                    <div v-for="i in 5" :key="i"
                        :style="`flex:1; height:6px; border-radius:3px; background:${i<=1 ? '#E8553E' : '#F0E8E0'};`"></div>
                </div>
                <h2 style="font-family:'Fredoka One',cursive; font-size:28px; color:#E8553E; margin-bottom:28px;">Tell us about yourself</h2>

                <div style="background:#fff; border:1px solid #F0E8E0; border-radius:10px; padding:32px;">
                        <form @submit.prevent="submit(false)" novalidate>
                        <!-- Bio -->
                        <div style="margin-bottom:28px;">
                            <label for="step1-bio" style="font-family:'Nunito',sans-serif; font-size:20px; font-weight:bold; color:#333; display:block; margin-bottom:10px;">
                                Tell students about yourself — your background, teaching style, and experience
                            </label>
                            <textarea id="step1-bio" v-model="form.bio" name="bio" rows="6"
                                placeholder="I have 25 years of experience teaching Mathematics at the secondary level..."
                                minlength="50" maxlength="2000"
                                :aria-invalid="bioHasError ? 'true' : 'false'"
                                aria-describedby="step1-bio-counter step1-bio-error"
                                :style="`width:100%; padding:16px; border:2px solid ${bioHasError ? '#E8553E' : '#F0E8E0'}; border-radius:8px; font-family:'Nunito',sans-serif; font-size:18px; resize:vertical; outline:none; box-sizing:border-box; min-height:160px;`"
                                @focus="$event.target.style.borderColor='#E8553E'"
                                @blur="$event.target.style.borderColor = bioHasError ? '#E8553E' : '#F0E8E0'"></textarea>
                            <!-- Character counter (Rulebook §8) -->
                            <div id="step1-bio-counter" style="font-family:'Nunito',sans-serif; font-size:14px; color:#888; text-align:right; margin-top:4px;">
                                {{ bioCharCount }}
                            </div>
                            <div id="step1-bio-error" role="alert" style="color:#c0392b; font-size:16px; margin-top:4px; min-height:20px;">
                                {{ bioErrorMessage }}
                            </div>
                        </div>

                        <!-- Experience Years -->
                        <div style="margin-bottom:28px;">
                            <label for="step1-exp" style="font-family:'Nunito',sans-serif; font-size:20px; font-weight:bold; color:#333; display:block; margin-bottom:10px;">
                                Years of Experience <span style="color:#E8553E;">*</span>
                            </label>
                            <input id="step1-exp" v-model.number="form.experience_years" name="experience_years" type="number" min="0" max="60"
                                placeholder="e.g. 25" required
                                :aria-invalid="form.errors.experience_years ? 'true' : 'false'"
                                aria-describedby="step1-exp-error"
                                style="width:180px; padding:14px 16px; border:2px solid #F0E8E0; border-radius:8px; font-family:'Nunito',sans-serif; font-size:24px; min-height:56px; outline:none; text-align:center;"
                                @input="normalizeExperience"
                                @focus="$event.target.style.borderColor='#E8553E'"
                                @blur="$event.target.style.borderColor='#F0E8E0'" />
                            <div id="step1-exp-error" role="alert" style="color:#c0392b; font-size:16px; margin-top:6px; min-height:20px;">
                                {{ form.errors.experience_years }}
                            </div>
                        </div>

                        <!-- Previous School -->
                        <div style="margin-bottom:36px;">
                            <label for="step1-school" style="font-family:'Nunito',sans-serif; font-size:20px; font-weight:bold; color:#333; display:block; margin-bottom:10px;">
                                Previous School / College
                            </label>
                            <input id="step1-school" v-model="form.previous_school" name="previous_school" type="text"
                                placeholder="e.g. Government Senior Secondary School, Chandigarh" maxlength="150"
                                style="width:100%; padding:14px 16px; border:2px solid #F0E8E0; border-radius:8px; font-family:'Nunito',sans-serif; font-size:18px; min-height:56px; outline:none; box-sizing:border-box;"
                                @focus="$event.target.style.borderColor='#E8553E'"
                                @blur="$event.target.style.borderColor='#F0E8E0'" />
                        </div>

                        <!-- Buttons -->
                        <button type="submit" :disabled="form.processing"
                            style="width:100%; padding:18px; background:#E8553E; color:#fff; border:none; border-radius:8px; font-family:'Nunito',sans-serif; font-size:20px; font-weight:bold; cursor:pointer; min-height:56px; margin-bottom:16px;"
                            :style="form.processing ? 'opacity:0.7;cursor:not-allowed;' : ''">
                            {{ form.processing ? 'Saving…' : 'Save & Continue →' }}
                        </button>
                        <div style="text-align:center;">
                            <button type="button" @click="submit(true)"
                                style="background:none; border:none; font-family:'Nunito',sans-serif; font-size:18px; color:#E8553E; cursor:pointer; text-decoration:underline; min-height:44px;">
                                Save for Later
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </TeacherLayout>
</template>
