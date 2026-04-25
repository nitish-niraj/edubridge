<script setup>
import { computed, onBeforeUnmount, ref, watch } from 'vue';

const props = defineProps({
    label: {
        type: String,
        required: true,
    },
    value: {
        type: [Number, String],
        default: 0,
    },
    trend: {
        type: Number,
        default: 0,
    },
    prefix: {
        type: String,
        default: '',
    },
    suffix: {
        type: String,
        default: '',
    },
    decimals: {
        type: Number,
        default: 0,
    },
    pulseSeed: {
        type: Number,
        default: 0,
    },
});

const toNumeric = (value) => {
    const parsed = Number(value);
    return Number.isFinite(parsed) ? parsed : null;
};

const animatedValue = ref(toNumeric(props.value) ?? 0);
const pulseActive = ref(false);
let frame = null;
let pulseTimer = null;

const stopAnimation = () => {
    if (frame !== null) {
        cancelAnimationFrame(frame);
        frame = null;
    }
};

const triggerPulse = () => {
    pulseActive.value = true;

    clearTimeout(pulseTimer);
    pulseTimer = window.setTimeout(() => {
        pulseActive.value = false;
    }, 320);
};

const animateTo = (target) => {
    stopAnimation();

    const start = animatedValue.value;
    const difference = target - start;
    const duration = 400;
    const startTime = performance.now();

    const tick = (now) => {
        const elapsed = Math.min(1, (now - startTime) / duration);
        const eased = 1 - Math.pow(1 - elapsed, 3);
        animatedValue.value = start + difference * eased;

        if (elapsed < 1) {
            frame = requestAnimationFrame(tick);
            return;
        }

        animatedValue.value = target;
        frame = null;
    };

    frame = requestAnimationFrame(tick);
};

watch(
    () => props.value,
    (nextValue) => {
        const numeric = toNumeric(nextValue);

        if (numeric === null) {
            stopAnimation();
            return;
        }

        animateTo(numeric);
    },
    { immediate: true }
);

watch(
    () => props.pulseSeed,
    (nextValue, previousValue) => {
        if (nextValue === previousValue) {
            return;
        }

        triggerPulse();
    }
);

onBeforeUnmount(() => {
    stopAnimation();
    clearTimeout(pulseTimer);
});

const isNumeric = computed(() => toNumeric(props.value) !== null);

const formattedValue = computed(() => {
    if (!isNumeric.value) {
        return String(props.value ?? '');
    }

    const formatter = new Intl.NumberFormat('en-IN', {
        minimumFractionDigits: props.decimals,
        maximumFractionDigits: props.decimals,
    });

    return `${props.prefix}${formatter.format(animatedValue.value)}${props.suffix}`;
});

const trendPositive = computed(() => props.trend >= 0);
const trendText = computed(() => `${trendPositive.value ? '+' : '-'}${Math.abs(props.trend)}%`);
</script>

<template>
    <article class="admin-stat-card" :class="{ 'pulse-border': pulseActive }">
        <div class="metric-label">{{ label }}</div>
        <div class="metric-value">{{ formattedValue }}</div>
        <div class="trend-pill" :class="trendPositive ? 'positive' : 'negative'">
            {{ trendText }}
        </div>
    </article>
</template>

<style scoped>
.admin-stat-card {
    background: #ffffff;
    border: 1px solid #e2e8f0;
    border-radius: 8px;
    padding: 20px;
    min-height: 138px;
    display: flex;
    flex-direction: column;
    gap: 10px;
    transition: box-shadow 200ms ease, transform 200ms ease;
}

.admin-stat-card:hover {
    box-shadow: 0 4px 12px rgba(0, 0, 0, 0.08);
    transform: translateY(-1px);
}

.admin-stat-card.pulse-border {
    animation: stat-border-pulse 280ms ease;
}

.metric-label {
    font-size: 13px;
    font-weight: 500;
    color: #9CA3AF;
}

.metric-value {
    margin-top: 2px;
    font-size: 28px;
    line-height: 1.15;
    font-weight: 700;
    color: #2D2D2D;
}

.trend-pill {
    margin-top: auto;
    align-self: flex-end;
    border-radius: 999px;
    padding: 4px 10px;
    font-size: 12px;
    line-height: 1.1;
    font-weight: 700;
}

.trend-pill.positive {
    background: #dcfce7;
    color: #16a34a;
}

.trend-pill.negative {
    background: #fee2e2;
    color: #dc2626;
}

@keyframes stat-border-pulse {
    0% {
        border-color: #e2e8f0;
    }
    50% {
        border-color: #E8553E;
    }
    100% {
        border-color: #e2e8f0;
    }
}
</style>
