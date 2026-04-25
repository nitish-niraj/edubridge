<template>
    <span 
        class="inline-flex items-center justify-center px-2 py-0.5 rounded-full text-[12px] font-semibold leading-[16px] border"
        style="transition: background-color 400ms ease, color 400ms ease, border-color 400ms ease;"
        :class="badgeClasses"
    >
        {{ text }}
    </span>
</template>

<script setup>
import { computed } from 'vue';

const props = defineProps({
    status: {
        type: String,
        default: 'pending' // pending, verified, rejected
    }
});

const text = computed(() => {
    switch (props.status) {
        case 'pending': return 'Pending';
        case 'verified': return 'Verified';
        case 'rejected': return 'Rejected';
        default: return props.status;
    }
});

const badgeClasses = computed(() => {
    switch (props.status) {
        case 'pending': return 'bg-[#FEF9C3] text-[#D97706] border-[#FDE047]'; // Amber
        case 'verified': return 'bg-[#DCFCE7] text-[#16A34A] border-[#BBF7D0]'; // Green
        case 'rejected': return 'bg-[#FEE2E2] text-[#DC2626] border-[#FECACA]'; // Red
        default: return 'bg-[#F1F5F9] text-[#64748B] border-[#E2E8F0]';
    }
});
</script>
