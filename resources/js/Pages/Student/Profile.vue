<script setup>
import StudentLayout from '@/Layouts/StudentLayout.vue';
import axios from 'axios';
import { CameraIcon } from '@heroicons/vue/24/outline';
import { router } from '@inertiajs/vue3';
import { computed, onMounted, reactive, ref } from 'vue';

defineOptions({ inheritAttrs: false });

onMounted(() => {
    document.body.setAttribute('data-portal', 'student');
});

const props = defineProps({
    profile:     { type: Object, default: () => ({}) },
    user:        { type: Object, default: () => ({}) },
});

const grades = [
    'Class 1','Class 2','Class 3','Class 4','Class 5','Class 6',
    'Class 7','Class 8','Class 9','Class 10','Class 11','Class 12',
    'Undergraduate','Postgraduate'
];

const subjects = ['Math','Science','English','History','Geography','Physics',
    'Chemistry','Biology','Hindi','Punjabi','Computer Science','Economics','Commerce','Other'];

const languages = ['English','Hindi','Punjabi','Bengali','Tamil','Telugu','Marathi','Gujarati'];

const AVATAR_ALLOWED_TYPES = ['image/jpeg', 'image/jpg', 'image/png', 'image/webp'];
const AVATAR_MAX_SIZE_BYTES = 2 * 1024 * 1024;

const normalizeChoice = (value, options) => {
    if (typeof value !== 'string') {
        return '';
    }

    const normalized = value.trim();
    return options.includes(normalized) ? normalized : '';
};

const normalizeSubjects = (value) => {
    if (!Array.isArray(value)) {
        return [];
    }

    return [...new Set(
        value
            .map((subject) => (typeof subject === 'string' ? subject.trim() : ''))
            .filter((subject) => subjects.includes(subject))
    )];
};

const form = reactive({
    name:               props.user?.name || '',
    email:              props.user?.email || '',
    class_grade:        normalizeChoice(props.profile?.class_grade, grades),
    school_name:        props.profile?.school_name || '',
    subjects_needed:    normalizeSubjects(props.profile?.subjects_needed),
    preferred_language: normalizeChoice(props.profile?.preferred_language, languages),
    avatar:             null,
});

const successMsg = ref('');
const errors = ref({});
const processing = ref(false);
const avatarPreview = ref(props.user?.avatar || null);
const uploadProgress = ref(null);

const avatarRingRadius = 52;
const avatarRingCircumference = 2 * Math.PI * avatarRingRadius;

const avatarRingOffset = computed(() => {
    const progress = Math.max(0, Math.min(100, Number(uploadProgress.value ?? 0)));
    return avatarRingCircumference - (progress / 100) * avatarRingCircumference;
});

const fieldError = (field) => {
    const errorValue = errors.value?.[field];

    if (Array.isArray(errorValue)) {
        return errorValue[0] || '';
    }

    return typeof errorValue === 'string' ? errorValue : '';
};

const subjectsError = computed(() => {
    const directError = fieldError('subjects_needed');
    if (directError) {
        return directError;
    }

    const nestedEntry = Object.entries(errors.value || {}).find(([key]) => key.startsWith('subjects_needed.'));
    if (!nestedEntry) {
        return '';
    }

    const nestedError = nestedEntry[1];
    if (Array.isArray(nestedError)) {
        return nestedError[0] || '';
    }

    return typeof nestedError === 'string' ? nestedError : '';
});

const toggleSubject = (s) => {
    const idx = form.subjects_needed.indexOf(s);
    if (idx > -1) form.subjects_needed.splice(idx, 1);
    else form.subjects_needed.push(s);
};

const handleAvatarChange = (e) => {
    const file = e.target.files?.[0];
    if (!file) {
        return;
    }

    if (!AVATAR_ALLOWED_TYPES.includes(file.type)) {
        const message = 'Unsupported image type. Please upload JPG, PNG, or WEBP.';
        errors.value = {
            ...errors.value,
            avatar: [message],
        };
        form.avatar = null;
        e.target.value = '';
        window.alert(message);
        return;
    }

    if (file.size > AVATAR_MAX_SIZE_BYTES) {
        const message = 'Image is too large. Maximum allowed size is 2 MB.';
        errors.value = {
            ...errors.value,
            avatar: [message],
        };
        form.avatar = null;
        e.target.value = '';
        window.alert(message);
        return;
    }

    const nextErrors = { ...errors.value };
    delete nextErrors.avatar;
    errors.value = nextErrors;

    form.avatar = file;
    avatarPreview.value = URL.createObjectURL(file);
    uploadProgress.value = null;
};

const submit = async () => {
    processing.value = true;
    errors.value = {};
    uploadProgress.value = form.avatar ? 0 : null;
    const hadAvatar = Boolean(form.avatar);

    const cleanedSubjects = normalizeSubjects(form.subjects_needed);
    form.subjects_needed = [...cleanedSubjects];

    const payload = new FormData();
    payload.append('_method', 'PATCH');
    payload.append('name', form.name || '');
    payload.append('email', form.email || '');
    payload.append('class_grade', normalizeChoice(form.class_grade, grades));
    payload.append('school_name', form.school_name || '');
    payload.append('preferred_language', normalizeChoice(form.preferred_language, languages));
    cleanedSubjects.forEach((subject) => payload.append('subjects_needed[]', subject));

    if (form.avatar) {
        payload.append('avatar', form.avatar);
    }

    try {
        await axios.post(route('account.profile.update'), payload, {
            headers: {
                'Accept': 'application/json',
            },
            onUploadProgress: (event) => {
                if (!event.total) {
                    return;
                }

                uploadProgress.value = Math.round((event.loaded / event.total) * 100);
            },
        });

        successMsg.value = 'Profile saved successfully! 🎉';
        form.avatar = null;
        router.reload({ only: ['auth'] });
        setTimeout(() => {
            successMsg.value = '';
        }, 4000);
    } catch (error) {
        if (error.response?.status === 422) {
            errors.value = error.response.data.errors || {};
        } else {
            errors.value = {
                general: ['Unable to save profile right now. Please try again.'],
            };
        }
    } finally {
        processing.value = false;
        if (hadAvatar) {
            window.setTimeout(() => {
                uploadProgress.value = null;
            }, 500);
        }
    }
};
</script>

<template>
    <StudentLayout>
        <div style="padding:32px; background:#FFF8F0; min-height:100vh;">
            <div style="max-width:680px; margin:0 auto;">
                <!-- Header -->
                <div style="background:linear-gradient(135deg,#E8553E,#FFAB76); border-radius:20px; padding:28px 32px; margin-bottom:28px; text-align:center;">
                    <!-- Avatar -->
                    <div class="avatar-upload-shell">
                        <img :src="avatarPreview || 'https://ui-avatars.com/api/?name=' + (user?.name||'U') + '&background=E8553E&color=fff'"
                            class="profile-avatar"
                            width="100"
                            height="100"
                            loading="lazy"
                        />

                        <svg v-if="uploadProgress !== null" class="avatar-progress-ring" viewBox="0 0 120 120" aria-hidden="true">
                            <circle class="ring-track" cx="60" cy="60" :r="avatarRingRadius" />
                            <circle
                                class="ring-progress"
                                cx="60"
                                cy="60"
                                :r="avatarRingRadius"
                                :stroke-dasharray="avatarRingCircumference"
                                :stroke-dashoffset="avatarRingOffset"
                            />
                        </svg>

                        <label class="avatar-upload-overlay" aria-label="Upload profile photo">
                            <span class="camera-icon-wrap">
                                <CameraIcon class="camera-icon" aria-hidden="true" />
                            </span>
                            <input type="file" accept="image/*" @change="handleAvatarChange" />
                        </label>
                    </div>
                    <h1 style="font-family:'Fredoka One',cursive; font-size:24px; color:#fff; margin:0;">My Profile</h1>
                    <div v-if="fieldError('avatar')" style="margin-top:10px; color:#fff1f2; font-family:Nunito,sans-serif; font-size:14px; font-weight:700;">
                        {{ fieldError('avatar') }}
                    </div>
                </div>

                <!-- Success toast -->
                <div v-if="successMsg" style="background:#4CB87E; color:#fff; border-radius:999px; padding:14px 24px; text-align:center; font-family:Nunito,sans-serif; font-size:16px; font-weight:700; margin-bottom:20px;">
                    {{ successMsg }}
                </div>

                <div v-if="fieldError('general')" style="background:#FFF1EC; color:#B93823; border-radius:14px; padding:12px 16px; margin-bottom:16px; font-family:Nunito,sans-serif; font-size:14px; font-weight:700;">
                    {{ fieldError('general') }}
                </div>

                <!-- Form Card -->
                <div style="background:#fff; border-radius:20px; padding:32px; box-shadow:0 4px 20px rgba(232,85,62,0.08);">
                    <form @submit.prevent="submit">
                        <!-- Name -->
                        <div style="margin-bottom:20px;">
                            <label style="font-family:Nunito,sans-serif; font-size:14px; font-weight:700; color:#555; display:block; margin-bottom:8px;">Full Name</label>
                            <input v-model="form.name" type="text"
                                style="width:100%; padding:12px 16px; border:2px solid #F0DDD5; border-radius:999px; font-family:Nunito,sans-serif; font-size:16px; outline:none; box-sizing:border-box;"
                                @focus="$event.target.style.borderColor='#E8553E'"
                                @blur="$event.target.style.borderColor='#F0DDD5'" />
                            <div v-if="fieldError('name')" style="margin-top:6px; color:#E8553E; font-family:Nunito,sans-serif; font-size:14px;">
                                {{ fieldError('name') }}
                            </div>
                        </div>

                        <!-- Class/Grade -->
                        <div style="margin-bottom:20px;">
                            <label style="font-family:Nunito,sans-serif; font-size:14px; font-weight:700; color:#555; display:block; margin-bottom:8px;">Class / Grade</label>
                            <select v-model="form.class_grade"
                                style="width:100%; padding:12px 16px; border:2px solid #F0DDD5; border-radius:999px; font-family:Nunito,sans-serif; font-size:16px; outline:none; box-sizing:border-box; background:#fff; appearance:none;">
                                <option value="">Select your class</option>
                                <option v-for="g in grades" :key="g" :value="g">{{ g }}</option>
                            </select>
                            <div v-if="fieldError('class_grade')" style="margin-top:6px; color:#E8553E; font-family:Nunito,sans-serif; font-size:14px;">
                                {{ fieldError('class_grade') }}
                            </div>
                        </div>

                        <!-- School -->
                        <div style="margin-bottom:20px;">
                            <label style="font-family:Nunito,sans-serif; font-size:14px; font-weight:700; color:#555; display:block; margin-bottom:8px;">School / College Name</label>
                            <input v-model="form.school_name" type="text"
                                style="width:100%; padding:12px 16px; border:2px solid #F0DDD5; border-radius:999px; font-family:Nunito,sans-serif; font-size:16px; outline:none; box-sizing:border-box;"
                                @focus="$event.target.style.borderColor='#E8553E'"
                                @blur="$event.target.style.borderColor='#F0DDD5'" />
                            <div v-if="fieldError('school_name')" style="margin-top:6px; color:#E8553E; font-family:Nunito,sans-serif; font-size:14px;">
                                {{ fieldError('school_name') }}
                            </div>
                        </div>

                        <!-- Subjects Needed -->
                        <div style="margin-bottom:20px;">
                            <label style="font-family:Nunito,sans-serif; font-size:14px; font-weight:700; color:#555; display:block; margin-bottom:12px;">Subjects I Need Help With</label>
                            <div style="display:flex; flex-wrap:wrap; gap:8px;">
                                <button v-for="s in subjects" :key="s" type="button" @click="toggleSubject(s)"
                                    :style="`padding:8px 16px; border-radius:999px; font-family:Nunito,sans-serif; font-size:14px; font-weight:600; cursor:pointer; transition:all 0.15s; border:2px solid ${form.subjects_needed.includes(s) ? '#E8553E' : '#F0DDD5'}; background:${form.subjects_needed.includes(s) ? '#FFF3EF' : '#fff'}; color:${form.subjects_needed.includes(s) ? '#E8553E' : '#888'};`">
                                    {{ s }}
                                </button>
                            </div>
                            <div v-if="subjectsError" style="margin-top:6px; color:#E8553E; font-family:Nunito,sans-serif; font-size:14px;">
                                {{ subjectsError }}
                            </div>
                        </div>

                        <!-- Preferred Language -->
                        <div style="margin-bottom:28px;">
                            <label style="font-family:Nunito,sans-serif; font-size:14px; font-weight:700; color:#555; display:block; margin-bottom:8px;">Preferred Teaching Language</label>
                            <select v-model="form.preferred_language"
                                style="width:100%; padding:12px 16px; border:2px solid #F0DDD5; border-radius:999px; font-family:Nunito,sans-serif; font-size:16px; outline:none; box-sizing:border-box; background:#fff; appearance:none;">
                                <option value="">Select language</option>
                                <option v-for="l in languages" :key="l" :value="l">{{ l }}</option>
                            </select>
                            <div v-if="fieldError('preferred_language')" style="margin-top:6px; color:#E8553E; font-family:Nunito,sans-serif; font-size:14px;">
                                {{ fieldError('preferred_language') }}
                            </div>
                        </div>

                        <button type="submit" :disabled="processing"
                            style="width:100%; padding:16px; background:#E8553E; color:#fff; border:none; border-radius:999px; font-family:'Fredoka One',cursive; font-size:20px; cursor:pointer;">
                            {{ processing ? 'Saving...' : 'Save Profile 💾' }}
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </StudentLayout>
</template>

<style scoped>
.avatar-upload-shell {
    position: relative;
    width: 100px;
    height: 100px;
    margin: 0 auto 12px;
}

.profile-avatar {
    width: 100%;
    height: 100%;
    border-radius: 50%;
    object-fit: cover;
    border: 4px solid #fff;
    display: block;
}

.avatar-upload-overlay {
    position: absolute;
    inset: 0;
    border-radius: 50%;
    background: rgba(15, 23, 42, 0.45);
    display: flex;
    align-items: center;
    justify-content: center;
    opacity: 0;
    cursor: pointer;
    transition: opacity 200ms ease;
}

.avatar-upload-overlay input {
    display: none;
}

.camera-icon-wrap {
    transform: scale(0.5);
    transition: transform 200ms ease;
}

.camera-icon {
    width: 24px;
    height: 24px;
    color: #fff;
}

.avatar-upload-shell:hover .avatar-upload-overlay {
    opacity: 1;
}

.avatar-upload-shell:hover .camera-icon-wrap {
    transform: scale(1);
}

.avatar-progress-ring {
    position: absolute;
    inset: -6px;
    width: 112px;
    height: 112px;
    transform: rotate(-90deg);
    pointer-events: none;
}

.ring-track,
.ring-progress {
    fill: none;
    stroke-width: 4;
}

.ring-track {
    stroke: rgba(255, 255, 255, 0.28);
}

.ring-progress {
    stroke: #ffffff;
    stroke-linecap: round;
    transition: stroke-dashoffset 160ms linear;
}
</style>
