<template>
    <aside class="w-64 bg-[#1E3A5F] text-white flex flex-col fixed inset-y-0 left-0 z-50 overflow-y-auto font-inter">
        <div class="h-16 flex items-center px-6 border-b border-white/10 shrink-0">
            <h1 class="text-xl font-bold tracking-wide">EduBridge Ops</h1>
        </div>
        
        <nav class="flex-1 py-4 flex flex-col gap-1 px-3">
            <template v-for="link in links" :key="link.name">
                <a :href="link.href"
                   class="flex items-center gap-3 px-3 py-2 rounded-md transition-colors duration-150 text-[14px]"
                   :class="{'bg-[#2563EB] text-white font-medium': link.active, 'text-gray-300 hover:text-white hover:bg-white/10': !link.active}">
                    <component :is="link.icon" class="w-5 h-5 shrink-0" />
                    <span>{{ link.name }}</span>
                    <span v-if="link.badge" class="ml-auto bg-amber-500 text-white text-[11px] font-bold px-1.5 py-0.5 rounded-full leading-none shadow-sm">
                        {{ link.badge }}
                    </span>
                </a>
            </template>
        </nav>
        
        <div class="p-4 border-t border-white/10">
            <div class="flex items-center gap-3 px-2">
                <div class="w-8 h-8 rounded bg-white/20 flex items-center justify-center text-sm font-bold">
                    A
                </div>
                <div class="flex flex-col">
                    <span class="text-[13px] font-medium leading-none mb-1">Admin User</span>
                    <span class="text-[11px] text-gray-400 leading-none">Settings &middot; Logout</span>
                </div>
            </div>
        </div>
    </aside>
</template>

<script setup>
import { computed } from 'vue';
import { 
    HomeIcon, 
    UsersIcon, 
    ShieldCheckIcon, 
    DocumentTextIcon, 
    ChatBubbleLeftEllipsisIcon
} from '@heroicons/vue/24/outline';

const props = defineProps({
    currentRoute: String
});

const links = computed(() => [
    { name: 'Overview', href: '#', icon: HomeIcon, active: props.currentRoute === 'overview' },
    { name: 'Verifications', href: '/design/admin-verifications', icon: ShieldCheckIcon, active: props.currentRoute === 'verifications', badge: '3' },
    { name: 'Users', href: '#', icon: UsersIcon, active: props.currentRoute === 'users' },
    { name: 'Disputes', href: '#', icon: ChatBubbleLeftEllipsisIcon, active: props.currentRoute === 'disputes' },
    { name: 'Reports', href: '#', icon: DocumentTextIcon, active: props.currentRoute === 'reports' },
]);
</script>
