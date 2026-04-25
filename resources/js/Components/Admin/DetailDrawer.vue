<template>
    <Teleport to="body">
        <!-- Backdrop -->
        <Transition
            enter-active-class="transition-opacity duration-300 ease-out"
            enter-from-class="opacity-0"
            enter-to-class="opacity-100"
            leave-active-class="transition-opacity duration-200 ease-in"
            leave-from-class="opacity-100"
            leave-to-class="opacity-0"
        >
            <div v-if="modelValue" 
                 class="fixed inset-0 bg-[#0F172A]/20 z-[60]" 
                 @click="close">
            </div>
        </Transition>

        <!-- Slide Over Drawer with exact 320ms timing as requested -->
        <Transition
            enter-active-class="transition-transform duration-[320ms] ease-out"
            enter-from-class="translate-x-full"
            enter-to-class="translate-x-0"
            leave-active-class="transition-transform duration-[320ms] ease-out"
            leave-from-class="translate-x-0"
            leave-to-class="translate-x-full"
        >
            <div v-if="modelValue" 
                 class="fixed inset-y-0 right-0 w-full max-w-[480px] bg-white border-l border-[#E2E8F0] shadow-2xl z-[70] flex flex-col font-inter">
                
                <div class="h-16 px-6 flex items-center justify-between border-b border-[#E2E8F0] shrink-0">
                    <h3 class="text-[16px] font-semibold text-[#0F172A]">{{ title }}</h3>
                    <button @click="close" class="text-[#64748B] hover:text-[#0F172A] p-2 -mr-2 rounded transition-colors focus:outline-none focus:ring-2 focus:ring-[#2563EB]">
                        <span class="sr-only">Close panel</span>
                        <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" /></svg>
                    </button>
                </div>

                <div class="flex-1 overflow-y-auto px-6 py-6 text-[14px] text-[#0F172A]">
                    <slot />
                </div>
                
                <div v-if="$slots.footer" class="p-4 border-t border-[#E2E8F0] bg-[#F8FAFC]">
                    <slot name="footer" />
                </div>
            </div>
        </Transition>
    </Teleport>
</template>

<script setup>
defineProps({
    modelValue: Boolean,
    title: {
        type: String,
        default: 'Details'
    }
});

const emit = defineEmits(['update:modelValue']);

const close = () => {
    emit('update:modelValue', false);
};
</script>
