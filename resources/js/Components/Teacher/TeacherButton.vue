<template>
    <button 
        class="inline-flex items-center justify-center font-arial font-bold text-[20px] rounded-[6px] px-8 min-h-[56px] transition-colors duration-0 active:translate-y-0 disabled:opacity-50 disabled:pointer-events-none"
        :class="computedClasses"
        v-bind="$attrs"
    >
        <slot />
    </button>
</template>

<script setup>
import { computed } from 'vue';

const props = defineProps({
    block: Boolean,
    variant: {
        type: String,
        default: 'primary' // 'primary' | 'secondary' | 'outline'
    }
});

const computedClasses = computed(() => {
    return [
        props.block ? 'w-full' : '',
        props.variant === 'primary' ? 'bg-[#3D6B4F] text-white hover:bg-[#2A4D38] border border-transparent' : '',
        props.variant === 'secondary' ? 'bg-[#E8F0EB] text-[#2A4D38] hover:bg-[#C2D6CA] border border-transparent' : '',
        props.variant === 'outline' ? 'border-2 border-[#3D6B4F] text-[#3D6B4F] bg-transparent hover:bg-[#F2F8F4]' : '',
    ].join(' ');
});
</script>
