<script setup>
import { computed, ref, watch } from 'vue';

const props = defineProps({
    rating: {
        type: Number,
        default: 0,
    },
    count: {
        type: Number,
        default: 0,
    },
    size: {
        type: String,
        default: 'md',
        validator: (value) => ['sm', 'md', 'lg'].includes(value),
    },
    interactive: {
        type: Boolean,
        default: false,
    },
});

const emit = defineEmits(['update:rating']);

const hoverRating = ref(0);
const selectedRating = ref(Math.max(0, Math.min(5, Number(props.rating) || 0)));
const clipPrefix = `sr-${Math.random().toString(36).slice(2, 10)}`;

watch(
    () => props.rating,
    (value) => {
        selectedRating.value = Math.max(0, Math.min(5, Number(value) || 0));
    },
);

const sizeMap = {
    sm: 14,
    md: 18,
    lg: 24,
};

const starWidth = computed(() => sizeMap[props.size]);

const displayRating = computed(() => {
    if (props.interactive && hoverRating.value > 0) {
        return hoverRating.value;
    }

    return selectedRating.value;
});

const stars = computed(() => {
    const value = Math.max(0, Math.min(5, displayRating.value));

    return Array.from({ length: 5 }, (_, index) => {
        const starIndex = index + 1;
        let fillPercent = 0;

        if (value >= starIndex) {
            fillPercent = 100;
        } else if (value > starIndex - 1) {
            fillPercent = 50;
        }

        return {
            index: starIndex,
            fillPercent,
            clipId: `${clipPrefix}-${starIndex}`,
        };
    });
});

const handleHover = (index) => {
    if (!props.interactive) return;
    hoverRating.value = index;
};

const handleLeave = () => {
    if (!props.interactive) return;
    hoverRating.value = 0;
};

const handleClick = (index) => {
    if (!props.interactive) return;

    selectedRating.value = index;
    emit('update:rating', index);
};
</script>

<template>
    <div class="star-rating-wrapper" :class="{ interactive }" @mouseleave="handleLeave">
        <div class="stars">
            <svg
                v-for="(star, index) in stars"
                :key="star.index"
                :width="starWidth"
                :height="starWidth"
                viewBox="0 0 24 24"
                class="star-svg"
                :class="{
                    'is-interactive': interactive,
                    'glow-bump': interactive && star.index <= hoverRating,
                }"
                :style="{ '--delay': `${index * 30}ms` }"
                @mouseenter="handleHover(star.index)"
                @click="handleClick(star.index)"
            >
                <defs>
                    <clipPath :id="star.clipId">
                        <rect x="0" y="0" :width="`${star.fillPercent}%`" height="100%" />
                    </clipPath>
                </defs>

                <path
                    d="M12 2l3.09 6.26L22 9.27l-5 4.87 1.18 6.88L12 17.77l-6.18 3.25L7 14.14 2 9.27l6.91-1.01L12 2z"
                    fill="#E8E8E8"
                />

                <path
                    v-if="star.fillPercent > 0"
                    d="M12 2l3.09 6.26L22 9.27l-5 4.87 1.18 6.88L12 17.77l-6.18 3.25L7 14.14 2 9.27l6.91-1.01L12 2z"
                    fill="#F5C518"
                    :clip-path="`url(#${star.clipId})`"
                />
            </svg>
        </div>

        <div v-if="!interactive" class="rating-text">
            <span class="rating-num">{{ Number(rating).toFixed(1) }}</span>
            <span class="rating-count">({{ count }} reviews)</span>
        </div>
    </div>
</template>

<style scoped>
.star-rating-wrapper {
    display: inline-flex;
    align-items: center;
    gap: 8px;
}

.stars {
    display: inline-flex;
    align-items: center;
    gap: 2px;
}

.rating-text {
    display: inline-flex;
    align-items: baseline;
    gap: 4px;
}

.rating-num {
    font-family: var(--s-font-body, 'Nunito', sans-serif);
    font-weight: 600;
    font-size: 14px;
    color: var(--s-text, #2D2D2D);
}

.rating-count {
    font-family: var(--s-font-body, 'Nunito', sans-serif);
    font-size: 13px;
    color: var(--s-text-muted, #9CA3AF);
}

.star-svg {
    transition: transform 0.3s cubic-bezier(0.34, 1.56, 0.64, 1);
    will-change: transform;
}

.star-svg.is-interactive {
    cursor: pointer;
}

.star-svg.glow-bump {
    transform: scale(1.3);
    transition-delay: var(--delay);
}
</style>
