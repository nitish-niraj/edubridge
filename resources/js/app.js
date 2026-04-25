import './bootstrap';
import '../css/app.css';

import * as Sentry from '@sentry/vue';
import Chart from 'chart.js/auto';
import { createApp, defineAsyncComponent, h, ref } from 'vue';
import { createInertiaApp, router } from '@inertiajs/vue3';
import { ZiggyVue } from '../../vendor/tightenco/ziggy';
import { registerSW } from 'virtual:pwa-register';
import CookieConsent from '@/Components/Shared/CookieConsent.vue';
import ToastNotification from '@/Components/Student/UI/ToastNotification.vue';
import PageLoader from '@/Components/Shared/PageLoader.vue';
import { useAnalytics } from '@/composables/useAnalytics';
import { refreshScrollReveal } from '@/composables/useScrollReveal';
import { MotionPlugin } from '@vueuse/motion';

const appName = import.meta.env.VITE_APP_NAME || 'Laravel';
const pages = import.meta.glob('./Pages/**/*.vue');

const AsyncPageLoadError = {
    props: {
        error: {
            type: [Object, String],
            default: null,
        },
    },
    setup(props) {
        return () => h('div', {
            style: {
                minHeight: '100vh',
                display: 'grid',
                placeItems: 'center',
                padding: '24px',
                background: '#FFF8F0',
            },
        }, [
            h('div', {
                style: {
                    width: '100%',
                    maxWidth: '540px',
                    background: '#fff',
                    borderRadius: '16px',
                    boxShadow: '0 10px 30px rgba(17, 24, 39, 0.08)',
                    padding: '24px',
                    border: '1px solid #f5d8cf',
                },
            }, [
                h('h1', {
                    style: {
                        margin: '0 0 10px',
                        fontSize: '24px',
                        color: '#E8553E',
                        fontFamily: "'Fredoka One', cursive",
                    },
                }, 'Page Load Interrupted'),
                h('p', {
                    style: {
                        margin: '0 0 16px',
                        color: '#4b5563',
                        fontFamily: "'Nunito', sans-serif",
                        fontSize: '15px',
                        lineHeight: '1.45',
                    },
                }, 'The page assets did not finish loading. Reload to recover.'),
                props.error
                    ? h('pre', {
                        style: {
                            margin: '0 0 16px',
                            padding: '10px 12px',
                            borderRadius: '10px',
                            background: '#fff1ec',
                            color: '#9f1239',
                            whiteSpace: 'pre-wrap',
                            fontSize: '12px',
                            fontFamily: 'ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, monospace',
                        },
                    }, String(props.error?.message || props.error))
                    : null,
                h('button', {
                    type: 'button',
                    style: {
                        border: 'none',
                        borderRadius: '999px',
                        background: '#E8553E',
                        color: '#fff',
                        fontFamily: "'Fredoka One', cursive",
                        fontSize: '16px',
                        padding: '10px 20px',
                        cursor: 'pointer',
                    },
                    onClick: () => window.location.reload(),
                }, 'Reload page'),
            ]),
        ]);
    },
};

const resolvePathname = (url = '') => {
    if (!url) return '/';

    try {
        return new URL(url, window.location.origin).pathname || '/';
    } catch {
        return '/';
    }
};

window.Chart = Chart;

const isLocalHost = ['localhost', '127.0.0.1', '::1'].includes(window.location.hostname);

if (isLocalHost && 'serviceWorker' in navigator) {
    navigator.serviceWorker.getRegistrations().then((registrations) => {
        registrations.forEach((registration) => {
            registration.unregister();
        });
    });
}

if (import.meta.env.PROD && !isLocalHost) {
    registerSW({ immediate: true });
}

const applyPortalBodyAttribute = (pageProps = {}) => {
    const role = pageProps?.auth?.user?.role;
    const allowed = ['student', 'teacher', 'admin'];
    const root = document.documentElement;

    if (allowed.includes(role)) {
        document.body.setAttribute('data-portal', role);
        root.setAttribute('data-portal', role);
        return;
    }

    document.body.removeAttribute('data-portal');
    root.removeAttribute('data-portal');
};

const validationFieldSelector = [
    'input:not([type="hidden"]):not([type="submit"]):not([type="button"]):not([type="checkbox"]):not([type="radio"])',
    'textarea',
    'select',
].join(',');

const validationErrorSelector = [
    '.text-red-600',
    '.s-input-error',
    '.validation-error-text',
    '[data-validation-error]',
].join(',');

let activeSubmitButton = null;

const triggerValidationShake = (field, delay = 0) => {
    window.setTimeout(() => {
        if (!field || !(field instanceof HTMLElement) || !field.isConnected) {
            return;
        }

        field.classList.remove('validation-shake');
        // Force reflow so repeated shakes replay.
        void field.offsetWidth;
        field.classList.add('validation-shake');
    }, delay);
};

const applyErrorMessageMotion = (root = document) => {
    root.querySelectorAll(validationErrorSelector).forEach((node) => {
        if (!(node instanceof HTMLElement)) {
            return;
        }

        const text = node.textContent?.trim() || '';
        if (!text) {
            return;
        }

        node.classList.add('validation-error-message');
        window.requestAnimationFrame(() => {
            node.classList.add('validation-error-visible');
        });
    });
};

const collectErroredFields = (root = document) => {
    const erroredFields = [];

    root.querySelectorAll(validationErrorSelector).forEach((node) => {
        if (!(node instanceof HTMLElement)) {
            return;
        }

        const text = node.textContent?.trim() || '';
        if (!text) {
            return;
        }

        const field = node.closest('div')?.querySelector(validationFieldSelector);
        if (!field || !(field instanceof HTMLElement)) {
            return;
        }

        if (!erroredFields.includes(field)) {
            erroredFields.push(field);
        }
    });

    return erroredFields;
};

const fieldShouldValidateOnBlur = (field) => {
    if (!(field instanceof HTMLInputElement || field instanceof HTMLTextAreaElement || field instanceof HTMLSelectElement)) {
        return false;
    }

    if (field instanceof HTMLInputElement && ['radio', 'checkbox', 'hidden', 'submit', 'button', 'file'].includes(field.type)) {
        return false;
    }

    if (field.disabled || field.readOnly) {
        return false;
    }

    const value = `${field.value || ''}`.trim();
    const meaningfulType = field instanceof HTMLInputElement && ['email', 'tel', 'password', 'url'].includes(field.type);

    return field.required || value.length > 0 || meaningfulType;
};

const resolveValidationGroup = (field) => {
    return (
        field.closest('[data-validation-group], .s-input-wrapper, .field-block, .form-group, .input-group, label')
        || field.parentElement
        || field
    );
};

const clearAutoValidationError = (field) => {
    const group = resolveValidationGroup(field);
    const autoNode = group.querySelector('[data-validation-error="auto"]');

    if (autoNode) {
        autoNode.remove();
    }
};

const upsertAutoValidationError = (field, message) => {
    const group = resolveValidationGroup(field);

    const manualErrorNode = Array.from(group.querySelectorAll(validationErrorSelector)).find((node) => {
        if (!(node instanceof HTMLElement)) {
            return false;
        }

        if (node.getAttribute('data-validation-error') === 'auto') {
            return false;
        }

        return Boolean(node.textContent?.trim());
    });

    if (manualErrorNode) {
        return;
    }

    let autoNode = group.querySelector('[data-validation-error="auto"]');

    if (!autoNode) {
        autoNode = document.createElement('p');
        autoNode.setAttribute('data-validation-error', 'auto');
        autoNode.className = 'validation-error-text validation-error-message';
        group.appendChild(autoNode);
    }

    autoNode.textContent = message || 'Please enter a valid value.';
    autoNode.classList.add('validation-error-visible');
};

const clearAllAutoValidationErrors = (root = document) => {
    root.querySelectorAll('[data-validation-error="auto"]').forEach((node) => {
        node.remove();
    });
};

const hasVisibleFormErrors = (form) => {
    if (!(form instanceof HTMLFormElement)) {
        return false;
    }

    return Array.from(form.querySelectorAll(validationErrorSelector)).some((node) => {
        if (!(node instanceof HTMLElement)) {
            return false;
        }

        if (!node.textContent?.trim()) {
            return false;
        }

        return window.getComputedStyle(node).display !== 'none';
    });
};

const setupValidationFeedback = () => {
    if (typeof window === 'undefined' || window.__edubridgeValidationFeedbackSetup) {
        return;
    }

    window.__edubridgeValidationFeedbackSetup = true;

    document.addEventListener('focusout', (event) => {
        const field = event.target;

        if (!(field instanceof HTMLInputElement || field instanceof HTMLTextAreaElement || field instanceof HTMLSelectElement)) {
            return;
        }

        if (!fieldShouldValidateOnBlur(field)) {
            field.classList.remove('validation-live-valid', 'validation-live-invalid');
            clearAutoValidationError(field);
            return;
        }

        const isValid = field.checkValidity();
        field.classList.toggle('validation-live-valid', isValid);
        field.classList.toggle('validation-live-invalid', !isValid);

        if (isValid) {
            clearAutoValidationError(field);
        } else {
            upsertAutoValidationError(field, field.validationMessage);
        }
    }, true);

    document.addEventListener('submit', (event) => {
        const form = event.target;

        if (!(form instanceof HTMLFormElement)) {
            return;
        }

        applyErrorMessageMotion(form);

        const fields = Array.from(form.querySelectorAll(validationFieldSelector)).filter((field) => {
            if (!(field instanceof HTMLInputElement || field instanceof HTMLTextAreaElement || field instanceof HTMLSelectElement)) {
                return false;
            }

            return !field.disabled && !field.readOnly;
        });

        const invalidFields = fields.filter((field) => !field.checkValidity());

        invalidFields.forEach((field, index) => {
            triggerValidationShake(field, index * 50);
            field.classList.add('validation-live-invalid');
            field.classList.remove('validation-live-valid');
            upsertAutoValidationError(field, field.validationMessage);
        });

        const submitter = event.submitter;
        if (submitter instanceof HTMLElement) {
            activeSubmitButton = submitter;

            activeSubmitButton.style.setProperty('--validation-submit-width', `${activeSubmitButton.offsetWidth}px`);
            activeSubmitButton.style.setProperty('--validation-submit-height', `${activeSubmitButton.offsetHeight}px`);
            activeSubmitButton.classList.remove('validation-submit-success');

            if (invalidFields.length === 0) {
                activeSubmitButton.classList.add('validation-submit-loading');
            }
        }

        if (invalidFields.length > 0) {
            activeSubmitButton?.classList.remove('validation-submit-loading');
            activeSubmitButton = null;
        }
    }, true);

    const observer = new MutationObserver(() => {
        applyErrorMessageMotion(document);
    });

    observer.observe(document.body, {
        childList: true,
        subtree: true,
        characterData: true,
    });

    applyErrorMessageMotion(document);
};

router.on('navigate', (event) => {
    applyPortalBodyAttribute(event.detail.page.props);
    clearAllAutoValidationErrors(document);
    applyErrorMessageMotion(document);
});

const { syncConsent } = useAnalytics();

createInertiaApp({
    title: (title) => `${title} - ${appName}`,
    resolve: (name) => {
        const pagePath = `./Pages/${name}.vue`;
        const importer = pages[pagePath];

        if (!importer) {
            throw new Error(`Unknown page component: ${name}`);
        }

        return defineAsyncComponent({
            loader: async () => {
                const module = await importer();
                return module.default;
            },
            delay: 0,
            timeout: 20000,
            errorComponent: AsyncPageLoadError,
            onError: (error, retry, fail, attempts) => {
                const message = String(error?.message || error || '');
                const isChunkLikeError = /loading chunk|failed to fetch|importing a module script|dynamic import/i.test(message);

                if (isChunkLikeError && attempts <= 2) {
                    retry();
                    return;
                }

                fail(error);
            },
        });
    },
    setup({ el, App, props, plugin }) {
        const initialPath = resolvePathname(props.initialPage?.url || window.location.href);
        const pageLoaderVisible = ref(true);
        const pageLoaderProgress = ref(0);

        let pageLoaderJumpTimer = null;
        let pageLoaderIncrementTimer = null;
        let pageLoaderHideTimer = null;
        let pageLoaderStartedAt = 0;
        let latestResolvedPath = initialPath;

        const clearPageLoaderTimers = () => {
            if (pageLoaderJumpTimer) {
                window.clearTimeout(pageLoaderJumpTimer);
                pageLoaderJumpTimer = null;
            }

            if (pageLoaderIncrementTimer) {
                window.clearInterval(pageLoaderIncrementTimer);
                pageLoaderIncrementTimer = null;
            }

            if (pageLoaderHideTimer) {
                window.clearTimeout(pageLoaderHideTimer);
                pageLoaderHideTimer = null;
            }
        };

        const beginPageLoader = () => {
            clearPageLoaderTimers();

            pageLoaderVisible.value = true;
            pageLoaderProgress.value = 0;
            pageLoaderStartedAt = performance.now();

            pageLoaderJumpTimer = window.setTimeout(() => {
                pageLoaderProgress.value = 30;

                pageLoaderIncrementTimer = window.setInterval(() => {
                    if (pageLoaderProgress.value >= 85) {
                        return;
                    }

                    const step = pageLoaderProgress.value < 70 ? 2.4 : 1.1;
                    pageLoaderProgress.value = Math.min(85, pageLoaderProgress.value + step);
                }, 130);
            }, 100);
        };

        const finishPageLoader = () => {
            const resolveProgress = () => {
                clearPageLoaderTimers();
                pageLoaderProgress.value = 100;

                pageLoaderHideTimer = window.setTimeout(() => {
                    pageLoaderVisible.value = false;
                    pageLoaderProgress.value = 0;
                }, 180);
            };

            const elapsed = performance.now() - pageLoaderStartedAt;
            if (elapsed < 100) {
                pageLoaderHideTimer = window.setTimeout(resolveProgress, 100 - elapsed);
                return;
            }

            resolveProgress();
        };

        beginPageLoader();

        router.on('navigate', (event) => {
            const page = event.detail.page;
            latestResolvedPath = resolvePathname(page.url);
            refreshScrollReveal();
        });

        router.on('start', (event) => {
            const nextPath = resolvePathname(event.detail.visit?.url || '');

            if (nextPath && nextPath !== latestResolvedPath) {
                beginPageLoader();
            }
        });

        router.on('finish', () => {
            const submitForm = activeSubmitButton?.closest('form');

            if (activeSubmitButton && activeSubmitButton.isConnected) {
                activeSubmitButton.classList.remove('validation-submit-loading');

                if (!hasVisibleFormErrors(submitForm)) {
                    activeSubmitButton.classList.add('validation-submit-success');

                    window.setTimeout(() => {
                        activeSubmitButton?.classList.remove('validation-submit-success');
                        activeSubmitButton = null;
                    }, 620);
                } else {
                    activeSubmitButton = null;
                }
            }

            const erroredFields = collectErroredFields(document);
            erroredFields.forEach((field, index) => {
                triggerValidationShake(field, index * 50);
            });

            finishPageLoader();
        });

        const app = createApp({
            render: () => [
                h(PageLoader, {
                    visible: pageLoaderVisible.value,
                    progress: pageLoaderProgress.value,
                }),
                h('div', { class: 'portal-page-transition-layer' }, [
                    h('div', { class: 'portal-page-frame' }, [h(App, props)]),
                ]),
                h(CookieConsent),
                h(ToastNotification),
            ],
        }).use(plugin)
            .use(ZiggyVue)
            .use(MotionPlugin);

        if (import.meta.env.PROD && import.meta.env.VITE_SENTRY_DSN) {
            Sentry.init({
                app,
                dsn: import.meta.env.VITE_SENTRY_DSN,
                tracesSampleRate: Number(import.meta.env.VITE_SENTRY_TRACES_SAMPLE_RATE || 0.1),
                environment: import.meta.env.MODE,
                release: import.meta.env.VITE_APP_VERSION || undefined,
                integrations: [Sentry.browserTracingIntegration()],
            });
        }

        const mounted = app.mount(el);

        window.requestAnimationFrame(() => {
            refreshScrollReveal();
            finishPageLoader();
        });

        applyPortalBodyAttribute(props.initialPage?.props);
        syncConsent();
        setupValidationFeedback();

        return mounted;
    },
    progress: false,
});
