<script setup>
import { XMarkIcon } from '@heroicons/vue/24/outline';
import { onBeforeUnmount, watch } from 'vue';

const props = defineProps({
    open: {
        type: Boolean,
        default: false,
    },
    title: {
        type: String,
        default: '',
    },
    subtitle: {
        type: String,
        default: '',
    },
    width: {
        type: Number,
        default: 480,
    },
    closeOnOverlay: {
        type: Boolean,
        default: true,
    },
    loading: {
        type: Boolean,
        default: false,
    },
});

const emit = defineEmits(['close']);

let previousBodyOverflow = '';

const lockBodyScroll = (shouldLock) => {
    if (typeof document === 'undefined') {
        return;
    }

    if (shouldLock) {
        previousBodyOverflow = document.body.style.overflow;
        document.body.style.overflow = 'hidden';
        return;
    }

    document.body.style.overflow = previousBodyOverflow;
};

const onEscape = (event) => {
    if (event.key === 'Escape' && props.open) {
        emit('close');
    }
};

const onOverlayClick = (event) => {
    if (event.target === event.currentTarget && props.closeOnOverlay) {
        emit('close');
    }
};

watch(
    () => props.open,
    (isOpen) => {
        if (isOpen) {
            lockBodyScroll(true);
            window.addEventListener('keydown', onEscape);
            return;
        }

        lockBodyScroll(false);
        window.removeEventListener('keydown', onEscape);
    },
    { immediate: true }
);

onBeforeUnmount(() => {
    lockBodyScroll(false);
    window.removeEventListener('keydown', onEscape);
});
</script>

<template>
    <Transition name="admin-drawer-overlay">
        <div v-if="open" class="admin-drawer-overlay" @click="onOverlayClick">
            <aside class="admin-drawer-panel" :style="{ width: `${width}px` }" role="dialog" aria-modal="true">
                <header class="admin-drawer-header">
                    <div class="admin-drawer-heading">
                        <h2>{{ title }}</h2>
                        <p v-if="subtitle">{{ subtitle }}</p>
                    </div>
                    <button class="admin-drawer-close" type="button" aria-label="Close" @click="emit('close')">
                        <XMarkIcon class="close-icon" />
                    </button>
                </header>

                <div v-if="loading" class="admin-drawer-body">
                    <div class="admin-skeleton-line large" />
                    <div class="admin-skeleton-line" />
                    <div class="admin-skeleton-line" />
                    <div class="admin-skeleton-block" />
                    <div class="admin-skeleton-line" />
                    <div class="admin-skeleton-block" />
                </div>

                <div v-else class="admin-drawer-body">
                    <slot />
                </div>

                <footer v-if="$slots.footer" class="admin-drawer-footer">
                    <slot name="footer" />
                </footer>
            </aside>
        </div>
    </Transition>
</template>

<style scoped>
.admin-drawer-overlay {
    position: fixed;
    inset: 0;
    z-index: 90;
    background: rgba(15, 23, 42, 0.4);
    backdrop-filter: blur(2px);
}

.admin-drawer-panel {
    position: fixed;
    top: 0;
    right: 0;
    height: 100vh;
    max-width: 100vw;
    background: #ffffff;
    box-shadow: -8px 0 32px rgba(0, 0, 0, 0.12);
    display: flex;
    flex-direction: column;
    transform: translateX(0);
}

.admin-drawer-header {
    height: 56px;
    border-bottom: 1px solid #e2e8f0;
    padding: 0 16px 0 24px;
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 16px;
}

.admin-drawer-heading {
    min-width: 0;
}

.admin-drawer-heading h2 {
    margin: 0;
    font-size: 16px;
    font-weight: 700;
    color: #2D2D2D;
}

.admin-drawer-heading p {
    margin: 2px 0 0;
    font-size: 12px;
    color: #9CA3AF;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}

.admin-drawer-close {
    width: 32px;
    height: 32px;
    border-radius: 6px;
    border: none;
    background: transparent;
    color: #2D2D2D;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    cursor: pointer;
    transition: background-color 120ms ease;
}

.admin-drawer-close:hover {
    background: #f1f5f9;
}

.close-icon {
    width: 18px;
    height: 18px;
}

.admin-drawer-body {
    flex: 1;
    overflow-y: auto;
    padding: 24px;
}

.admin-drawer-footer {
    position: sticky;
    bottom: 0;
    border-top: 1px solid #e2e8f0;
    padding: 16px 24px;
    background: #ffffff;
    display: flex;
    justify-content: flex-end;
    gap: 10px;
}

.admin-skeleton-line,
.admin-skeleton-block {
    background: #e2e8f0;
    border-radius: 6px;
}

.admin-skeleton-line {
    height: 12px;
    margin-bottom: 12px;
}

.admin-skeleton-line.large {
    width: 72%;
    height: 18px;
    margin-bottom: 20px;
}

.admin-skeleton-block {
    height: 88px;
    margin: 8px 0 18px;
}

.admin-drawer-overlay-enter-active,
.admin-drawer-overlay-leave-active {
    transition: opacity 200ms ease;
}

.admin-drawer-overlay-enter-active .admin-drawer-panel,
.admin-drawer-overlay-leave-active .admin-drawer-panel {
    transition: transform 280ms cubic-bezier(0.16, 1, 0.3, 1);
}

.admin-drawer-overlay-enter-from,
.admin-drawer-overlay-leave-to {
    opacity: 0;
}

.admin-drawer-overlay-enter-from .admin-drawer-panel,
.admin-drawer-overlay-leave-to .admin-drawer-panel {
    transform: translateX(480px);
}
</style>
