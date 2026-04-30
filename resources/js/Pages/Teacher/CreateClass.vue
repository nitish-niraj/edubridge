<script setup>
import TeacherLayout from '@/Layouts/TeacherLayout.vue';
import { Head, Link } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import axios from 'axios';
import { validateRequired, validateLength, validateSelect, validateNumberRange, charCount } from '@/composables/useFormValidation';

const form = ref({
    name: '',
    subject: '',
    description: '',
    max_students: 30,
});

const subjects = ['Mathematics', 'Science', 'English', 'Hindi', 'Social Studies', 'Computer Science', 'Physics', 'Chemistry', 'Biology', 'Economics', 'Accounting', 'History', 'Geography', 'Art', 'Music', 'Other'];

const saving  = ref(false);
const success  = ref(null);
const error    = ref(null);

// — Field validation state —
const fieldErrors = ref({ name: '', subject: '', description: '', max_students: '' });

const blurName        = () => { fieldErrors.value.name        = (validateRequired(form.value.name, 'Class name').error || validateLength(form.value.name, 3, 100, 'Class name').error) || ''; };
const blurSubject     = () => { fieldErrors.value.subject     = validateSelect(form.value.subject, 'Please select a subject.').error || ''; };
const blurDescription = () => { fieldErrors.value.description = validateLength(form.value.description, 0, 2000, 'Description').error || ''; };
const blurStudents    = () => { fieldErrors.value.max_students = validateNumberRange(form.value.max_students, 2, 50, 'Max students').error || ''; };

// Char counter for description
const descCharCount = computed(() => charCount(form.value.description, 2000));

const validateAll = () => {
    blurName(); blurSubject(); blurDescription(); blurStudents();
    return !Object.values(fieldErrors.value).some(Boolean);
};

const submit = async () => {
    if (!validateAll()) return;
    error.value = null;
    saving.value = true;
    try {
        const { data } = await axios.post('/api/groups', form.value);
        success.value = data;
    } catch (e) {
        error.value = e.response?.data?.message || 'Failed to create class.';
    } finally {
        saving.value = false;
    }
};

const copied = ref(false);
const copyLink = () => {
    navigator.clipboard.writeText(success.value.invite_link);
    copied.value = true;
    setTimeout(() => copied.value = false, 2000);
};
</script>

<template>
    <TeacherLayout>
        <div style="max-width: 700px; margin: 0 auto; padding: 48px 24px;">

            <!-- Success state -->
            <div v-if="success" style="text-align: center;">
                <div style="font-size: 64px; margin-bottom: 16px;">🎉</div>
                <h1 style="font-family: 'Fredoka One', cursive; font-size: 28px; color: #E8553E; margin-bottom: 8px;">Class Created!</h1>
                <p style="font-family: 'Nunito', sans-serif; font-size: 16px; color: #777; margin-bottom: 24px;">Share the link below with your students to join.</p>

                <div style="background: #FFF3EF; border-radius: 14px; padding: 20px 24px; margin-bottom: 24px; word-break: break-all; font-family: monospace; font-size: 16px; color: #333;">
                    {{ success.invite_link }}
                </div>

                <button @click="copyLink"
                    style="width: 100%; padding: 16px; border: none; border-radius: 14px; background: #E8553E; color: #fff; font-family: 'Fredoka One', cursive; font-size: 18px; font-weight: bold; cursor: pointer;">
                    {{ copied ? '✓ Copied!' : '📋 Copy Invite Link' }}
                </button>

                <div style="display: flex; gap: 12px; margin-top: 16px;">
                    <Link :href="route('teacher.classes.manage', { id: success.conversation.id })"
                        style="flex: 1; display: block; text-align: center; padding: 14px; border: 2px solid #E8553E; border-radius: 14px; color: #E8553E; text-decoration: none; font-family: 'Fredoka One', cursive; font-weight: bold;">
                        Manage Class
                    </Link>
                    <button @click="success = null; form.name = ''; form.description = ''"
                        style="flex: 1; padding: 14px; border: 2px solid #F0E8E0; border-radius: 14px; background: #fff; color: #555; cursor: pointer; font-family: 'Nunito', sans-serif;">
                        Create Another
                    </button>
                </div>
            </div>

            <!-- Create form -->
            <div v-else>
                <h1 style="font-family: 'Fredoka One', cursive; font-size: 26px; color: #E8553E; margin-bottom: 8px;">➕ Create a New Class</h1>
                <p style="font-family: 'Nunito', sans-serif; font-size: 16px; color: #777; margin-bottom: 32px;">
                    Create a persistent class group to chat, share announcements, and host group video sessions.
                </p>

                <div v-if="error"
                    style="background: #FFEBEE; color: #C62828; padding: 14px 20px; border-radius: 12px; margin-bottom: 20px; font-family: 'Nunito', sans-serif;">
                    {{ error }}
                </div>

                <form @submit.prevent="submit" novalidate>
                <!-- Class Name -->
                <div style="margin-bottom:20px;">
                    <label for="cc-name" style="display:block; font-family:'Fredoka One',cursive; font-size:15px; color:#333; margin-bottom:6px;">
                        Class Name <span style="color:#E8553E;">*</span>
                    </label>
                    <input id="cc-name" v-model="form.name" type="text" placeholder="e.g. Class 12 Math — Batch A"
                        required minlength="3" maxlength="100"
                        :aria-invalid="fieldErrors.name ? 'true' : 'false'"
                        aria-describedby="cc-name-error"
                        style="width:100%; height:56px; padding:0 16px; border:2px solid #F0E8E0; border-radius:12px; font-family:'Nunito',sans-serif; font-size:16px; color:#333; box-sizing:border-box;"
                        @blur="blurName"
                        @focus="$event.target.style.borderColor='#E8553E'"
                    />
                    <div id="cc-name-error" role="alert" style="color:#c0392b; font-size:14px; margin-top:4px; min-height:18px;">{{ fieldErrors.name }}</div>
                </div>

                <!-- Subject -->
                <div style="margin-bottom:20px;">
                    <label for="cc-subject" style="display:block; font-family:'Fredoka One',cursive; font-size:15px; color:#333; margin-bottom:6px;">
                        Subject <span style="color:#E8553E;">*</span>
                    </label>
                    <select id="cc-subject" v-model="form.subject" required
                        :aria-invalid="fieldErrors.subject ? 'true' : 'false'"
                        aria-describedby="cc-subject-error"
                        style="width:100%; height:56px; padding:0 16px; border:2px solid #F0E8E0; border-radius:12px; font-family:'Nunito',sans-serif; font-size:16px; color:#333; background:#fff;"
                        @change="blurSubject"
                    >
                        <option value="" disabled>Select subject...</option>
                        <option v-for="s in subjects" :key="s" :value="s">{{ s }}</option>
                    </select>
                    <div id="cc-subject-error" role="alert" style="color:#c0392b; font-size:14px; margin-top:4px; min-height:18px;">{{ fieldErrors.subject }}</div>
                </div>

                <!-- Description -->
                <div style="margin-bottom:20px;">
                    <label for="cc-desc" style="display:block; font-family:'Fredoka One',cursive; font-size:15px; color:#333; margin-bottom:6px;">Description (optional)</label>
                    <textarea id="cc-desc" v-model="form.description" rows="4" placeholder="Describe the class..."
                        maxlength="2000"
                        :aria-invalid="fieldErrors.description ? 'true' : 'false'"
                        aria-describedby="cc-desc-counter"
                        style="width:100%; padding:14px 16px; border:2px solid #F0E8E0; border-radius:12px; font-family:'Nunito',sans-serif; font-size:16px; color:#333; resize:vertical; box-sizing:border-box;"
                        @blur="blurDescription"
                    />
                    <div id="cc-desc-counter" style="text-align:right; font-size:13px; color:#aaa; margin-top:2px;">{{ descCharCount }}</div>
                    <div v-if="fieldErrors.description" role="alert" style="color:#c0392b; font-size:14px;">{{ fieldErrors.description }}</div>
                </div>

                <!-- Max Students -->
                <div style="margin-bottom:32px;">
                    <label for="cc-max" style="display:block; font-family:'Fredoka One',cursive; font-size:15px; color:#333; margin-bottom:6px;">
                        Max Students
                    </label>
                    <input id="cc-max" v-model.number="form.max_students" type="number" min="2" max="50"
                        :aria-invalid="fieldErrors.max_students ? 'true' : 'false'"
                        aria-describedby="cc-max-error"
                        style="width:120px; height:56px; padding:0 16px; border:2px solid #F0E8E0; border-radius:12px; font-family:'Nunito',sans-serif; font-size:16px; color:#333; text-align:center;"
                        @blur="blurStudents"
                        @focus="$event.target.style.borderColor='#E8553E'"
                    />
                    <div id="cc-max-error" role="alert" style="color:#c0392b; font-size:14px; margin-top:4px; min-height:18px;">{{ fieldErrors.max_students }}</div>
                </div>

                <button type="submit" :disabled="saving"
                    style="width:100%; padding:16px; border:none; border-radius:14px; background:#E8553E; color:#fff; font-family:'Fredoka One',cursive; font-size:18px; font-weight:bold; cursor:pointer;"
                    :style="{ opacity: saving ? 0.5 : 1, cursor: saving ? 'not-allowed' : 'pointer' }">
                    {{ saving ? 'Creating…' : 'Create Class' }}
                </button>
                </form>
            </div>
        </div>
    </TeacherLayout>
</template>
