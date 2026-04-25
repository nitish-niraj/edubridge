<script setup>
import { computed, ref } from 'vue';

const props = defineProps({
    variant: {
        type: String,
        default: 'primary',
        validator: (value) => ['primary', 'secondary', 'ghost'].includes(value),
    },
    size: {
        type: String,
        default: 'md',
        validator: (value) => ['sm', 'md', 'lg'].includes(value),
    },
    loading: {
        type: Boolean,
        default: false,
    },
    icon: {
        type: String,
        default: '',
    },
    disabled: {
        type: Boolean,
        default: false,
    },
    type: {
        type: String,
        default: 'button',
    },
});

const emit = defineEmits(['click']);
const buttonRef = ref(null);

const computedClasses = computed(() => [
    's-btn',
    `s-btn--${props.variant}`,
    `s-btn--${props.size}`,
    {
        's-btn--loading': props.loading,
    },
]);

const canUseMagnet = () => {
    return Boolean(window.matchMedia?.('(pointer: fine)').matches);
};

const resetMagnet = () => {
    const element = buttonRef.value;
    if (!element) return;

    element.style.setProperty('--mx', '0px');
    element.style.setProperty('--my', '0px');
};

const onClick = (event) => {
    if (props.disabled || props.loading) {
        event.preventDefault();
        return;
    }

    emit('click', event);
};

const handleMouseMove = (event) => {
    if (props.disabled || props.loading || !canUseMagnet()) return;

    const element = buttonRef.value;
    if (!element) return;

    const rect = element.getBoundingClientRect();
    const x = (event.clientX - rect.left - rect.width / 2) / 8;
    const y = (event.clientY - rect.top - rect.height / 2) / 8;

    element.style.setProperty('--mx', `${x}px`);
    element.style.setProperty('--my', `${y}px`);
};

const handleMouseLeave = () => {
    if (props.disabled || props.loading) return;
    resetMagnet();
};
</script>

<template>
    <button
        ref="buttonRef"
        :type="type"
        :class="computedClasses"
        :disabled="disabled || loading"
        @click="onClick"
        @mousemove="handleMouseMove"
        @mouseleave="handleMouseLeave"
    >
        <span v-if="loading" class="s-btn-loader" aria-hidden="true">
            <span class="dot"></span>
            <span class="dot"></span>
            <span class="dot"></span>
        </span>

        <span v-else class="s-btn-content">
            <slot name="icon-left">
                <span v-if="icon" class="s-btn-icon" aria-hidden="true">{{ icon }}</span>
            </slot>
            <slot />
            <slot name="icon-right" />
        </span>
    </button>
</template>

<style scoped>
.s-btn {
    --mx: 0px;
    --my: 0px;
    --lift: 0px;
    --press: 1;
    font-family: var(--s-font-body, 'Nunito', sans-serif);
    font-weight: 700;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: 8px;
    cursor: pointer;
    position: relative;
    min-height: 42px;
    border-radius: 50px;
    text-align: center;
    transform: translate(var(--mx), var(--my)) translateY(var(--lift)) scale(var(--press));
    transition: transform 0.2s cubic-bezier(0.34, 1.56, 0.64, 1), box-shadow 0.2s cubic-bezier(0.34, 1.56, 0.64, 1), background-color 0.2s cubic-bezier(0.34, 1.56, 0.64, 1), color 0.2s cubic-bezier(0.34, 1.56, 0.64, 1), border-color 0.2s cubic-bezier(0.34, 1.56, 0.64, 1);
    will-change: transform, box-shadow;
}

.s-btn-content {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: 8px;
}

.s-btn-icon {
    font-size: 1em;
    line-height: 1;
}

.s-btn:disabled {
    opacity: 0.6;
    cursor: not-allowed;
    transform: none;
    box-shadow: none;
}

.s-btn:not(:disabled):hover {
    --lift: -2px;
}

.s-btn:not(:disabled):active {
    --press: 0.96;
}

.s-btn--sm {
    padding: 10px 20px;
    font-size: 14px;
}

.s-btn--md {
    padding: 14px 32px;
    font-size: 16px;
}

.s-btn--lg {
    padding: 18px 40px;
    font-size: 18px;
}

.s-btn--primary {
    background: #E8553E;
    color: #FFFFFF;
    border: none;
}

.s-btn--primary:not(:disabled):hover {
    background: #D44433;
    box-shadow: 0 8px 24px rgba(232, 85, 62, 0.35);
}

.s-btn--secondary {
    background: #FFFFFF;
    color: #E8553E;
    border: 2px solid #E8553E;
}

.s-btn--secondary:not(:disabled):hover {
    background: #FFFFFF;
    box-shadow: 0 8px 24px rgba(232, 85, 62, 0.2);
}

.s-btn--ghost {
    background: transparent;
    color: #9CA3AF;
    border: none;
}

.s-btn--ghost:not(:disabled):hover {
    color: #E8553E;
    background: rgba(232, 85, 62, 0.08);
}

.s-btn--loading {
    pointer-events: none;
}

.s-btn--secondary.s-btn--loading,
.s-btn--ghost.s-btn--loading {
    background: #E8553E;
    border-color: #E8553E;
    color: #FFFFFF;
}

.s-btn-loader {
    display: inline-flex;
    align-items: center;
    gap: 4px;
}

.s-btn-loader .dot {
    width: 8px;
    height: 8px;
    border-radius: 50%;
    background: #FFFFFF;
    animation: bounce 0.62s infinite cubic-bezier(0.34, 1.56, 0.64, 1);
}

.s-btn-loader .dot:nth-child(1) {
    animation-delay: 0ms;
}

.s-btn-loader .dot:nth-child(2) {
    animation-delay: 160ms;
}

.s-btn-loader .dot:nth-child(3) {
    animation-delay: 320ms;
}

@keyframes bounce {
    0%,
    100% {
        transform: translateY(0);
    }

    50% {
        transform: translateY(-6px);
    }
}
</style>
