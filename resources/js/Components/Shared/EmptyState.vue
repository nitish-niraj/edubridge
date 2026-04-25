<script setup>
import { Link } from '@inertiajs/vue3';
import { onMounted, ref } from 'vue';

const props = defineProps({
    illustration: {
        type: String,
        default: 'search',
    },
    title: {
        type: String,
        required: true,
    },
    body: {
        type: String,
        required: true,
    },
    ctaText: {
        type: String,
        default: '',
    },
    ctaRoute: {
        type: String,
        default: '',
    },
});

const emit = defineEmits(['cta']);
const entered = ref(false);

const handleCta = () => {
    emit('cta');
};

onMounted(() => {
    window.requestAnimationFrame(() => {
        entered.value = true;
    });
});
</script>

<template>
    <section class="empty-state-shell" aria-live="polite">
        <div class="empty-illustration-wrap float" :class="{ 'is-entered': entered }">
            <svg v-if="illustration === 'search'" viewBox="0 0 320 220" class="empty-svg" role="img" aria-label="Search illustration">
                <rect x="20" y="42" width="200" height="132" rx="18" fill="#FFF3EF" stroke="#F5D3CB" />
                <circle cx="114" cy="102" r="34" fill="#FFFFFF" stroke="#E8553E" stroke-width="8" />
                <line x1="136" y1="126" x2="172" y2="162" stroke="#E8553E" stroke-width="12" stroke-linecap="round" />
                <rect x="234" y="62" width="68" height="108" rx="14" fill="#FFE7DE" />
                <rect x="48" y="152" width="88" height="10" rx="5" fill="#E7BBAE" />
                <rect x="58" y="170" width="62" height="10" rx="5" fill="#F2C9BC" />
                <circle cx="250" cy="52" r="16" fill="#4CB87E" opacity="0.22" />
            </svg>

            <svg v-else-if="illustration === 'calendar'" viewBox="0 0 320 220" class="empty-svg" role="img" aria-label="Calendar illustration">
                <rect x="42" y="30" width="236" height="158" rx="18" fill="#FFFFFF" stroke="#F0DDD5" stroke-width="6" />
                <rect x="42" y="30" width="236" height="42" rx="18" fill="#FFEAE4" />
                <circle cx="94" cy="51" r="8" fill="#E8553E" />
                <circle cx="226" cy="51" r="8" fill="#E8553E" />
                <g fill="#EFE8E0">
                    <circle cx="92" cy="102" r="11" />
                    <circle cx="132" cy="102" r="11" />
                    <circle cx="172" cy="102" r="11" />
                    <circle cx="212" cy="102" r="11" />
                    <circle cx="92" cy="140" r="11" />
                    <circle cx="132" cy="140" r="11" />
                    <circle cx="172" cy="140" r="11" />
                    <circle cx="212" cy="140" r="11" />
                </g>
                <circle cx="252" cy="146" r="24" fill="#E8553E" opacity="0.2" />
            </svg>

            <svg v-else-if="illustration === 'messages'" viewBox="0 0 320 220" class="empty-svg" role="img" aria-label="Messages illustration">
                <path d="M44 54h150a18 18 0 0 1 18 18v56a18 18 0 0 1-18 18H94l-32 24v-24H44a18 18 0 0 1-18-18V72a18 18 0 0 1 18-18z" fill="#FFF3EF" stroke="#EBC8BD" stroke-width="4" />
                <path d="M154 30h124a16 16 0 0 1 16 16v56a16 16 0 0 1-16 16h-18v20l-26-20h-80a16 16 0 0 1-16-16V46a16 16 0 0 1 16-16z" fill="#FFFFFF" stroke="#DDE8F2" stroke-width="4" />
                <rect x="52" y="82" width="94" height="10" rx="5" fill="#E0BFB3" />
                <rect x="52" y="100" width="78" height="10" rx="5" fill="#EFD4CC" />
                <rect x="164" y="56" width="92" height="10" rx="5" fill="#D2E6F8" />
                <rect x="164" y="74" width="74" height="10" rx="5" fill="#E2EEF9" />
            </svg>

            <svg v-else viewBox="0 0 320 220" class="empty-svg" role="img" aria-label="Saved items illustration">
                <path d="M160 186c-8 0-16-2-24-7-34-22-66-52-82-82-14-26-8-58 13-74 22-17 55-14 74 7l19 22 20-22c19-21 52-24 74-7 21 16 27 48 13 74-16 30-48 60-82 82-8 5-16 7-25 7z" fill="#FFE9E2" stroke="#E8553E" stroke-width="8" />
                <circle cx="88" cy="54" r="12" fill="#F5C518" opacity="0.35" />
                <circle cx="244" cy="64" r="10" fill="#4CB87E" opacity="0.3" />
            </svg>
        </div>

        <h2 class="empty-title" :class="{ 'is-entered': entered }">{{ title }}</h2>
        <p class="empty-body" :class="{ 'is-entered': entered }">{{ body }}</p>

        <div class="empty-cta" :class="{ 'is-entered': entered }">
            <Link
                v-if="ctaText && ctaRoute"
                :href="ctaRoute"
                class="empty-cta-btn"
            >
                {{ ctaText }}
            </Link>

            <button
                v-else-if="ctaText"
                type="button"
                class="empty-cta-btn"
                @click="handleCta"
            >
                {{ ctaText }}
            </button>
        </div>
    </section>
</template>

<style scoped>
.empty-state-shell {
    width: 100%;
    max-width: 760px;
    margin: 0 auto;
    padding: 20px 14px;
    display: flex;
    flex-direction: column;
    align-items: center;
    text-align: center;
}

.empty-illustration-wrap,
.empty-title,
.empty-body,
.empty-cta {
    opacity: 0;
    transform: translateY(12px);
}

.empty-illustration-wrap {
    width: min(320px, 100%);
    margin-bottom: 12px;
    transform: scale(0.84);
}

.empty-illustration-wrap.is-entered {
    opacity: 1;
    transform: scale(1);
    transition: opacity 220ms ease, transform 620ms cubic-bezier(0.34, 1.56, 0.64, 1);
}

.empty-title {
    margin: 8px 0 0;
    font-family: 'Fredoka One', cursive;
    font-size: 22px;
    line-height: 1.2;
    color: #e8553e;
}

.empty-title.is-entered {
    opacity: 1;
    transform: translateY(0);
    transition: opacity 240ms ease 120ms, transform 280ms ease 120ms;
}

.empty-body {
    margin: 10px 0 0;
    max-width: 620px;
    font-family: 'Nunito', sans-serif;
    font-size: 18px;
    line-height: 1.55;
    color: #6b7280;
}

.empty-body.is-entered {
    opacity: 1;
    transform: translateY(0);
    transition: opacity 240ms ease 210ms, transform 280ms ease 210ms;
}

.empty-cta {
    margin-top: 18px;
    transform: scale(0.92);
}

.empty-cta.is-entered {
    opacity: 1;
    transform: scale(1);
    transition: opacity 240ms ease 300ms, transform 340ms cubic-bezier(0.34, 1.56, 0.64, 1) 300ms;
}

.empty-cta-btn {
    min-height: 46px;
    padding: 0 24px;
    border: 0;
    border-radius: 999px;
    background: #e8553e;
    color: #fff;
    font-family: 'Fredoka One', cursive;
    font-size: 18px;
    text-decoration: none;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    cursor: pointer;
}

.empty-svg {
    display: block;
    width: 100%;
    height: auto;
}

@media (prefers-reduced-motion: reduce) {
    .empty-illustration-wrap,
    .empty-title,
    .empty-body,
    .empty-cta {
        opacity: 1;
        transform: none;
        transition: none;
    }
}
</style>
