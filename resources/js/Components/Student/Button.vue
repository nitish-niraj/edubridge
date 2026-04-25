<template>
    <button 
        class="inline-flex items-center justify-center font-sans font-semibold text-[18px] text-white bg-coral rounded-[16px] px-6 py-3 transition-all duration-200 active:scale-95 disabled:opacity-50 hover:-translate-y-0.5"
        style="box-shadow: 0 4px 14px rgba(232, 85, 62, 0.2);"
        :class="[block ? 'w-full' : '']"
        v-bind="$attrs"
    >
        <slot />
    </button>
</template>

<script setup>
defineProps({
    block: {
        type: Boolean,
        default: false
    }
});
</script>
