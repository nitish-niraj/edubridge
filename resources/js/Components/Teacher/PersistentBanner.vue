<template>
    <div v-if="isVisible" 
         class="w-full min-h-[72px] flex items-start justify-between px-6 py-5 rounded-[4px] shadow-sm"
         :class="containerClass"
         role="alert">
        <div class="flex items-start gap-4 w-full">
            <div class="flex-shrink-0 mt-0.5">
                <slot name="icon">
                    <svg class="h-8 w-8" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7-4a1 1 0 11-2 0 1 1 0 012 0zM9 9a1 1 0 000 2v3a1 1 0 001 1h1a1 1 0 100-2v-3a1 1 0 00-1-1H9z" clip-rule="evenodd" /></svg>
                </slot>
            </div>
            <div class="flex-1 font-arial text-[20px] leading-relaxed">
                <slot />
            </div>
        </div>
        <!-- Dismiss button with large hit area -->
        <button @click="dismiss" class="ml-4 flex-shrink-0 text-current hover:opacity-75 focus:outline-none focus:ring-2 focus:ring-current min-h-[56px] min-w-[56px] -my-2 flex items-center justify-center rounded-md">
            <span class="sr-only">Dismiss message</span>
            <svg class="h-8 w-8" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" /></svg>
        </button>
    </div>
</template>

<script setup>
import { ref, computed } from 'vue';

const props = defineProps({
    variant: {
        type: String,
        default: 'success' 
    }
});

const isVisible = ref(true);

const dismiss = () => {
    isVisible.value = false;
};

const containerClass = computed(() => {
    switch (props.variant) {
        case 'success': return 'bg-[#E8F0EB] text-[#2A4D38] border-l-[6px] border-[#3D6B4F]';
        case 'warning': return 'bg-[#FEF9C3] text-[#854D0E] border-l-[6px] border-[#EAB308]';
        case 'error': return 'bg-[#FEE2E2] text-[#991B1B] border-l-[6px] border-[#EF4444]';
        default: return 'bg-[#E0F2FE] text-[#0369A1] border-l-[6px] border-[#0EA5E9]';
    }
});
</script>
