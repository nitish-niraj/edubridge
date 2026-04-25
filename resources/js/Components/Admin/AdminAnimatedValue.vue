<script setup>
import { computed, onBeforeUnmount, ref, watch } from 'vue';

const props = defineProps({
    value: {
        type: [Number, String],
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
    duration: {
        type: Number,
        default: 400,
    },
});

const toNumeric = (value) => {
    const parsed = Number(value);
    return Number.isFinite(parsed) ? parsed : null;
};

const animated = ref(toNumeric(props.value) ?? 0);
let frame = null;

const stop = () => {
    if (frame !== null) {
        cancelAnimationFrame(frame);
        frame = null;
    }
};

const animateTo = (target) => {
    stop();

    const start = animated.value;
    const diff = target - start;
    const startTime = performance.now();

    const tick = (now) => {
        const elapsed = Math.min(1, (now - startTime) / Math.max(120, props.duration));
        const eased = 1 - Math.pow(1 - elapsed, 3);
        animated.value = start + diff * eased;

        if (elapsed < 1) {
            frame = requestAnimationFrame(tick);
            return;
        }

        animated.value = target;
        frame = null;
    };

    frame = requestAnimationFrame(tick);
};

watch(
    () => props.value,
    (nextValue) => {
        const numeric = toNumeric(nextValue);

        if (numeric === null) {
            stop();
            return;
        }

        animateTo(numeric);
    },
    { immediate: true }
);

onBeforeUnmount(stop);

const isNumeric = computed(() => toNumeric(props.value) !== null);

const display = computed(() => {
    if (!isNumeric.value) {
        return String(props.value ?? '');
    }

    const formatter = new Intl.NumberFormat('en-IN', {
        minimumFractionDigits: props.decimals,
        maximumFractionDigits: props.decimals,
    });

    return `${props.prefix}${formatter.format(animated.value)}${props.suffix}`;
});
</script>

<template>
    <span>{{ display }}</span>
</template>
