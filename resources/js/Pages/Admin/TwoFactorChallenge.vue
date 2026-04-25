<script setup>
import { useForm } from '@inertiajs/vue3';

const props = defineProps({
    email: {
        type: String,
        default: '',
    },
});

const form = useForm({
    code: '',
});

const submit = () => {
    form.post('/admin/2fa', {
        onFinish: () => {
            form.reset('code');
        },
    });
};
</script>

<template>
    <div style="min-height:100vh;display:flex;align-items:center;justify-content:center;padding:20px;background:#FFF8F0;">
        <div style="width:min(480px,100%);background:#fff;border:1px solid #e2e8f0;border-radius:16px;padding:26px;box-shadow:0 20px 40px rgba(15,23,42,0.08);">
            <p style="margin:0 0 8px;font-size:12px;font-weight:800;letter-spacing:0.12em;text-transform:uppercase;color:#9CA3AF;">Admin Security</p>
            <h1 style="margin:0 0 8px;font-size:28px;font-weight:800;color:#2D2D2D;">Two-Factor Verification</h1>
            <p style="margin:0 0 16px;color:#2D2D2D;">Enter the 6-digit code from your authenticator app for {{ email }}.</p>

            <div v-if="form.errors.code" style="margin:0 0 12px;padding:12px;border-radius:10px;background:#fef2f2;border:1px solid #ef4444;color:#b91c1c;font-weight:600;">
                {{ form.errors.code }}
            </div>

            <form @submit.prevent="submit">
                <label for="code" style="display:block;margin:0 0 6px;font-size:14px;font-weight:700;color:#2D2D2D;">Verification Code</label>
                <input
                    id="code"
                    v-model="form.code"
                    inputmode="numeric"
                    pattern="[0-9]*"
                    maxlength="6"
                    required
                    autocomplete="one-time-code"
                    placeholder="123456"
                    style="width:100%;min-height:52px;padding:0 12px;border-radius:10px;border:1px solid #F0E8E0;font-size:22px;letter-spacing:0.1em;"
                />

                <button
                    type="submit"
                    :disabled="form.processing"
                    style="margin-top:14px;width:100%;min-height:52px;border:none;border-radius:10px;background:#E8553E;color:#fff;font-weight:700;font-size:16px;cursor:pointer;"
                >
                    {{ form.processing ? 'Verifying...' : 'Verify and Continue' }}
                </button>
            </form>
        </div>
    </div>
</template>
