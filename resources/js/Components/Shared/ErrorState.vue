<script setup>
import { Link } from '@inertiajs/vue3';
import { computed, onMounted, ref } from 'vue';

const props = defineProps({
    code: {
        type: [Number, String],
        default: 500,
    },
    title: {
        type: String,
        default: 'Something went wrong',
    },
    message: {
        type: String,
        default: 'Please try again in a moment.',
    },
    showBack: {
        type: Boolean,
        default: true,
    },
});

const hasArrived = ref(false);
const isFloating = ref(false);

const resolvedCode = computed(() => String(props.code || '500'));

const goBack = () => {
    window.history.back();
};

onMounted(() => {
    hasArrived.value = true;

    window.setTimeout(() => {
        isFloating.value = true;
    }, 620);
});
</script>

<template>
    <section class="error-state" aria-live="polite">
        <div class="error-visual-wrap">
            <svg
                class="error-illustration"
                :class="{
                    'error-illustration--arrive': hasArrived,
                    'float': isFloating,
                }"
                viewBox="0 0 340 240"
                role="img"
                aria-label="Error illustration"
            >
                <rect x="42" y="34" width="256" height="164" rx="22" fill="#FFF3EF" stroke="#F1CDC3" stroke-width="6" />
                <circle cx="126" cy="106" r="16" fill="#E8553E" opacity="0.85" />
                <circle cx="214" cy="106" r="16" fill="#E8553E" opacity="0.85" />
                <path d="M122 158c14-18 82-18 96 0" fill="none" stroke="#E8553E" stroke-width="10" stroke-linecap="round" />
                <rect x="146" y="64" width="48" height="12" rx="6" fill="#F7DAD2" />
                <circle cx="300" cy="50" r="12" fill="#F5C518" opacity="0.33" />
                <circle cx="54" cy="192" r="10" fill="#4CB87E" opacity="0.26" />
            </svg>
        </div>

        <p class="error-code">{{ resolvedCode }}</p>
        <h1 class="error-title">{{ title }}</h1>
        <p class="error-message">{{ message }}</p>

        <div class="error-actions">
            <button v-if="showBack" type="button" class="error-btn secondary" @click="goBack">Go Back</button>
            <Link :href="route('landing')" class="error-btn primary">Go Home</Link>
        </div>
    </section>
</template>

<style scoped>
.error-state {
    min-height: 52vh;
    width: min(760px, 100%);
    margin: 0 auto;
    padding: 20px 14px;
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    text-align: center;
}

.error-visual-wrap {
    width: min(340px, 100%);
}

.error-illustration {
    width: 100%;
    height: auto;
    display: block;
}

.error-illustration--arrive {
    animation: error-arrive-shake 600ms ease both;
}

.error-code {
    margin: 8px 0 0;
    font-family: 'Fredoka One', cursive;
    font-size: clamp(58px, 10vw, 80px);
    line-height: 1;
    color: rgba(232, 85, 62, 0.15);
}

.error-title {
    margin: 8px 0 0;
    font-family: 'Fredoka One', cursive;
    font-size: 28px;
    line-height: 1.2;
    color: #1f2937;
}

.error-message {
    margin: 12px 0 0;
    max-width: 620px;
    font-family: 'Nunito', sans-serif;
    font-size: 18px;
    line-height: 1.6;
    color: #6b7280;
}

.error-actions {
    margin-top: 20px;
    display: inline-flex;
    flex-wrap: wrap;
    gap: 10px;
}

.error-btn {
    min-height: 44px;
    padding: 0 18px;
    border-radius: 999px;
    border: 1px solid transparent;
    text-decoration: none;
    cursor: pointer;
    font-family: 'Nunito', sans-serif;
    font-size: 15px;
    font-weight: 700;
    display: inline-flex;
    align-items: center;
    justify-content: center;
}

.error-btn.primary {
    background: #e8553e;
    border-color: #e8553e;
    color: #ffffff;
}

.error-btn.secondary {
    background: #ffffff;
    border-color: #f0ddd5;
    color: #374151;
}

@keyframes error-arrive-shake {
    0% {
        transform: rotate(-2deg);
    }

    42% {
        transform: rotate(2deg);
    }

    100% {
        transform: rotate(0deg);
    }
}

@media (prefers-reduced-motion: reduce) {
    .error-illustration--arrive {
        animation: none;
    }
}
</style>
