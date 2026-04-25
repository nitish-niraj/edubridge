<template>
    <div 
        class="bg-white rounded-card p-6 shadow-card-tinted transition-all duration-300 transform-gpu"
        v-motion
        :initial="{ opacity: 0, scale: 0.95, y: 20 }"
        :enter="{ opacity: 1, scale: 1, y: 0, transition: { type: 'spring', stiffness: 220, damping: 22, delay: delay } }"
    >
        <slot />
    </div>
</template>

<script setup>
defineProps({
    delay: {
        type: Number,
        default: 0
    }
});
</script>
