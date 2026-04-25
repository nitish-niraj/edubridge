<script setup>
import AdminLayout from '@/Layouts/AdminLayout.vue';
import axios from 'axios';
import { router, usePage } from '@inertiajs/vue3';
import { computed, reactive, ref } from 'vue';

defineOptions({ inheritAttrs: false });

const props = defineProps({
    two_factor: {
        type: Object,
        default: () => ({
            enabled: false,
            secret_preview: null,
            manual_key: null,
            qr_svg: null,
        }),
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
    } catch (requestError) {
        if (requestError.response?.status === 422) {
            profileErrors.value = requestError.response.data.errors || {};
        } else {
            profileErrors.value = {
                general: ['Unable to update profile right now. Please try again.'],
            };
        }
    } finally {
        profileSaving.value = false;
    }
};

const code = ref('');
const busy = ref(false);
const disabling = ref(false);
const success = ref('');
const error = ref('');

const isEnabled = computed(() => Boolean(props.two_factor?.enabled));

const enableTwoFactor = async () => {
    if (busy.value) return;

    success.value = '';
    error.value = '';
    busy.value = true;

    try {
        await axios.post('/admin/settings/account/2fa/enable', {
            code: code.value,
        });

        success.value = 'Two-factor authentication is now enabled.';
        code.value = '';
        window.location.reload();
    } catch (requestError) {
        error.value = requestError?.response?.data?.errors?.code?.[0]
            || requestError?.response?.data?.message
            || 'Unable to enable two-factor authentication.';
    } finally {
        busy.value = false;
    }
};

const disableTwoFactor = async () => {
    if (disabling.value) return;

    success.value = '';
    error.value = '';
    disabling.value = true;

    try {
        await axios.delete('/admin/settings/account/2fa');
        success.value = 'Two-factor authentication has been disabled.';
        window.location.reload();
    } catch (requestError) {
        error.value = requestError?.response?.data?.message || 'Unable to disable two-factor authentication.';
    } finally {
        disabling.value = false;
    }
};
</script>

<template>
    <AdminLayout>
        <div style="background:#fff;border:1px solid #e5ebf3;border-radius:16px;padding:24px;max-width:920px;">
            <p style="margin:0 0 8px;font-size:11px;font-weight:800;letter-spacing:.12em;text-transform:uppercase;color:#9CA3AF;">
                Account
            </p>
            <h1 style="margin:0 0 10px;font-size:26px;font-weight:800;color:#2D2D2D;">Admin Account Settings</h1>
            <p style="margin:0 0 18px;color:#475569;">Update your account details and profile photo, then manage security settings.</p>

            <form @submit.prevent="saveProfile" style="background:#FFF8F0;border:1px solid #FFE7DD;border-radius:16px;padding:18px;margin-bottom:22px;">
                <div style="display:flex;align-items:center;gap:14px;flex-wrap:wrap;">
                    <img
                        :src="avatarPreview || 'https://ui-avatars.com/api/?name=' + (profileForm.name || 'Admin') + '&background=E8553E&color=fff'"
                        alt="Admin avatar"
                        style="width:72px;height:72px;border-radius:999px;object-fit:cover;border:3px solid #fff;box-shadow:0 3px 12px rgba(15,23,42,.14);"
                    />

                    <label style="display:inline-flex;align-items:center;justify-content:center;min-height:40px;padding:0 14px;border-radius:999px;background:#E8553E;color:#fff;font-size:14px;font-weight:700;cursor:pointer;">
                        Upload Photo
                        <input type="file" accept="image/*" style="display:none" @change="handleAvatarChange" />
                    </label>
                </div>

                <div style="display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:12px;margin-top:14px;">
                    <label style="display:flex;flex-direction:column;gap:6px;font-size:14px;font-weight:700;color:#374151;">
                        Full Name
                        <input v-model="profileForm.name" type="text" autocomplete="name" required style="min-height:42px;border:1px solid #E5D8CF;border-radius:10px;padding:0 12px;font-size:15px;" />
                        <small v-if="profileError('name')" style="color:#b91c1c;font-size:13px;font-weight:700;">{{ profileError('name') }}</small>
                    </label>

                    <label style="display:flex;flex-direction:column;gap:6px;font-size:14px;font-weight:700;color:#374151;">
                        Email
                        <input v-model="profileForm.email" type="email" autocomplete="email" required style="min-height:42px;border:1px solid #E5D8CF;border-radius:10px;padding:0 12px;font-size:15px;" />
                        <small v-if="profileError('email')" style="color:#b91c1c;font-size:13px;font-weight:700;">{{ profileError('email') }}</small>
                    </label>
                </div>

                <small v-if="profileError('avatar')" style="display:block;margin-top:8px;color:#b91c1c;font-size:13px;font-weight:700;">{{ profileError('avatar') }}</small>
                <small v-if="profileError('general')" style="display:block;margin-top:8px;color:#b91c1c;font-size:13px;font-weight:700;">{{ profileError('general') }}</small>
                <p v-if="profileStatus" style="margin:10px 0 0;color:#0f766e;font-size:14px;font-weight:700;">{{ profileStatus }}</p>

                <button type="submit" :disabled="profileSaving" style="margin-top:12px;min-height:42px;padding:0 16px;border:none;border-radius:999px;background:#E8553E;color:#fff;font-weight:700;cursor:pointer;">
                    {{ profileSaving ? 'Saving Profile...' : 'Save Profile' }}
                </button>
            </form>

            <p style="margin:0 0 8px;font-size:11px;font-weight:800;letter-spacing:.12em;text-transform:uppercase;color:#9CA3AF;">
                Security
            </p>
            <p style="margin:0 0 18px;color:#475569;">Manage two-factor authentication to protect admin access.</p>

            <div v-if="success" style="margin:0 0 14px;padding:12px 14px;border-radius:12px;background:#ecfdf5;border:1px solid #10b981;color:#065f46;font-weight:600;">
                {{ success }}
            </div>

            <div v-if="error" style="margin:0 0 14px;padding:12px 14px;border-radius:12px;background:#fef2f2;border:1px solid #ef4444;color:#b91c1c;font-weight:600;">
                {{ error }}
            </div>

            <div v-if="isEnabled" style="background:#FFF8F0;border:1px solid #FFE7DD;border-radius:16px;padding:18px;">
                <h2 style="margin:0 0 8px;font-size:20px;font-weight:800;color:#2D2D2D;">Two-Factor Status: Enabled</h2>
                <p style="margin:0 0 14px;color:#2D2D2D;">Your admin account requires a 6-digit code at sign-in.</p>
                <button
                    type="button"
                    :disabled="disabling"
                    @click="disableTwoFactor"
                    style="min-height:44px;padding:0 16px;border-radius:10px;border:1px solid #ef4444;background:#fff;color:#b91c1c;font-weight:700;cursor:pointer;"
                >
                    {{ disabling ? 'Disabling...' : 'Disable Two-Factor Authentication' }}
                </button>
            </div>

            <div v-else style="display:grid;grid-template-columns:1fr 1fr;gap:18px;align-items:start;">
                <div style="background:#FFF8F0;border:1px solid #FFE7DD;border-radius:16px;padding:18px;">
                    <h2 style="margin:0 0 8px;font-size:20px;font-weight:800;color:#2D2D2D;">Set Up Authenticator App</h2>
                    <p style="margin:0 0 12px;color:#2D2D2D;">Scan this QR in Google Authenticator, Microsoft Authenticator, or Authy.</p>

                    <div
                        v-if="two_factor?.qr_svg"
                        style="background:#fff;border:1px solid #e2e8f0;border-radius:12px;padding:10px;display:inline-block;"
                        v-html="two_factor.qr_svg"
                    />

                    <p style="margin:12px 0 4px;font-size:13px;color:#9CA3AF;">Manual setup key</p>
                    <p style="margin:0;font-family:monospace;font-size:15px;font-weight:700;color:#2D2D2D;word-break:break-all;">
                        {{ two_factor?.manual_key || two_factor?.secret_preview || 'Unavailable' }}
                    </p>
                </div>

                <form style="background:#FFF8F0;border:1px solid #FFE7DD;border-radius:16px;padding:18px;" @submit.prevent="enableTwoFactor">
                    <h2 style="margin:0 0 8px;font-size:20px;font-weight:800;color:#2D2D2D;">Verify and Enable</h2>
                    <p style="margin:0 0 12px;color:#2D2D2D;">Enter the current 6-digit code from your authenticator app.</p>

                    <label for="two-factor-code" style="display:block;margin:0 0 6px;font-size:14px;font-weight:700;color:#2D2D2D;">Verification Code</label>
                    <input
                        id="two-factor-code"
                        v-model="code"
                        inputmode="numeric"
                        pattern="[0-9]*"
                        maxlength="6"
                        required
                        style="width:100%;min-height:46px;padding:0 12px;border:1px solid #F0E8E0;border-radius:10px;font-size:18px;letter-spacing:0.08em;"
                        placeholder="123456"
                    />

                    <button
                        type="submit"
                        :disabled="busy"
                        style="margin-top:14px;min-height:46px;padding:0 16px;border:none;border-radius:10px;background:#E8553E;color:#fff;font-weight:700;cursor:pointer;"
                    >
                        {{ busy ? 'Enabling...' : 'Enable Two-Factor Authentication' }}
                    </button>
                </form>
            </div>
        </div>
    </AdminLayout>
</template>
