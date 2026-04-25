<template>
    <nav class="fixed bottom-0 left-0 right-0 bg-white border-t border-[var(--s-border)] md:static md:w-64 md:h-screen md:border-r md:border-t-0 flex flex-col z-50">
        <!-- Desktop Brand Header -->
        <div class="hidden md:flex p-6 items-center flex-shrink-0">
            <h1 class="text-coral font-display text-2xl tracking-wide">EduBridge</h1>
        </div>
        
        <!-- Navigation Links -->
        <div class="flex flex-row justify-around md:flex-col md:gap-2 md:p-4 w-full md:mt-4 h-[72px] md:h-auto items-center md:items-stretch">
            <template v-for="link in links" :key="link.name">
                <Link :href="route(link.route)"
                   class="flex flex-col md:flex-row items-center gap-1 md:gap-3 px-2 py-2 md:py-3 md:px-4 rounded-xl transition-colors duration-200 relative overflow-hidden group"
                   :class="{'text-coral bg-[#FFF3EF]': link.active, 'text-gray-400 hover:text-gray-800 hover:bg-[var(--s-cream)]': !link.active}">
                   
                    <component :is="link.icon" 
                               class="w-6 h-6 md:w-5 md:h-5 shrink-0 transition-transform duration-300 group-hover:scale-110" 
                               :class="{'opacity-100': link.active, 'opacity-70': !link.active}" />
                               
                    <span class="text-[11px] md:text-[15px] font-bold md:font-semibold">
                        {{ link.name }}
                    </span>
                    
                    <!-- Ink spread effect on click (optional visual simulation) -->
                    <div v-if="link.active" class="absolute inset-0 bg-coral opacity-5 rounded-xl"></div>
                </Link>
            </template>
        </div>
    </nav>
</template>

<script setup>
import { computed } from 'vue';
import { Link } from '@inertiajs/vue3';
import { 
    HomeIcon, 
    MagnifyingGlassIcon, 
    CalendarDaysIcon, 
    ChatBubbleLeftRightIcon, 
    UserIcon 
} from '@heroicons/vue/24/outline';

const props = defineProps({
    currentRoute: {
        type: String,
        default: 'dashboard'
    }
});

const links = computed(() => [
    { name: 'Dashboard', route: 'student.dashboard', icon: HomeIcon, active: props.currentRoute === 'dashboard' },
    { name: 'Find', route: 'teachers.index', icon: MagnifyingGlassIcon, active: props.currentRoute === 'find' },
    { name: 'Bookings', route: 'student.bookings', icon: CalendarDaysIcon, active: props.currentRoute === 'bookings' },
    { name: 'Messages', route: 'student.chat', icon: ChatBubbleLeftRightIcon, active: props.currentRoute === 'messages' },
    { name: 'Profile', route: 'student.profile', icon: UserIcon, active: props.currentRoute === 'profile' },
]);
</script>
