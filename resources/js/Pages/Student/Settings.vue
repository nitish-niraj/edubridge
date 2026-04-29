<script setup>
import DangerButton from '@/Components/DangerButton.vue';
import InputError from '@/Components/InputError.vue';
import InputLabel from '@/Components/InputLabel.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import SecondaryButton from '@/Components/SecondaryButton.vue';
import TextInput from '@/Components/TextInput.vue';
import Modal from '@/Components/Modal.vue';
import StudentLayout from '@/Layouts/StudentLayout.vue';
import { Head, useForm, usePage } from '@inertiajs/vue3';
import { computed, onMounted, ref } from 'vue';

defineOptions({ inheritAttrs: false });

const page = usePage();
const user = computed(() => page.props.auth?.user || {});
const confirmingDeletion = ref(false);
const passwordInput = ref(null);
const currentPasswordInput = ref(null);
const deletePasswordInput = ref(null);

const profileForm = useForm({
    name: user.value?.name || '',
    email: user.value?.email || '',
});

const passwordForm = useForm({
    current_password: '',
    password: '',
    password_confirmation: '',
});

const deleteForm = useForm({
    password: '',
});

onMounted(() => {
    document.body.setAttribute('data-portal', 'student');
});

const updateProfile = () => {
    profileForm.patch(route('account.profile.update'), {
        preserveScroll: true,
    });
};

const updatePassword = () => {
    passwordForm.put(route('password.update'), {
        preserveScroll: true,
        onSuccess: () => passwordForm.reset(),
        onError: () => {
            if (passwordForm.errors.password) {
                passwordForm.reset('password', 'password_confirmation');
                passwordInput.value?.focus();
            }

            if (passwordForm.errors.current_password) {
                passwordForm.reset('current_password');
                currentPasswordInput.value?.focus();
            }
        },
    });
};

const confirmDeletion = () => {
    confirmingDeletion.value = true;
    deleteForm.clearErrors();
    deleteForm.reset();
};

const closeDeletionModal = () => {
    confirmingDeletion.value = false;
    deleteForm.clearErrors();
    deleteForm.reset();
};

const deleteAccount = () => {
    deleteForm.delete(route('account.profile.destroy'), {
        preserveScroll: true,
        onError: () => deletePasswordInput.value?.focus(),
    });
};
</script>

<template>
    <Head title="Student Settings" />

    <StudentLayout>
        <div class="settings-page">
            <div class="settings-shell">
                <p class="eyebrow">Account</p>
                <h1>Student Settings</h1>
                <p class="helper">Manage your sign-in details, password, and account access.</p>

                <section class="settings-section">
                    <h2>Account Details</h2>

                    <form class="form-stack" @submit.prevent="updateProfile">
                        <div>
                            <InputLabel for="name" value="Full Name" />
                            <TextInput id="name" v-model="profileForm.name" type="text" class="mt-1 block w-full" required autocomplete="name" />
                            <InputError :message="profileForm.errors.name" class="mt-2" />
                        </div>

                        <div>
                            <InputLabel for="email" value="Email" />
                            <TextInput id="email" v-model="profileForm.email" type="email" class="mt-1 block w-full" required autocomplete="username" />
                            <InputError :message="profileForm.errors.email" class="mt-2" />
                        </div>

                        <div class="action-row">
                            <PrimaryButton :disabled="profileForm.processing">Save Details</PrimaryButton>
                            <p v-if="profileForm.recentlySuccessful" class="status-text">Saved.</p>
                        </div>
                    </form>
                </section>

                <section class="settings-section">
                    <h2>Password</h2>

                    <form class="form-stack" @submit.prevent="updatePassword">
                        <div>
                            <InputLabel for="current_password" value="Current Password" />
                            <TextInput
                                id="current_password"
                                ref="currentPasswordInput"
                                v-model="passwordForm.current_password"
                                type="password"
                                class="mt-1 block w-full"
                                autocomplete="current-password"
                            />
                            <InputError :message="passwordForm.errors.current_password" class="mt-2" />
                        </div>

                        <div>
                            <InputLabel for="password" value="New Password" />
                            <TextInput
                                id="password"
                                ref="passwordInput"
                                v-model="passwordForm.password"
                                type="password"
                                class="mt-1 block w-full"
                                autocomplete="new-password"
                            />
                            <InputError :message="passwordForm.errors.password" class="mt-2" />
                        </div>

                        <div>
                            <InputLabel for="password_confirmation" value="Confirm Password" />
                            <TextInput
                                id="password_confirmation"
                                v-model="passwordForm.password_confirmation"
                                type="password"
                                class="mt-1 block w-full"
                                autocomplete="new-password"
                            />
                            <InputError :message="passwordForm.errors.password_confirmation" class="mt-2" />
                        </div>

                        <div class="action-row">
                            <PrimaryButton :disabled="passwordForm.processing">Update Password</PrimaryButton>
                            <p v-if="passwordForm.recentlySuccessful" class="status-text">Saved.</p>
                        </div>
                    </form>
                </section>

                <section class="settings-section danger-section">
                    <div>
                        <h2>Delete Account</h2>
                        <p>Deleting your account signs you out and removes access while preserving soft-delete recovery support.</p>
                    </div>

                    <DangerButton @click="confirmDeletion">Delete Account</DangerButton>
                </section>
            </div>
        </div>

        <Modal :show="confirmingDeletion" @close="closeDeletionModal">
            <form class="delete-modal" @submit.prevent="deleteAccount">
                <h2>Delete Account</h2>
                <p>Enter your password to confirm account deletion.</p>

                <div class="mt-6">
                    <InputLabel for="delete_password" value="Password" class="sr-only" />
                    <TextInput
                        id="delete_password"
                        ref="deletePasswordInput"
                        v-model="deleteForm.password"
                        type="password"
                        class="mt-1 block w-full"
                        autocomplete="current-password"
                        placeholder="Password"
                    />
                    <InputError :message="deleteForm.errors.password" class="mt-2" />
                </div>

                <div class="modal-actions">
                    <SecondaryButton type="button" @click="closeDeletionModal">Cancel</SecondaryButton>
                    <DangerButton :disabled="deleteForm.processing">Delete Account</DangerButton>
                </div>
            </form>
        </Modal>
    </StudentLayout>
</template>

<style scoped>
.settings-page {
    min-height: 100vh;
    background: #fff8f0;
    padding: 32px;
}

.settings-shell {
    max-width: 760px;
    background: #fff;
    border: 1px solid #f0ddd5;
    border-radius: 18px;
    box-shadow: 0 4px 20px rgba(232, 85, 62, 0.08);
    padding: 28px;
}

.eyebrow {
    margin: 0 0 6px;
    font-size: 12px;
    font-weight: 800;
    letter-spacing: 0.14em;
    text-transform: uppercase;
    color: #8b6f65;
}

h1,
h2 {
    margin: 0;
    color: #e8553e;
}

h1 {
    font-family: 'Fredoka One', cursive;
    font-size: 30px;
}

h2 {
    font-size: 20px;
    font-weight: 800;
}

.helper {
    margin: 12px 0 0;
    color: #5f514a;
}

.settings-section {
    margin-top: 22px;
    border: 1px solid #f0ddd5;
    border-radius: 14px;
    background: #fffaf5;
    padding: 18px;
}

.form-stack {
    margin-top: 16px;
    display: grid;
    gap: 16px;
}

.action-row {
    display: flex;
    align-items: center;
    gap: 14px;
    flex-wrap: wrap;
}

.status-text {
    color: #0f766e;
    font-weight: 700;
}

.danger-section {
    display: flex;
    justify-content: space-between;
    align-items: center;
    gap: 16px;
}

.danger-section p,
.delete-modal p {
    margin: 8px 0 0;
    color: #6b7280;
}

.delete-modal {
    padding: 24px;
}

.delete-modal h2 {
    color: #991b1b;
}

.modal-actions {
    margin-top: 24px;
    display: flex;
    justify-content: flex-end;
    gap: 12px;
}

@media (max-width: 700px) {
    .settings-page {
        padding: 18px;
    }

    .danger-section {
        align-items: flex-start;
        flex-direction: column;
    }
}
</style>
