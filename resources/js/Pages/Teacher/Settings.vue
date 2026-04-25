<script setup>
import TeacherLayout from '@/Layouts/TeacherLayout.vue';
import axios from 'axios';
import { router, usePage } from '@inertiajs/vue3';
import { computed, onMounted, reactive, ref } from 'vue';

defineOptions({ inheritAttrs: false });

const props = defineProps({
    preferences: {
        type: Object,
        default: () => ({ high_contrast: false }),
    },
});

const page = usePage();
const authUser = computed(() => page.props.auth?.user || {});

const profileForm = reactive({
    name: authUser.value?.name || '',
    email: authUser.value?.email || '',
    avatar: null,
});

const avatarPreview = ref(authUser.value?.avatar || null);
const profileSaving = ref(false);
const profileErrors = ref({});
const profileStatus = ref('');

const AVATAR_ALLOWED_TYPES = ['image/jpeg', 'image/jpg', 'image/png', 'image/webp'];
const AVATAR_MAX_SIZE_BYTES = 2 * 1024 * 1024;

const profileError = (field) => {
    const value = profileErrors.value?.[field];
    if (Array.isArray(value)) {
        return value[0] || '';
    }

    return typeof value === 'string' ? value : '';
};

const handleAvatarChange = (event) => {
    const file = event.target.files?.[0];
    if (!file) {
        return;
    }

    if (!AVATAR_ALLOWED_TYPES.includes(file.type)) {
        const message = 'Unsupported image type. Please upload JPG, PNG, or WEBP.';
        profileErrors.value = {
            ...profileErrors.value,
            avatar: [message],
        };
        profileForm.avatar = null;
        event.target.value = '';
        window.alert(message);
        return;
    }

    if (file.size > AVATAR_MAX_SIZE_BYTES) {
        const message = 'Image is too large. Maximum allowed size is 2 MB.';
        profileErrors.value = {
            ...profileErrors.value,
            avatar: [message],
        };
        profileForm.avatar = null;
        event.target.value = '';
        window.alert(message);
        return;
    }

    const nextErrors = { ...profileErrors.value };
    delete nextErrors.avatar;
    profileErrors.value = nextErrors;

    profileForm.avatar = file;
    avatarPreview.value = URL.createObjectURL(file);
};

const saveProfile = async () => {
    if (profileSaving.value) {
        return;
    }

    profileSaving.value = true;
    profileErrors.value = {};
    profileStatus.value = '';

    const payload = new FormData();
    payload.append('_method', 'PATCH');
    payload.append('name', profileForm.name || '');
    payload.append('email', profileForm.email || '');
    if (profileForm.avatar) {
        payload.append('avatar', profileForm.avatar);
    }

    try {
        const response = await axios.post(route('account.profile.update'), payload, {
            headers: {
                Accept: 'application/json',
            },
        });

        const updatedUser = response?.data?.user || null;
        if (updatedUser) {
            profileForm.name = updatedUser.name || profileForm.name;
            profileForm.email = updatedUser.email || profileForm.email;
            avatarPreview.value = updatedUser.avatar || avatarPreview.value;
        }

        profileForm.avatar = null;
        profileStatus.value = response?.data?.message || 'Profile updated successfully.';
        router.reload({ only: ['auth'] });
    } catch (error) {
        if (error.response?.status === 422) {
            profileErrors.value = error.response.data.errors || {};
        } else {
            profileErrors.value = {
                general: ['Unable to update profile right now. Please try again.'],
            };
        }
    } finally {
        profileSaving.value = false;
    }
};

const highContrast = ref(Boolean(props.preferences?.high_contrast));
const saving = ref(false);
const statusMessage = ref('');
const STORAGE_KEY = 'edubridge.teacher.high_contrast';

const applyContrastClass = (enabled) => {
    document.body.classList.toggle('high-contrast', enabled);
};

const persistLocalPreference = (enabled) => {
    try {
        localStorage.setItem(STORAGE_KEY, enabled ? '1' : '0');
    } catch {
        // Ignore storage failures.
    }
};

const persistPreference = async (enabled) => {
    saving.value = true;
    statusMessage.value = '';

    try {
        await axios.patch('/api/teacher/preferences', {
            high_contrast: enabled,
        });
        persistLocalPreference(enabled);
        statusMessage.value = enabled ? 'High contrast mode is enabled.' : 'High contrast mode is disabled.';
    } catch (error) {
        highContrast.value = !enabled;
        applyContrastClass(highContrast.value);
        persistLocalPreference(highContrast.value);
        statusMessage.value = error?.response?.data?.message || 'Unable to save preference.';
    } finally {
        saving.value = false;
    }
};

const handleToggle = (event) => {
    const enabled = event.target.checked;
    highContrast.value = enabled;
    applyContrastClass(enabled);
    persistPreference(enabled);
};

onMounted(() => {
    document.body.setAttribute('data-portal', 'teacher');
    const initial = Boolean(props.preferences?.high_contrast);
    highContrast.value = initial;
    applyContrastClass(initial);
    persistLocalPreference(initial);
});
</script>

<template>
    <TeacherLayout>
        <div class="teacher-settings-page">
            <div class="settings-panel">
                <p class="eyebrow">Account</p>
                <h1>Teacher Settings</h1>
                <p class="helper">
                    Update your account details and profile photo, then manage accessibility preferences for your teacher portal.
                </p>

                <form class="profile-card" @submit.prevent="saveProfile">
                    <div class="avatar-field">
                        <img
                            :src="avatarPreview || 'https://ui-avatars.com/api/?name=' + (profileForm.name || 'Teacher') + '&background=3D6B4F&color=fff'"
                            alt="Teacher avatar"
                            class="avatar-preview"
                        />
                        <label class="avatar-picker">
                            <span>Upload Photo</span>
                            <input type="file" accept="image/*" @change="handleAvatarChange" />
                        </label>
                    </div>

                    <div class="fields-grid">
                        <label class="field-block">
                            <span>Full Name</span>
                            <input v-model="profileForm.name" type="text" autocomplete="name" required />
                            <small v-if="profileError('name')" class="field-error">{{ profileError('name') }}</small>
                        </label>

                        <label class="field-block">
                            <span>Email</span>
                            <input v-model="profileForm.email" type="email" autocomplete="email" required />
                            <small v-if="profileError('email')" class="field-error">{{ profileError('email') }}</small>
                        </label>
                    </div>

                    <small v-if="profileError('avatar')" class="field-error">{{ profileError('avatar') }}</small>
                    <small v-if="profileError('general')" class="field-error">{{ profileError('general') }}</small>
                    <p v-if="profileStatus" class="success-message">{{ profileStatus }}</p>

                    <button type="submit" class="save-profile-btn" :disabled="profileSaving">
                        {{ profileSaving ? 'Saving Profile...' : 'Save Profile' }}
                    </button>
                </form>

                <p class="eyebrow section-space">Accessibility</p>
                <p class="helper compact-helper">
                    Turn on high contrast mode for easier reading and stronger separation between elements.
                </p>

                <div class="context-grid">
                    <article class="context-card">
                        <h2>When to enable</h2>
                        <p>Enable high contrast if text feels low-visibility, if you work for long hours, or if you teach from small screens.</p>
                    </article>
                    <article class="context-card">
                        <h2>What changes</h2>
                        <p>Buttons, outlines, and key labels become stronger so important actions and controls are easier to identify quickly.</p>
                    </article>
                </div>

                <div class="toggle-row">
                    <div>
                        <div class="toggle-label">🔆 High Contrast</div>
                        <div class="toggle-copy">Applies stronger colors and borders to improve readability.</div>
                    </div>
                    <label class="toggle-switch">
                        <input :checked="highContrast" type="checkbox" @change="handleToggle" />
                        <span class="toggle-track">
                            <span class="toggle-thumb" />
                        </span>
                    </label>
                </div>

                <p v-if="statusMessage" class="status-message">{{ statusMessage }}</p>
                <p v-if="saving" class="status-message">Saving preference...</p>
            </div>
        </div>
    </TeacherLayout>
</template>

<style scoped>
.teacher-settings-page {
    padding: 24px;
    min-height: 100vh;
    background: #FFF8F0;
}

.settings-panel {
    max-width: 760px;
    background: #fff;
    border: 1px solid #F0E8E0;
    border-radius: 20px;
    box-shadow: 0 2px 10px rgba(61, 107, 79, 0.08);
    padding: 28px;
}

.eyebrow {
    margin: 0 0 6px;
    font-size: 12px;
    font-weight: 700;
    letter-spacing: 0.14em;
    text-transform: uppercase;
    color: #6b7280;
}

h1 {
    margin: 0;
    font-family: 'Fredoka One', cursive;
    font-size: 30px;
    color: #E8553E;
}

.helper {
    margin: 14px 0 0;
    font-family: 'Nunito', sans-serif;
    font-size: 20px;
    color: #4b5563;
}

.compact-helper {
    margin-top: 8px;
    font-size: 17px;
}

.section-space {
    margin-top: 26px;
}

.profile-card {
    margin-top: 16px;
    border: 1px solid #F0E8E0;
    border-radius: 14px;
    background: #FFF8F0;
    padding: 16px;
}

.avatar-field {
    display: flex;
    align-items: center;
    gap: 14px;
}

.avatar-preview {
    width: 72px;
    height: 72px;
    border-radius: 999px;
    object-fit: cover;
    border: 3px solid #fff;
    box-shadow: 0 3px 12px rgba(15, 23, 42, 0.14);
}

.avatar-picker {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    min-height: 40px;
    border-radius: 999px;
    background: #E8553E;
    color: #fff;
    font-family: 'Nunito', sans-serif;
    font-size: 14px;
    font-weight: 700;
    padding: 0 14px;
    cursor: pointer;
}

.avatar-picker input {
    display: none;
}

.fields-grid {
    margin-top: 14px;
    display: grid;
    grid-template-columns: repeat(2, minmax(0, 1fr));
    gap: 12px;
}

.field-block {
    display: flex;
    flex-direction: column;
    gap: 6px;
    font-family: 'Nunito', sans-serif;
    font-size: 14px;
    font-weight: 700;
    color: #374151;
}

.field-block input {
    min-height: 42px;
    border: 1px solid #E5D8CF;
    border-radius: 10px;
    padding: 0 12px;
    font-family: 'Nunito', sans-serif;
    font-size: 15px;
}

.field-error {
    display: block;
    margin-top: 8px;
    font-family: 'Nunito', sans-serif;
    font-size: 13px;
    font-weight: 700;
    color: #b91c1c;
}

.success-message {
    margin: 10px 0 0;
    font-family: 'Nunito', sans-serif;
    font-size: 14px;
    font-weight: 700;
    color: #0f766e;
}

.save-profile-btn {
    margin-top: 12px;
    min-height: 42px;
    border: none;
    border-radius: 999px;
    background: #E8553E;
    color: #fff;
    font-family: 'Fredoka One', cursive;
    font-size: 16px;
    padding: 0 18px;
    cursor: pointer;
}

.save-profile-btn:disabled {
    opacity: 0.75;
    cursor: not-allowed;
}

.context-grid {
    margin-top: 18px;
    display: grid;
    grid-template-columns: repeat(2, minmax(0, 1fr));
    gap: 12px;
}

.context-card {
    border: 1px solid #F0E8E0;
    border-radius: 14px;
    padding: 14px;
    background: #FFF8F0;
}

.context-card h2 {
    margin: 0;
    font-family: 'Fredoka One', cursive;
    font-size: 16px;
    font-weight: 400;
    color: #E8553E;
}

.context-card p {
    margin: 8px 0 0;
    font-family: 'Nunito', sans-serif;
    font-size: 15px;
    line-height: 1.55;
    color: #4b5563;
}

.toggle-row {
    margin-top: 24px;
    padding: 20px;
    border-radius: 16px;
    border: 1px solid #F0E8E0;
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 20px;
}

.toggle-label {
    font-family: 'Fredoka One', cursive;
    font-size: 24px;
    color: #E8553E;
    font-weight: 700;
}

.toggle-copy {
    margin-top: 6px;
    font-family: 'Nunito', sans-serif;
    font-size: 18px;
    color: #6b7280;
}

.toggle-switch {
    display: inline-flex;
    align-items: center;
    cursor: pointer;
}

.toggle-switch input {
    position: absolute;
    opacity: 0;
    pointer-events: none;
}

.toggle-track {
    width: 88px;
    height: 44px;
    border-radius: 999px;
    background: #d1d5db;
    display: inline-flex;
    align-items: center;
    padding: 4px;
    transition: background-color 0.2s ease;
}

.toggle-thumb {
    width: 36px;
    height: 36px;
    border-radius: 999px;
    background: #fff;
    box-shadow: 0 4px 10px rgba(15, 23, 42, 0.14);
    transition: transform 0.2s ease;
}

.toggle-switch input:checked + .toggle-track {
    background: #E8553E;
}

.toggle-switch input:checked + .toggle-track .toggle-thumb {
    transform: translateX(44px);
}

.status-message {
    margin: 14px 0 0;
    font-family: 'Nunito', sans-serif;
    font-size: 18px;
    color: #E8553E;
}

@media (max-width: 720px) {
    .fields-grid {
        grid-template-columns: 1fr;
    }

    .context-grid {
        grid-template-columns: 1fr;
    }

    .toggle-row {
        flex-direction: column;
        align-items: flex-start;
    }
}
</style>
