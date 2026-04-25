<script setup>
import { computed } from 'vue';

const props = defineProps({
    visible: {
        type: Boolean,
        default: false,
    },
    progress: {
        type: Number,
        default: 0,
    },
});

const clampedProgress = computed(() => {
    const value = Number.isFinite(props.progress) ? props.progress : 0;
    return Math.max(0, Math.min(100, value));
});
</script>

<template>
    <transition name="page-loader-fade">
        <div
            v-if="visible"
            class="page-loader"
            role="status"
            aria-live="polite"
            aria-label="Loading page"
        >
            <div class="page-loader-progress-wrap" aria-hidden="true">
                <div class="page-loader-progress" :style="{ transform: `scaleX(${clampedProgress / 100})` }" />
            </div>

            <div class="page-loader-content">
                <span class="page-loader-wordmark">EduBridge</span>
            </div>
        </div>
    </transition>
</template>

<style scoped>
.page-loader {
    position: fixed;
    inset: 0;
    z-index: 3000;
    background: #fff8f0;
    display: flex;
    align-items: center;
    justify-content: center;
    pointer-events: none;
}

.page-loader-progress-wrap {
    position: fixed;
    top: 0;
    left: 0;
    width: 100%;
    height: 3px;
    background: rgba(232, 85, 62, 0.2);
}

.page-loader-progress {
    width: 100%;
    height: 100%;
    background: #e8553e;
    transform-origin: left center;
    transition: transform 150ms linear;
}

.page-loader-wordmark {
    display: inline-block;
    font-family: 'Fredoka One', cursive;
    font-size: 32px;
    line-height: 1;
    letter-spacing: 0.01em;
    color: #e8553e;
}

.page-loader-fade-enter-active,
.page-loader-fade-leave-active {
    transition: opacity 180ms ease;
}

.page-loader-fade-enter-from,
.page-loader-fade-leave-to {
    opacity: 0;
}

@media (prefers-reduced-motion: reduce) {
    .page-loader-progress {
        transition: none;
    }

    .page-loader-fade-enter-active,
    .page-loader-fade-leave-active {
        transition: none;
    }
}
</style>
