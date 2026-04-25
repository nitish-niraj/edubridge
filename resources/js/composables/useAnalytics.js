const CONSENT_STORAGE_KEY = 'edubridge_cookie_consent';

const CONSENT_GRANTED = {
    analytics_storage: 'granted',
    ad_storage: 'denied',
    ad_user_data: 'denied',
    ad_personalization: 'denied',
};

const CONSENT_DENIED = {
    analytics_storage: 'denied',
    ad_storage: 'denied',
    ad_user_data: 'denied',
    ad_personalization: 'denied',
};

const readConsent = () => {
    if (typeof window === 'undefined' || !window.localStorage) {
        return null;
    }

    try {
        return localStorage.getItem(CONSENT_STORAGE_KEY);
    } catch {
        return null;
    }
};

const updateGoogleConsent = (granted) => {
    if (typeof window === 'undefined' || typeof window.gtag !== 'function') {
        return;
    }

    window.gtag('consent', 'update', granted ? CONSENT_GRANTED : CONSENT_DENIED);
};

export function useAnalytics() {
    const hasConsent = () => readConsent() === 'accepted';

    const syncConsent = () => {
        const consent = readConsent();

        if (consent === 'accepted') {
            updateGoogleConsent(true);
            return;
        }

        if (consent === 'rejected') {
            updateGoogleConsent(false);
        }
    };

    const grantConsent = () => {
        if (typeof window === 'undefined' || !window.localStorage) {
            return;
        }

        try {
            localStorage.setItem(CONSENT_STORAGE_KEY, 'accepted');
        } catch {
            return;
        }

        updateGoogleConsent(true);
    };

    const denyConsent = () => {
        if (typeof window === 'undefined' || !window.localStorage) {
            return;
        }

        try {
            localStorage.setItem(CONSENT_STORAGE_KEY, 'rejected');
        } catch {
            return;
        }

        updateGoogleConsent(false);
    };

    const trackEvent = (eventName, payload = {}) => {
        if (!hasConsent() || typeof window === 'undefined' || typeof window.gtag !== 'function') {
            return;
        }

        window.gtag('event', eventName, payload);
    };

    return {
        hasConsent,
        syncConsent,
        grantConsent,
        denyConsent,
        trackEvent,
        consentStorageKey: CONSENT_STORAGE_KEY,
    };
}
