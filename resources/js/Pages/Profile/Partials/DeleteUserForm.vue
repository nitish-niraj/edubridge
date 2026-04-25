<script setup>
import DangerButton from '@/Components/DangerButton.vue';
import Modal from '@/Components/Modal.vue';
import SecondaryButton from '@/Components/SecondaryButton.vue';
import { usePage } from '@inertiajs/vue3';
import { computed, ref } from 'vue';

const confirmingUserDeletion = ref(false);
const page = usePage();
const accountRouteName = computed(() => {
    const role = page.props.auth?.user?.role;

    if (role === 'teacher') {
        return 'teacher.settings';
    }

    if (role === 'admin') {
        return 'admin.settings.account';
    }

    return 'student.profile';
});

const confirmUserDeletion = () => {
    confirmingUserDeletion.value = true;
};

const openAccountSettings = () => {
    closeModal();
    window.location.href = route(accountRouteName.value);
};

const closeModal = () => {
    confirmingUserDeletion.value = false;
};
</script>

<template>
    <section class="space-y-6">
        <header>
            <h2 class="text-lg font-medium text-gray-900">Delete Account</h2>

            <p class="mt-1 text-sm text-gray-600">
                Once your account is deleted, all of its resources and data will be permanently deleted. Before deleting
                your account, please download any data or information that you wish to retain.
            </p>
        </header>

        <DangerButton @click="confirmUserDeletion">Delete Account</DangerButton>

        <Modal :show="confirmingUserDeletion" @close="closeModal">
            <div class="p-6">
                <h2 class="text-lg font-medium text-gray-900">
                    Account deletion moved
                </h2>

                <p class="mt-1 text-sm text-gray-600">
                    This legacy page no longer performs account deletion directly. Open the current account settings
                    page to continue.
                </p>

                <div class="mt-6 flex justify-end">
                    <SecondaryButton @click="closeModal"> Cancel </SecondaryButton>

                    <DangerButton class="ms-3" @click="openAccountSettings">
                        Open Account Settings
                    </DangerButton>
                </div>
            </div>
        </Modal>
    </section>
</template>
