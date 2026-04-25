<script setup>
import GuestLayout from '@/Layouts/GuestLayout.vue';
import InputError from '@/Components/InputError.vue';
import InputLabel from '@/Components/InputLabel.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import TextInput from '@/Components/TextInput.vue';
import { Head, useForm } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import {
    validatePasswordMatch,
    passwordStrength,
    strengthLabel,
    strengthColor,
} from '@/composables/useFormValidation';

const props = defineProps({
    email: { type: String, required: true },
    token: { type: String, required: true },
});

const form = useForm({
    token:                 props.token,
    email:                 props.email,
    password:              '',
    password_confirmation: '',
});

const showPassword        = ref(false);
const showPasswordConfirm = ref(false);
const confirmError        = ref('');

const pwStrength      = computed(() => passwordStrength(form.password));
const pwStrengthLabel = computed(() => strengthLabel[pwStrength.value]);
const pwStrengthColor = computed(() => strengthColor[pwStrength.value]);

const blurConfirm = () => {
    confirmError.value = validatePasswordMatch(form.password, form.password_confirmation).error || '';
};

const submit = () => {
    blurConfirm();
    if (confirmError.value) return;
    form.post(route('password.store'), {
        onFinish: () => form.reset('password', 'password_confirmation'),
    });
};
</script>

<template>
    <GuestLayout>
        <Head title="Reset Password" />

        <form @submit.prevent="submit" novalidate>
            <div>
                <InputLabel for="rp-email" value="Email" />
                <TextInput
                    id="rp-email"
                    type="email"
                    class="mt-1 block w-full"
                    v-model="form.email"
                    required
                    autofocus
                    autocomplete="username"
                    maxlength="254"
                />
                <InputError class="mt-2" :message="form.errors.email" />
            </div>

            <div class="mt-4">
                <InputLabel for="rp-password" value="Password" />
                <div style="position:relative;">
                    <TextInput
                        id="rp-password"
                        :type="showPassword ? 'text' : 'password'"
                        class="mt-1 block w-full"
                        v-model="form.password"
                        required
                        autocomplete="new-password"
                        minlength="8"
                        maxlength="128"
                        :aria-invalid="form.errors.password ? 'true' : 'false'"
                        aria-describedby="rp-pw-hint rp-pw-error"
                        style="padding-right:48px;"
                    />
                    <button type="button" @click="showPassword = !showPassword"
                        :aria-label="showPassword ? 'Hide password' : 'Show password'"
                        style="position:absolute; right:12px; top:50%; transform:translateY(-50%); background:none; border:none; cursor:pointer; font-size:18px; color:#888; padding:4px;">
                        {{ showPassword ? '🙈' : '👁' }}
                    </button>
                </div>
                <!-- Strength meter -->
                <div v-if="form.password" class="mt-2">
                    <div style="display:flex; gap:4px; margin-bottom:4px;">
                        <div v-for="i in 4" :key="i"
                            :style="`flex:1; height:4px; border-radius:2px; background:${i <= pwStrength ? pwStrengthColor : '#e2e8f0'}; transition:background 0.3s;`">
                        </div>
                    </div>
                    <span :style="`font-size:12px; font-weight:600; color:${pwStrengthColor};`">{{ pwStrengthLabel }}</span>
                </div>
                <p id="rp-pw-hint" class="text-xs text-gray-500 mt-1">At least 8 characters, uppercase, lowercase, number, and symbol.</p>
                <div id="rp-pw-error" role="alert">
                    <InputError class="mt-2" :message="form.errors.password" />
                </div>
            </div>

            <div class="mt-4">
                <InputLabel for="rp-confirm" value="Confirm Password" />
                <div style="position:relative;">
                    <TextInput
                        id="rp-confirm"
                        :type="showPasswordConfirm ? 'text' : 'password'"
                        class="mt-1 block w-full"
                        v-model="form.password_confirmation"
                        required
                        autocomplete="new-password"
                        minlength="8"
                        maxlength="128"
                        :aria-invalid="(form.errors.password_confirmation || confirmError) ? 'true' : 'false'"
                        aria-describedby="rp-confirm-error"
                        @blur="blurConfirm"
                        style="padding-right:48px;"
                    />
                    <button type="button" @click="showPasswordConfirm = !showPasswordConfirm"
                        :aria-label="showPasswordConfirm ? 'Hide confirm password' : 'Show confirm password'"
                        style="position:absolute; right:12px; top:50%; transform:translateY(-50%); background:none; border:none; cursor:pointer; font-size:18px; color:#888; padding:4px;">
                        {{ showPasswordConfirm ? '🙈' : '👁' }}
                    </button>
                </div>
                <div id="rp-confirm-error" role="alert" class="mt-2 text-sm text-red-600" style="min-height:18px;">
                    {{ form.errors.password_confirmation || confirmError }}
                </div>
            </div>

            <div class="flex items-center justify-end mt-4">
                <PrimaryButton :class="{ 'opacity-25': form.processing }" :disabled="form.processing">
                    Reset Password
                </PrimaryButton>
            </div>
        </form>
    </GuestLayout>
</template>
