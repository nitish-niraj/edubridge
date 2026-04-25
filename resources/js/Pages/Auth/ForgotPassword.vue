<script setup>
import GuestLayout from '@/Layouts/GuestLayout.vue';
import InputError from '@/Components/InputError.vue';
import InputLabel from '@/Components/InputLabel.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import TextInput from '@/Components/TextInput.vue';
import { Head, useForm } from '@inertiajs/vue3';
import { ref } from 'vue';
import { validateEmail } from '@/composables/useFormValidation';

defineProps({
    status: { type: String },
});

const form = useForm({
    email: '',
});

const emailError = ref('');
const blurEmail  = () => {
    const result = validateEmail(form.email);
    emailError.value = result.error || '';
};

const submit = () => {
    blurEmail();
    if (emailError.value) return;
    form.post(route('password.email'));
};
</script>

<template>
    <GuestLayout>
        <Head title="Forgot Password" />

        <div class="mb-4 text-sm text-gray-600">
            Forgot your password? No problem. Just let us know your email address and we will email you a password reset
            link that will allow you to choose a new one.
        </div>

        <div v-if="status" class="mb-4 font-medium text-sm text-green-600">
            {{ status }}
        </div>

        <form @submit.prevent="submit" novalidate>
            <div>
                <InputLabel for="email" value="Email" />

                <TextInput
                    id="email"
                    type="email"
                    class="mt-1 block w-full"
                    v-model="form.email"
                    required
                    autofocus
                    autocomplete="username"
                    minlength="5"
                    maxlength="254"
                    :aria-invalid="(form.errors.email || emailError) ? 'true' : 'false'"
                    aria-describedby="fp-email-error"
                    @blur="blurEmail"
                />

                <div id="fp-email-error" role="alert" class="mt-2 text-sm text-red-600" style="min-height:18px;">
                    {{ form.errors.email || emailError }}
                </div>
            </div>

            <div class="flex items-center justify-end mt-4">
                <PrimaryButton :class="{ 'opacity-25': form.processing }" :disabled="form.processing">
                    Email Password Reset Link
                </PrimaryButton>
            </div>
        </form>
    </GuestLayout>
</template>
