<template>
    <div class="flex flex-col md:flex-row min-h-screen bg-[#FAFAF8] text-[#111827]" data-portal="teacher">
        <!-- Sidebar Navigation -->
        <TeacherNavigation :currentRoute="currentRoute" />

        <!-- Main Content Area centered inside a 720px column -->
        <main class="flex-1 overflow-y-auto w-full">
            <div class="max-w-[720px] mx-auto py-12 px-6 pb-24">
                <slot />
            </div>
        </main>
    </div>
</template>

<script setup>
import TeacherNavigation from './TeacherNavigation.vue';

defineProps({
    currentRoute: String
});
</script>

<style>
/* Any localized teacher portal base resets if needed */
</style>
