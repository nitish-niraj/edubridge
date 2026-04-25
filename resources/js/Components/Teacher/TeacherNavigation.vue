<template>
    <nav class="w-full md:w-[320px] bg-white border-b md:border-b-0 md:border-r border-[#E5E7EB] flex flex-col min-h-0 md:h-screen z-40 sticky top-0 shadow-sm">
        <div class="h-[96px] px-8 flex items-center border-b border-[#E5E7EB] mb-4">
            <h1 class="text-[#3D6B4F] font-georgia text-[32px] font-normal tracking-wide">EduBridge</h1>
        </div>
        
        <div class="flex-1 py-2 flex flex-col overflow-y-auto px-4 gap-2">
            <template v-for="link in links" :key="link.name">
                <a :href="link.href"
                   class="flex flex-row items-center gap-5 px-6 min-h-[64px] transition-colors duration-0 relative text-[20px] font-arial rounded-[4px]"
                   :class="{'text-[#2A4D38] font-bold bg-[#F2F8F4]': link.active, 'text-[#4B5563] hover:text-[#111827] hover:bg-[#F9FAFB]': !link.active}">
                   
                    <!-- Left active border -->
                    <div v-if="link.active" class="absolute left-0 top-0 bottom-0 w-2 bg-[#3D6B4F] rounded-l-md"></div>
                    
                    <component :is="link.icon" class="w-8 h-8" :class="{'text-[#3D6B4F]': link.active, 'text-[#6B7280]': !link.active}" />
                    
                    <span>{{ link.name }}</span>
                </a>
            </template>
        </div>
        
        <!-- User Profile Footer area -->
        <div class="p-6 border-t border-[#E5E7EB] mt-auto">
             <div class="flex items-center gap-4">
                 <div class="w-[56px] h-[56px] rounded-full bg-[#E8F0EB] text-[#2A4D38] flex justify-center items-center font-georgia text-[24px]">
                     G
                 </div>
                 <div>
                     <p class="font-bold font-arial text-[20px] text-[#111827]">Gurpreet S.</p>
                     <p class="font-arial text-[16px] text-[#6B7280]">Log out</p>
                 </div>
             </div>
        </div>
    </nav>
</template>

<script setup>
import { computed } from 'vue';
import { 
    HomeIcon, 
    CalendarIcon, 
    UserIcon, 
    Cog6ToothIcon 
} from '@heroicons/vue/24/outline';

const props = defineProps({
    currentRoute: String
});

const links = computed(() => [
    { name: 'Dashboard', href: '/design/teacher-dashboard', icon: HomeIcon, active: props.currentRoute === 'dashboard' },
    { name: 'My Schedule', href: '/design/teacher-availability', icon: CalendarIcon, active: props.currentRoute === 'availability' },
    { name: 'Students', href: '#', icon: UserIcon, active: props.currentRoute === 'students' },
    { name: 'Settings', href: '#', icon: Cog6ToothIcon, active: props.currentRoute === 'settings' },
]);
</script>
