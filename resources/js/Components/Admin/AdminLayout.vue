<template>
    <div class="min-h-screen bg-[#F8FAFC] font-inter text-[#0F172A]" data-portal="admin">
        <AdminSidebar :currentRoute="currentRoute" />
        <main class="ml-64 min-h-screen">
            <!-- Header bar for context / breadcrumbs -->
            <header class="h-16 bg-white border-b border-[#E2E8F0] flex items-center px-8 shrink-0 relative z-30 shadow-sm">
                <slot name="header">
                    <h2 class="text-[16px] font-semibold text-[#0F172A]">Administration</h2>
                </slot>
            </header>
            
            <div class="p-8">
                <slot />
            </div>
        </main>
    </div>
</template>

<script setup>
import AdminSidebar from './AdminSidebar.vue';

defineProps({
    currentRoute: String
});
</script>
