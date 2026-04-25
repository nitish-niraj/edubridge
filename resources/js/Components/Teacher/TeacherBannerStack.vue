<script setup>
import { computed } from 'vue';

const props = defineProps({
    banners: {
        type: Array,
        default: () => [],
    },
});

const emit = defineEmits(['dismiss']);

const iconMap = {
    success: '✓',
    warning: '!',
    error: '!',
    info: 'i',
};

const normalizedBanners = computed(() => {
    return (props.banners || []).map((banner, index) => ({
        id: banner.id ?? `banner-${index}`,
        type: banner.type || 'info',
        message: banner.message || '',
        icon: banner.icon || iconMap[banner.type] || iconMap.info,
    }));
});

const dismissBanner = (id) => {
    emit('dismiss', id);
};
</script>

<template>
    <div class="teacher-banner-stack" role="status" aria-live="polite">
        <TransitionGroup name="teacher-banner-drop" tag="div" class="teacher-banner-list">
            <article
                v-for="banner in normalizedBanners"
                :key="banner.id"
                class="teacher-banner"
                :class="`teacher-banner--${banner.type}`"
            >
                <div class="teacher-banner__left">
                    <span class="teacher-banner__icon" aria-hidden="true">{{ banner.icon }}</span>
                    <p class="teacher-banner__message">{{ banner.message }}</p>
                </div>

                <button
                    type="button"
                    class="teacher-banner__dismiss"
                    aria-label="Dismiss notification"
                    @click="dismissBanner(banner.id)"
                >
                    ×
                </button>
            </article>
        </TransitionGroup>
    </div>
</template>

<style scoped>
.teacher-banner-stack {
    width: 100%;
}

.teacher-banner-list {
    display: grid;
    gap: 10px;
}

.teacher-banner {
    width: 100%;
    padding: 16px 24px;
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 16px;
    border-radius: 8px;
}

.teacher-banner--success {
    background: #FFF3EF;
    border-left: 6px solid #E8553E;
}

.teacher-banner--warning {
    background: #fff8e7;
    border-left: 6px solid #e65100;
}

.teacher-banner--error {
    background: #fef2f2;
    border-left: 6px solid #c0392b;
}

.teacher-banner--info {
    background: #eff6ff;
    border-left: 6px solid #E8553E;
}

.teacher-banner__left {
    display: flex;
    align-items: center;
    gap: 12px;
    min-width: 0;
    flex: 1;
}

.teacher-banner__icon {
    width: 24px;
    height: 24px;
    border-radius: 999px;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    font-family: 'Nunito', sans-serif;
    font-size: 14px;
    font-weight: 700;
    background: rgba(255, 255, 255, 0.75);
    color: #2D2D2D;
    flex: 0 0 auto;
}

.teacher-banner__message {
    margin: 0;
    font-family: 'Nunito', sans-serif;
    font-size: 18px;
    line-height: 1.4;
    color: #1f2937;
}

.teacher-banner__dismiss {
    min-width: 44px;
    min-height: 44px;
    border: 1px solid #9CA3AF;
    border-radius: 8px;
    background: #ffffff;
    color: #2D2D2D;
    font-size: 28px;
    line-height: 1;
    cursor: pointer;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    flex: 0 0 auto;
}

.teacher-banner__dismiss:hover {
    background: #FFF8F0;
}

.teacher-banner-drop-enter-active {
    transition: transform 250ms ease-out, opacity 250ms ease-out;
}

.teacher-banner-drop-leave-active {
    transition: opacity 180ms ease;
}

.teacher-banner-drop-enter-from {
    transform: translateY(-100%);
    opacity: 0;
}

.teacher-banner-drop-leave-to {
    opacity: 0;
}

.teacher-banner-drop-move {
    transition: transform 250ms ease;
}

@media (max-width: 768px) {
    .teacher-banner {
        padding: 14px 12px;
    }

    .teacher-banner__message {
        font-size: 16px;
    }
}
</style>
