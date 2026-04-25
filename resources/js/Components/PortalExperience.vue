<script setup>
import axios from 'axios';
import { router } from '@inertiajs/vue3';
import { ChatBubbleLeftEllipsisIcon, XMarkIcon } from '@heroicons/vue/24/outline';
import { onBeforeUnmount, onMounted, ref } from 'vue';

const installEvent = ref(null);
const installBannerVisible = ref(false);
const feedbackOpen = ref(false);
const submittingFeedback = ref(false);
const screenshotFile = ref(null);
const form = ref({
    type: 'bug',
    description: '',
});

const visitKey = 'edubridge_visit_count';
const dismissKey = 'edubridge_install_prompt_dismissed';
const sessionDismissKey = 'edubridge_install_prompt_session_dismissed';

const incrementVisits = () => {
    const current = Number(localStorage.getItem(visitKey) || '0') + 1;
    localStorage.setItem(visitKey, String(current));

    if (current >= 3 && installEvent.value && !sessionStorage.getItem(sessionDismissKey) && !localStorage.getItem(dismissKey)) {
        installBannerVisible.value = true;
    }
};

const onBeforeInstallPrompt = (event) => {
    event.preventDefault();
    installEvent.value = event;

    const current = Number(localStorage.getItem(visitKey) || '0');
    if (current >= 3 && !sessionStorage.getItem(sessionDismissKey) && !localStorage.getItem(dismissKey)) {
        installBannerVisible.value = true;
    }
};

const acceptInstall = async () => {
    if (!installEvent.value) return;

    installBannerVisible.value = false;
    const prompt = installEvent.value;
    installEvent.value = null;

    await prompt.prompt();
    const choice = await prompt.userChoice;
    if (choice?.outcome === 'accepted') {
        localStorage.setItem(dismissKey, '1');
    }
};

const dismissInstall = () => {
    installBannerVisible.value = false;
    sessionStorage.setItem(sessionDismissKey, '1');
};

const openFeedback = () => {
    feedbackOpen.value = true;
};

const closeFeedback = () => {
    feedbackOpen.value = false;
    screenshotFile.value = null;
    form.value = { type: 'bug', description: '' };
};

const submitFeedback = async () => {
    if (submittingFeedback.value || !form.value.description.trim()) return;

    submittingFeedback.value = true;
    try {
        const payload = new FormData();
        payload.append('type', form.value.type);
        payload.append('description', form.value.description.trim());
        payload.append('page_url', window.location.href);
        payload.append('user_agent', navigator.userAgent);

        if (screenshotFile.value) {
            payload.append('screenshot', screenshotFile.value);
        }

        await axios.post('/api/feedback', payload, {
            headers: {
                'Content-Type': 'multipart/form-data',
            },
        });

        closeFeedback();
    } catch (error) {
        window.alert(error?.response?.data?.message || 'Unable to submit feedback.');
    } finally {
        submittingFeedback.value = false;
    }
};

const onScreenshotChange = (event) => {
    const file = event.target.files?.[0] ?? null;
    if (!file) return;

    if (!['image/jpeg', 'image/png', 'image/webp'].includes(file.type)) {
        window.alert('Please upload a JPG, PNG, or WebP screenshot.');
        event.target.value = '';
        return;
    }

    if (file.size > 5 * 1024 * 1024) {
        window.alert('Screenshot must be 5MB or smaller.');
        event.target.value = '';
        return;
    }

    screenshotFile.value = file;
};

const onKeydown = (event) => {
    if (event.key === 'Escape' && feedbackOpen.value) {
        closeFeedback();
    }
};

let navigateUnregister = null;

onMounted(() => {
    incrementVisits();
    window.addEventListener('beforeinstallprompt', onBeforeInstallPrompt);
    window.addEventListener('keydown', onKeydown);

    navigateUnregister = router.on('navigate', () => {
        incrementVisits();
    });
});

onBeforeUnmount(() => {
    window.removeEventListener('beforeinstallprompt', onBeforeInstallPrompt);
    window.removeEventListener('keydown', onKeydown);
    if (typeof navigateUnregister === 'function') {
        navigateUnregister();
    }
});
</script>

<template>
    <div>
        <div v-if="installBannerVisible" class="portal-install-banner" role="region" aria-label="Install app prompt">
            <div class="portal-install-banner__copy">
                <span class="portal-install-banner__badge" aria-hidden="true">📱</span>
                <span>Add EduBridge to your home screen for quick access!</span>
            </div>
            <div class="portal-install-banner__actions">
                <button type="button" class="portal-install-banner__button portal-install-banner__button--primary" @click="acceptInstall">
                    Add
                </button>
                <button type="button" class="portal-install-banner__button portal-install-banner__button--ghost" @click="dismissInstall">
                    Not Now
                </button>
            </div>
        </div>

        <button
            type="button"
            class="portal-feedback-button"
            aria-label="Open feedback form"
            @click="openFeedback"
        >
            <ChatBubbleLeftEllipsisIcon class="h-5 w-5" aria-hidden="true" />
        </button>

        <div v-if="feedbackOpen" class="portal-overlay" @click.self="closeFeedback">
            <div class="portal-modal">
                <div class="flex items-start justify-between gap-4 mb-4">
                    <div>
                        <p class="portal-modal__kicker">Beta feedback</p>
                        <h2 class="portal-modal__title">Tell us what needs work</h2>
                    </div>
                    <button type="button" class="portal-modal__close" @click="closeFeedback" aria-label="Close feedback modal">
                        <XMarkIcon class="h-5 w-5" aria-hidden="true" />
                    </button>
                </div>

                <form class="space-y-4" @submit.prevent="submitFeedback">
                    <div>
                        <label class="portal-field-label">Type</label>
                        <div class="portal-type-list">
                            <label class="portal-type-pill" :class="{ 'is-active': form.type === 'bug' }">
                                <input v-model="form.type" type="radio" value="bug" class="portal-type-pill__input" />
                                <span class="portal-type-pill__dot" aria-hidden="true"></span>
                                <span>Bug</span>
                            </label>
                            <label class="portal-type-pill" :class="{ 'is-active': form.type === 'feature' }">
                                <input v-model="form.type" type="radio" value="feature" class="portal-type-pill__input" />
                                <span class="portal-type-pill__dot" aria-hidden="true"></span>
                                <span>Feature Request</span>
                            </label>
                            <label class="portal-type-pill" :class="{ 'is-active': form.type === 'general' }">
                                <input v-model="form.type" type="radio" value="general" class="portal-type-pill__input" />
                                <span class="portal-type-pill__dot" aria-hidden="true"></span>
                                <span>General</span>
                            </label>
                        </div>
                    </div>

                    <div>
                        <label class="portal-field-label" for="feedback-description">Description</label>
                        <textarea
                            id="feedback-description"
                            v-model="form.description"
                            class="portal-input portal-input--textarea"
                            required
                            placeholder="Tell us what happened, what you expected, and any details that help."
                        />
                    </div>

                    <div>
                        <label class="portal-field-label" for="feedback-screenshot">Screenshot (optional)</label>
                        <input
                            id="feedback-screenshot"
                            class="portal-file-input"
                            type="file"
                            accept="image/jpeg,image/png,image/webp"
                            @change="onScreenshotChange"
                        />
                    </div>

                    <div class="portal-modal__footer">
                        <button type="button" class="portal-button portal-button--ghost" @click="closeFeedback">
                            Cancel
                        </button>
                        <button type="submit" class="portal-button portal-button--primary" :disabled="submittingFeedback">
                            {{ submittingFeedback ? 'Sending...' : 'Send Feedback' }}
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</template>

<style scoped>
.portal-install-banner {
    position: fixed;
    top: 12px;
    left: 12px;
    right: 12px;
    z-index: 1200;
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 14px;
    padding: 12px 14px;
    border-radius: 14px;
    border: 1px solid rgba(255, 255, 255, 0.34);
    background: linear-gradient(115deg, #183153 0%, #1f3f67 58%, #2a5882 100%);
    box-shadow: 0 14px 30px rgba(10, 28, 52, 0.26);
    color: #f8fbff;
    animation: bannerDrop 260ms ease-out;
}

.portal-install-banner__copy {
    display: flex;
    align-items: center;
    gap: 10px;
    min-width: 0;
    font-family: 'Nunito', sans-serif;
    font-size: 14px;
    font-weight: 700;
    line-height: 1.35;
}

.portal-install-banner__copy span:last-child {
    display: inline-block;
}

.portal-install-banner__badge {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    width: 28px;
    height: 28px;
    border-radius: 9px;
    background: rgba(255, 255, 255, 0.16);
    box-shadow: inset 0 0 0 1px rgba(255, 255, 255, 0.2);
    flex-shrink: 0;
}

.portal-install-banner__actions {
    display: inline-flex;
    align-items: center;
    gap: 8px;
    flex-wrap: wrap;
    flex-shrink: 0;
}

.portal-install-banner__actions .portal-install-banner__button + .portal-install-banner__button {
    margin-left: 8px;
}

.portal-install-banner__button {
    border: none;
    border-radius: 999px;
    min-height: 34px;
    padding: 0 14px;
    font-family: 'Nunito', sans-serif;
    font-size: 13px;
    font-weight: 800;
    cursor: pointer;
    transition: transform .18s ease, box-shadow .18s ease, background-color .18s ease, color .18s ease;
}

.portal-install-banner__button:hover {
    transform: translateY(-1px);
}

.portal-install-banner__button--primary {
    background: #f4b51d;
    color: #12253e;
    box-shadow: 0 8px 16px rgba(245, 197, 24, 0.35);
}

.portal-install-banner__button--ghost {
    background: rgba(255, 255, 255, 0.12);
    color: #f8fbff;
    border: 1px solid rgba(255, 255, 255, 0.28);
}

.portal-feedback-button {
    position: fixed;
    right: 18px;
    bottom: 18px;
    z-index: 1150;
    width: 52px;
    height: 52px;
    border: none;
    border-radius: 14px;
    background: linear-gradient(145deg, #e8553e 0%, #cf3d2a 100%);
    color: #ffffff;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    box-shadow: 0 14px 24px rgba(190, 56, 36, 0.34);
    cursor: pointer;
    transition: transform .2s ease, box-shadow .2s ease;
}

.portal-feedback-button:hover {
    transform: translateY(-2px);
    box-shadow: 0 18px 26px rgba(190, 56, 36, 0.4);
}

.portal-overlay {
    position: fixed;
    inset: 0;
    z-index: 1300;
    background: rgba(9, 22, 39, 0.42);
    backdrop-filter: blur(4px);
    display: flex;
    align-items: center;
    justify-content: center;
    padding: 20px;
}

.portal-modal {
    width: min(100%, 640px);
    max-height: calc(100vh - 40px);
    overflow: auto;
    border-radius: 20px;
    border: 1px solid #dbe5ef;
    background:
        radial-gradient(circle at 85% -20%, rgba(246, 201, 91, 0.14), transparent 52%),
        radial-gradient(circle at 0% 100%, rgba(91, 158, 212, 0.12), transparent 48%),
        #fffefb;
    box-shadow: 0 24px 52px rgba(20, 43, 73, 0.24);
    padding: 24px;
}

.portal-modal__kicker {
    margin: 0 0 4px;
    font-family: 'Nunito', sans-serif;
    font-size: 11px;
    font-weight: 800;
    text-transform: uppercase;
    letter-spacing: 0.14em;
    color: #486581;
}

.portal-modal__title {
    margin: 0;
    font-family: 'Fredoka One', cursive;
    font-size: clamp(1.6rem, 3.4vw, 2rem);
    line-height: 1.12;
    color: #112744;
}

.portal-modal__close {
    width: 44px;
    height: 44px;
    border-radius: 999px;
    border: 1px solid #d7e2ed;
    background: rgba(255, 255, 255, 0.82);
    color: #34516e;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    cursor: pointer;
}

.portal-field-label {
    display: block;
    margin-bottom: 8px;
    font-family: 'Nunito', sans-serif;
    font-size: 14px;
    font-weight: 800;
    color: #243b53;
}

.portal-type-list {
    display: flex;
    flex-wrap: wrap;
    gap: 10px;
}

.portal-type-pill {
    position: relative;
    display: inline-flex;
    align-items: center;
    gap: 10px;
    min-height: 42px;
    padding: 0 14px;
    border-radius: 999px;
    border: 1px solid #cfdbea;
    background: #f9fbfe;
    color: #1f3349;
    font-family: 'Nunito', sans-serif;
    font-size: 14px;
    font-weight: 700;
    cursor: pointer;
    transition: all .18s ease;
}

.portal-type-pill__input {
    position: absolute;
    opacity: 0;
    pointer-events: none;
}

.portal-type-pill__dot {
    width: 16px;
    height: 16px;
    border-radius: 50%;
    border: 1.5px solid #7b8ea6;
    background: #ffffff;
    position: relative;
    flex-shrink: 0;
}

.portal-type-pill__dot::after {
    content: '';
    position: absolute;
    inset: 3px;
    border-radius: 50%;
    background: #2f68c6;
    transform: scale(0);
    transition: transform .18s ease;
}

.portal-type-pill.is-active {
    border-color: #a6c4f6;
    background: #eef5ff;
    box-shadow: inset 0 0 0 1px #bfd4fb;
}

.portal-type-pill.is-active .portal-type-pill__dot {
    border-color: #2f68c6;
}

.portal-type-pill.is-active .portal-type-pill__dot::after {
    transform: scale(1);
}

.portal-input {
    width: 100%;
    border-radius: 14px;
    border: 1px solid #cfdbea;
    background: #ffffff;
    font-family: 'Nunito', sans-serif;
    font-size: 15px;
    color: #13293f;
    padding: 12px 14px;
    box-sizing: border-box;
}

.portal-input:focus {
    outline: none;
    border-color: #3e85f0;
    box-shadow: 0 0 0 3px rgba(62, 133, 240, 0.18);
}

.portal-input--textarea {
    min-height: 130px;
    resize: vertical;
}

.portal-file-input {
    width: 100%;
    font-family: 'Nunito', sans-serif;
    font-size: 14px;
    color: #1f3349;
}

.portal-file-input::file-selector-button {
    border: 1px solid #cfdbea;
    border-radius: 10px;
    background: #f8fbff;
    color: #1f3349;
    font-family: 'Nunito', sans-serif;
    font-weight: 700;
    margin-right: 10px;
    padding: 8px 12px;
    cursor: pointer;
}

.portal-modal__footer {
    display: flex;
    align-items: center;
    justify-content: flex-end;
    gap: 10px;
    padding-top: 6px;
}

.portal-button {
    min-height: 44px;
    border-radius: 999px;
    padding: 0 18px;
    font-family: 'Nunito', sans-serif;
    font-size: 15px;
    font-weight: 800;
    cursor: pointer;
    transition: transform .18s ease, box-shadow .18s ease, background-color .18s ease;
}

.portal-button:disabled {
    cursor: not-allowed;
    opacity: 0.7;
}

.portal-button--ghost {
    border: 1px solid #d2dfec;
    background: #ffffff;
    color: #27405f;
}

.portal-button--primary {
    border: none;
    background: linear-gradient(140deg, #e8553e 0%, #ce3e2c 100%);
    color: #ffffff;
    box-shadow: 0 10px 18px rgba(192, 60, 40, 0.28);
}

.portal-button--primary:hover:not(:disabled),
.portal-button--ghost:hover:not(:disabled) {
    transform: translateY(-1px);
}

@keyframes bannerDrop {
    from {
        transform: translateY(-12px);
        opacity: 0;
    }

    to {
        transform: translateY(0);
        opacity: 1;
    }
}

@media (max-width: 760px) {
    .portal-install-banner {
        flex-direction: column;
        align-items: stretch;
    }

    .portal-install-banner__actions {
        display: flex;
        justify-content: flex-end;
    }

    .portal-install-banner__button {
        min-width: 96px;
    }

    .portal-modal {
        padding: 18px;
    }

    .portal-type-list {
        gap: 8px;
    }

    .portal-type-pill {
        flex: 1 1 calc(50% - 8px);
        min-width: 140px;
        justify-content: center;
    }
}
</style>
