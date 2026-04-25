<script setup>
import TeacherLayout from '@/Layouts/TeacherLayout.vue';
import { CameraIcon } from '@heroicons/vue/24/outline';
import { useForm, router } from '@inertiajs/vue3';
import { computed, onMounted, ref } from 'vue';

onMounted(() => { document.body.setAttribute('data-portal', 'teacher'); });

const props = defineProps({
    profile: { type: Object, default: () => ({}) },
});

const form = useForm({
    avatar:         null,
    degree:         null,
    service_record: null,
    id_proof:       null,
});

const avatarPreview = ref(props.profile?.avatar || null);
const degreeFile     = ref(null);
const serviceFile    = ref(null);
const idFile         = ref(null);
const avatarClientError = ref('');

const AVATAR_ALLOWED_TYPES = ['image/jpeg', 'image/jpg', 'image/png', 'image/webp'];
const AVATAR_MAX_SIZE_BYTES = 2 * 1024 * 1024;

const topError = computed(() => {
    if (avatarClientError.value) {
        return avatarClientError.value;
    }

    return form.errors.degree
        || form.errors.service_record
        || form.errors.id_proof
        || form.errors.avatar
        || '';
});

const avatarRingRadius = 58;
const avatarRingCircumference = 2 * Math.PI * avatarRingRadius;

const uploadPercent = computed(() => Number(form.progress?.percentage || 0));
const avatarRingOffset = computed(() => {
    const progress = Math.max(0, Math.min(100, uploadPercent.value));
    return avatarRingCircumference - (progress / 100) * avatarRingCircumference;
});

const handlePhoto = (e) => {
    const f = e.target.files?.[0];
    if (!f) {
        return;
    }

    if (!AVATAR_ALLOWED_TYPES.includes(f.type)) {
        avatarClientError.value = 'Unsupported image type. Please upload JPG, PNG, or WEBP.';
        form.avatar = null;
        e.target.value = '';
        window.alert(avatarClientError.value);
        return;
    }

    if (f.size > AVATAR_MAX_SIZE_BYTES) {
        avatarClientError.value = 'Image is too large. Maximum allowed size is 2 MB.';
        form.avatar = null;
        e.target.value = '';
        window.alert(avatarClientError.value);
        return;
    }

    avatarClientError.value = '';
    form.clearErrors('avatar');
    form.avatar = f;
    avatarPreview.value = URL.createObjectURL(f);
};

const handleDoc = (type, e) => {
    const f = e.target.files[0];
    if (!f) return;
    if (type === 'degree')         { form.degree = f;         degreeFile.value  = f.name; }
    if (type === 'service_record') { form.service_record = f; serviceFile.value = f.name; }
    if (type === 'id_proof')       { form.id_proof = f;       idFile.value      = f.name; }
};

const fieldError = (field) => form.errors[field] || '';

const submit = () => {
    form.post(route('teacher.profile.step5.save'), {
        forceFormData: true,
        preserveScroll: true,
        onError: () => {
            window.scrollTo({ top: 0, behavior: 'smooth' });
        },
        onSuccess: () => {
            router.visit(route('teacher.dashboard'));
        },
    });
};
</script>

<template>
    <TeacherLayout>
        <div style="padding:36px; background:#FFF8F0; min-height:100vh;">
            <div style="max-width:700px; margin:0 auto;">
                <div style="font-family:'Fredoka One',cursive; font-size:26px; color:#E8553E; margin-bottom:8px;">Step 5 of 5</div>
                <div style="display:flex; gap:8px; margin-bottom:36px;">
                    <div v-for="i in 5" :key="i" :style="`flex:1; height:6px; border-radius:3px; background:#E8553E;`"></div>
                </div>
                <h2 style="font-family:'Fredoka One',cursive; font-size:28px; color:#E8553E; margin-bottom:28px;">Photo & Documents</h2>

                <div style="background:#fff; border:1px solid #F0E8E0; border-radius:10px; padding:32px;">
                    <div v-if="topError" role="alert"
                        style="color:#b91c1c; background:#fff1f2; border:1px solid #fecaca; font-size:16px; margin-bottom:16px; padding:10px 12px; border-radius:8px;">
                        {{ topError }}
                    </div>

                    <!-- Profile Photo -->
                    <div style="text-align:center; margin-bottom:36px;">
                        <div style="font-family:'Fredoka One',cursive; font-size:22px; color:#E8553E; margin-bottom:16px;">Profile Photo</div>
                        <div class="teacher-avatar-upload-shell">
                            <img
                                :src="avatarPreview || 'https://ui-avatars.com/api/?name=Teacher&background=3D6B4F&color=fff'"
                                class="teacher-avatar-image"
                                width="120"
                                height="120"
                                loading="lazy"
                            />

                            <svg v-if="form.progress" class="teacher-avatar-progress" viewBox="0 0 134 134" aria-hidden="true">
                                <circle class="teacher-ring-track" cx="67" cy="67" :r="avatarRingRadius" />
                                <circle
                                    class="teacher-ring-progress"
                                    cx="67"
                                    cy="67"
                                    :r="avatarRingRadius"
                                    :stroke-dasharray="avatarRingCircumference"
                                    :stroke-dashoffset="avatarRingOffset"
                                />
                            </svg>

                            <label class="teacher-avatar-overlay" aria-label="Upload teacher profile photo">
                                <span class="teacher-camera-wrap">
                                    <CameraIcon class="teacher-camera-icon" aria-hidden="true" />
                                </span>
                                <input type="file" accept="image/*" @change="handlePhoto" />
                            </label>
                        </div>

                        <label style="display:inline-block; padding:10px 18px; background:#E8553E; color:#fff; border-radius:8px; font-family:'Nunito',sans-serif; font-size:16px; font-weight:bold; margin-top:12px; cursor:pointer;">
                            Choose Photo
                            <input type="file" accept="image/*" style="display:none;" @change="handlePhoto" />
                        </label>
                        <div style="font-family:'Nunito',sans-serif; font-size:16px; color:#aaa; margin-top:8px;">Max 2MB. Will be resized to 300×300.</div>
                    </div>

                    <!-- Documents -->
                    <div style="font-family:'Fredoka One',cursive; font-size:22px; color:#E8553E; margin-bottom:20px;">Verification Documents</div>

                    <template v-for="(doc, i) in [
                        { key:'degree', label:'Your Degree Certificate (PDF or image)', file: degreeFile },
                        { key:'service_record', label:'Service Record / Experience Letter', file: serviceFile },
                        { key:'id_proof', label:'ID Proof (Aadhaar / PAN)', file: idFile },
                    ]" :key="i">
                        <label :style="`display:block; border:2px dashed ${fieldError(doc.key) ? '#dc2626' : doc.file ? '#E8553E' : '#F0E8E0'}; background:${doc.file ? '#FFF3EF' : '#FFF8F0'}; border-radius:10px; padding:24px 20px; margin-bottom:8px; cursor:pointer; min-height:80px;`">
                            <div style="display:flex; align-items:center; justify-content:space-between;">
                                <div>
                                    <div style="font-family:'Nunito',sans-serif; font-size:18px; font-weight:bold; color:#333;">{{ doc.label }}</div>
                                    <div v-if="doc.file" style="font-family:'Nunito',sans-serif; font-size:16px; color:#E8553E; margin-top:4px;">✓ {{ doc.file }}</div>
                                    <div v-else style="font-family:'Nunito',sans-serif; font-size:16px; color:#aaa; margin-top:4px;">Click to upload</div>
                                </div>
                                <div style="font-size:28px;">{{ doc.file ? '✅' : '📄' }}</div>
                            </div>
                            <input type="file" accept=".pdf,.jpg,.jpeg,.png,.webp" style="display:none;" @change="handleDoc(doc.key, $event)" />
                        </label>

                        <div v-if="fieldError(doc.key)" role="alert" style="color:#b91c1c; font-size:15px; margin-bottom:12px;">
                            {{ fieldError(doc.key) }}
                        </div>
                    </template>

                    <!-- Submit for verification -->
                    <button type="button" @click="submit" :disabled="form.processing"
                        style="width:100%; padding:18px; background:#E8553E; color:#fff; border:none; border-radius:8px; font-family:'Nunito',sans-serif; font-size:20px; font-weight:bold; cursor:pointer; min-height:56px; margin-top:16px; margin-bottom:16px;">
                        {{ form.processing ? 'Submitting...' : 'Submit for Verification ✓' }}
                    </button>
                    <p style="font-family:'Nunito',sans-serif; font-size:18px; color:#888; text-align:center; margin:0;">
                        Our team will review and verify your profile within 24 hours.
                    </p>
                </div>
            </div>
        </div>
    </TeacherLayout>
</template>

<style scoped>
.teacher-avatar-upload-shell {
    position: relative;
    width: 120px;
    height: 120px;
    margin: 0 auto;
}

.teacher-avatar-image {
    width: 100%;
    height: 100%;
    border-radius: 50%;
    object-fit: cover;
    border: 3px solid #F0E8E0;
    display: block;
}

.teacher-avatar-overlay {
    position: absolute;
    inset: 0;
    border-radius: 50%;
    background: rgba(24, 50, 36, 0.42);
    display: flex;
    align-items: center;
    justify-content: center;
    opacity: 0;
    cursor: pointer;
    transition: opacity 200ms ease;
}

.teacher-avatar-overlay input {
    display: none;
}

.teacher-camera-wrap {
    transform: scale(0.5);
    transition: transform 200ms ease;
}

.teacher-camera-icon {
    width: 24px;
    height: 24px;
    color: #fff;
}

.teacher-avatar-upload-shell:hover .teacher-avatar-overlay {
    opacity: 1;
}

.teacher-avatar-upload-shell:hover .teacher-camera-wrap {
    transform: scale(1);
}

.teacher-avatar-progress {
    position: absolute;
    inset: -7px;
    width: 134px;
    height: 134px;
    transform: rotate(-90deg);
    pointer-events: none;
}

.teacher-ring-track,
.teacher-ring-progress {
    fill: none;
    stroke-width: 4;
}

.teacher-ring-track {
    stroke: rgba(61, 107, 79, 0.2);
}

.teacher-ring-progress {
    stroke: #E8553E;
    stroke-linecap: round;
    transition: stroke-dashoffset 180ms linear;
}
</style>
