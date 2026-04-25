<script setup>
import { onMounted, ref } from 'vue';
import { useAnalytics } from '@/composables/useAnalytics';

const visible = ref(false);
const { grantConsent, denyConsent, consentStorageKey } = useAnalytics();

onMounted(() => {
    if (typeof window === 'undefined' || !window.localStorage) {
        return;
    }

    try {
        visible.value = !localStorage.getItem(consentStorageKey);
    } catch {
        visible.value = false;
    }
});

const accept = () => {
    grantConsent();
    visible.value = false;
};

const reject = () => {
    denyConsent();
    visible.value = false;
};
</script>

<template>
    <transition name="cookie-fade">
        <aside v-if="visible" class="cookie-consent" role="dialog" aria-live="polite" aria-label="Cookie settings">
            <p>
                We use essential cookies and optional analytics cookies to improve your EduBridge experience.
            </p>
            <div class="cookie-actions">
                <button type="button" class="accept" @click="accept">Accept analytics</button>
                <button type="button" class="reject" @click="reject">Reject analytics</button>
            </div>
        </aside>
    </transition>
</template>

<style scoped>
.cookie-consent {
    position: fixed;
    left: 16px;
    right: 16px;
    bottom: 16px;
    z-index: 90;
    max-width: 460px;
    margin: 0 auto;
    background: #2D2D2D;
    color: #e2e8f0;
    border: 1px solid #2D2D2D;
    border-radius: 14px;
    padding: 12px;
    box-shadow: 0 16px 30px rgba(2, 6, 23, .35);
}

.cookie-consent p {
    margin: 0;
    font-size: 13px;
    line-height: 1.45;
}

.cookie-actions {
    margin-top: 10px;
    display: flex;
    gap: 8px;
    flex-wrap: wrap;
}

.cookie-actions button {
    border: 1px solid transparent;
    border-radius: 999px;
    min-height: 34px;
    padding: 0 12px;
    font-size: 12px;
    font-weight: 700;
    cursor: pointer;
}

.cookie-actions .accept {
    background: #22c55e;
    color: #052e16;
}

.cookie-actions .reject {
    background: #2D2D2D;
    color: #FFF8F0;
    border-color: #2D2D2D;
}

.cookie-fade-enter-active,
.cookie-fade-leave-active {
    transition: opacity .2s ease, transform .2s ease;
}

.cookie-fade-enter-from,
.cookie-fade-leave-to {
    opacity: 0;
    transform: translateY(8px);
}
</style>
