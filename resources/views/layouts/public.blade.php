<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="theme-color" content="#0d3b66">
    @if(!empty($seoTags))
        {!! $seoTags !!}
    @endif
    @stack('seo')

    @if(app()->environment('production') && (config('services.ga.measurement_id') ?: config('services.google_analytics.measurement_id')))
        <link rel="preconnect" href="https://www.googletagmanager.com">
        <link rel="preconnect" href="https://www.google-analytics.com">
        <script async src="https://www.googletagmanager.com/gtag/js?id={{ config('services.ga.measurement_id') ?: config('services.google_analytics.measurement_id') }}"></script>
        <script>
            window.dataLayer = window.dataLayer || [];
            function gtag(){dataLayer.push(arguments);}
            window.gtag = window.gtag || gtag;

            const consentKey = 'edubridge_cookie_consent';
            let consentValue = null;

            try {
                consentValue = window.localStorage ? localStorage.getItem(consentKey) : null;
            } catch (error) {
                consentValue = null;
            }

            gtag('js', new Date());
            gtag('consent', 'default', {
                analytics_storage: 'denied',
                ad_storage: 'denied',
                ad_user_data: 'denied',
                ad_personalization: 'denied',
            });

            if (consentValue === 'accepted') {
                gtag('consent', 'update', {
                    analytics_storage: 'granted',
                    ad_storage: 'denied',
                    ad_user_data: 'denied',
                    ad_personalization: 'denied',
                });
            }

            gtag('config', '{{ config('services.ga.measurement_id') ?: config('services.google_analytics.measurement_id') }}', {
                anonymize_ip: true,
            });
        </script>
    @endif

    <link rel="icon" href="{{ asset('favicon.ico') }}" type="image/x-icon">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Fraunces:wght@500;700&family=Manrope:wght@400;500;600;700&display=swap" rel="stylesheet">

    @vite(['resources/css/app.css'])

    <style>
        :root {
            --bg: #f8fbff;
            --ink: #102a43;
            --brand: #0d3b66;
            --brand-alt: #f95738;
            --surface: #ffffff;
            --muted: #52667a;
            --line: #d9e6f2;
        }

        * {
            box-sizing: border-box;
        }

        body.public-site {
            margin: 0;
            font-family: 'Manrope', sans-serif;
            color: var(--ink);
            background:
                radial-gradient(1200px 600px at 90% -20%, rgba(13, 59, 102, 0.09), transparent 70%),
                radial-gradient(900px 420px at 0% 15%, rgba(249, 87, 56, 0.12), transparent 70%),
                var(--bg);
            min-height: 100vh;
        }

        .public-wrap {
            width: min(1120px, calc(100% - 32px));
            margin: 0 auto;
        }

        .public-header {
            position: sticky;
            top: 0;
            z-index: 40;
            backdrop-filter: blur(10px);
            background: rgba(248, 251, 255, 0.74);
            border-bottom: 1px solid transparent;
            transition: background .35s ease, border-color .35s ease, box-shadow .35s ease;
        }

        .public-header.is-scrolled {
            background: rgba(248, 251, 255, 0.95);
            border-bottom-color: rgba(217, 230, 242, 0.9);
            box-shadow: 0 10px 24px rgba(16, 42, 67, 0.08);
        }

        body.landing-bloom .public-header {
            background: rgba(248, 251, 255, 0.58);
        }

        body.landing-bloom .public-header.is-scrolled {
            background: rgba(248, 251, 255, 0.91);
        }

        .nav-shell {
            min-height: 76px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 16px;
        }

        .brand-mark {
            text-decoration: none;
            color: var(--ink);
            display: inline-flex;
            align-items: center;
            gap: 10px;
            font-weight: 700;
            letter-spacing: 0.01em;
        }

        .brand-title {
            font-size: 18px;
            font-weight: 700;
        }

        .nav-mobile-panel {
            margin-left: auto;
            display: flex;
            align-items: center;
            gap: 20px;
        }

        .nav-links {
            display: flex;
            align-items: center;
            gap: 22px;
        }

        .nav-links a {
            color: var(--ink);
            text-decoration: none;
            font-weight: 600;
            font-size: 14px;
        }

        .nav-actions {
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .btn-pill {
            display: inline-flex;
            justify-content: center;
            align-items: center;
            min-height: 42px;
            padding: 0 18px;
            border-radius: 999px;
            border: 1px solid transparent;
            text-decoration: none;
            font-weight: 700;
            font-size: 14px;
            transition: transform .2s ease, box-shadow .2s ease, background-color .2s ease, color .2s ease;
            transform: translate3d(var(--mx, 0px), calc(var(--my, 0px) + var(--lift, 0px)), 0);
        }

        .btn-pill:hover {
            --lift: -1px;
        }

        .btn-pill.primary {
            background: var(--brand);
            color: #fff;
            box-shadow: 0 12px 26px rgba(13, 59, 102, 0.25);
        }

        .btn-pill.secondary {
            background: #fff;
            border-color: var(--line);
            color: var(--ink);
        }

        .btn-pill.primary.magnetic-cta {
            position: relative;
            isolation: isolate;
        }

        .btn-pill.primary.magnetic-cta::before {
            content: '';
            position: absolute;
            inset: -12px;
            border-radius: inherit;
            background: radial-gradient(
                circle at var(--gx, 50%) var(--gy, 50%),
                rgba(132, 194, 255, 0.56),
                rgba(13, 59, 102, 0) 70%
            );
            opacity: 0;
            transition: opacity .24s ease;
            z-index: -1;
            pointer-events: none;
        }

        .btn-pill.primary.magnetic-cta:hover::before {
            opacity: 1;
        }

        .menu-toggle {
            display: none;
            border: 1px solid var(--line);
            background: #fff;
            border-radius: 10px;
            min-height: 40px;
            min-width: 40px;
            align-items: center;
            justify-content: center;
            color: var(--ink);
            gap: 4px;
            flex-direction: column;
            transition: border-color .25s ease, box-shadow .25s ease, transform .25s ease;
            cursor: pointer;
        }

        .menu-toggle span {
            display: block;
            width: 18px;
            height: 2px;
            border-radius: 999px;
            background: currentColor;
            transition: transform .3s cubic-bezier(.2, .9, .24, 1), opacity .22s ease;
        }

        .menu-toggle:hover {
            transform: translateY(-1px);
            box-shadow: 0 10px 22px rgba(16, 42, 67, 0.12);
            border-color: #bfd5eb;
        }

        .nav-shell[data-open="true"] .menu-toggle span:nth-child(1) {
            transform: translateY(6px) rotate(45deg);
        }

        .nav-shell[data-open="true"] .menu-toggle span:nth-child(2) {
            opacity: 0;
        }

        .nav-shell[data-open="true"] .menu-toggle span:nth-child(3) {
            transform: translateY(-6px) rotate(-45deg);
        }

        .public-main {
            padding: 38px 0 72px;
        }

        .public-footer {
            border-top: 1px solid var(--line);
            background: #fff;
            margin-top: 64px;
        }

        body.landing-bloom .public-footer {
            position: relative;
            border-top-color: rgba(132, 178, 226, 0.52);
            background: linear-gradient(140deg, #0f3357 0%, #164e80 55%, #0d3b66 100%);
            overflow: hidden;
        }

        body.landing-bloom .public-footer::before {
            content: '';
            position: absolute;
            inset: 0;
            background:
                radial-gradient(circle at 12% 18%, rgba(124, 193, 255, 0.24) 0 2px, transparent 3px),
                radial-gradient(circle at 78% 40%, rgba(255, 255, 255, 0.12) 0 2px, transparent 3px),
                radial-gradient(circle at 44% 70%, rgba(249, 87, 56, 0.22) 0 2px, transparent 3px);
            background-size: 18px 18px, 20px 20px, 22px 22px;
            opacity: 0.42;
            pointer-events: none;
        }

        .footer-shell {
            padding: 30px 0;
            display: flex;
            justify-content: space-between;
            gap: 16px;
            flex-wrap: wrap;
            align-items: center;
        }

        body.landing-bloom .footer-shell {
            position: relative;
            color: #e8f1ff;
        }

        .footer-links {
            display: flex;
            gap: 16px;
            flex-wrap: wrap;
        }

        .footer-links a {
            color: var(--muted);
            text-decoration: none;
            font-weight: 600;
        }

        body.landing-bloom .footer-links a {
            color: #d8e9ff;
            transition: color .2s ease;
        }

        body.landing-bloom .footer-links a:hover {
            color: #ffffff;
        }

        .cookie-consent {
            position: fixed;
            left: 16px;
            right: 16px;
            bottom: 16px;
            z-index: 60;
            background: #0f172a;
            color: #e2e8f0;
            border-radius: 16px;
            border: 1px solid #334155;
            padding: 14px;
            display: none;
            box-shadow: 0 16px 42px rgba(2, 6, 23, 0.44);
        }

        .cookie-consent p {
            margin: 0;
            font-size: 14px;
            line-height: 1.45;
        }

        .cookie-actions {
            margin-top: 12px;
            display: flex;
            gap: 8px;
            flex-wrap: wrap;
        }

        .cookie-actions button {
            border: 1px solid transparent;
            border-radius: 999px;
            min-height: 36px;
            padding: 0 14px;
            font-weight: 700;
            cursor: pointer;
        }

        .cookie-actions .accept {
            background: #22c55e;
            color: #052e16;
        }

        .cookie-actions .reject {
            background: #1e293b;
            color: #f8fafc;
            border-color: #334155;
        }

        @media (max-width: 900px) {
            .menu-toggle {
                display: inline-flex;
            }

            .nav-shell {
                flex-wrap: wrap;
                padding: 10px 0;
            }

            .nav-mobile-panel {
                width: 100%;
                order: 3;
                margin-left: 0;
                flex-direction: column;
                align-items: stretch;
                gap: 14px;
                max-height: 0;
                opacity: 0;
                transform: translateY(-12px) scale(0.98);
                overflow: hidden;
                transition:
                    max-height .45s cubic-bezier(.2, .95, .24, 1),
                    opacity .28s ease,
                    transform .4s cubic-bezier(.2, .95, .24, 1);
            }

            .nav-shell[data-open="true"] .nav-mobile-panel {
                max-height: 320px;
                opacity: 1;
                transform: translateY(0) scale(1);
                padding: 6px 0 12px;
            }

            .nav-links {
                gap: 10px;
                width: 100%;
                flex-direction: column;
                align-items: flex-start;
            }

            .nav-actions {
                width: 100%;
                gap: 8px;
                flex-direction: column;
            }

            .nav-actions .btn-pill {
                width: 100%;
            }
        }
    </style>
</head>
<body class="public-site @yield('body_class')">
    <header class="public-header" id="public-header">
        <div class="public-wrap nav-shell" data-open="false" id="public-nav-shell">
            <a href="{{ route('landing') }}" class="brand-mark" aria-label="EduBridge home">
                <img src="{{ asset('images/logo.png') }}" class="logo" alt="EduBridge Logo" style="width: 38px; height: 38px; border-radius: 11px; object-fit: cover;">
                <span class="brand-title">EduBridge</span>
            </a>

            <button class="menu-toggle" id="public-nav-toggle" type="button" aria-label="Toggle menu" aria-expanded="false" aria-controls="public-nav-panel">
                <span></span>
                <span></span>
                <span></span>
            </button>

            <div class="nav-mobile-panel" id="public-nav-panel">
                <nav class="nav-links" aria-label="Main navigation">
                    <a href="{{ route('landing') }}">Home</a>
                    <a href="{{ route('teachers.index') }}">Find Teachers</a>
                    <a href="{{ route('about') }}">About</a>
                    <a href="{{ route('contact') }}">Contact</a>
                </nav>

                <div class="nav-actions">
                    <a class="btn-pill secondary" href="{{ route('login') }}">Log In</a>
                    <a class="btn-pill primary magnetic-cta js-magnetic" href="{{ route('student.register') }}">Get Started</a>
                </div>
            </div>
        </div>
    </header>

    <main class="public-main">
        <div class="public-wrap">
            @yield('content')
        </div>
    </main>

    <footer class="public-footer">
        <div class="public-wrap footer-shell">
            <div>
                <strong>EduBridge</strong>
                <div style="font-size: 13px; color: var(--muted); margin-top: 4px;">Learning with trusted teachers, everywhere.</div>
            </div>
            <div class="footer-links">
                <a href="{{ route('about') }}">About</a>
                <a href="{{ route('privacy-policy') }}">Privacy</a>
                <a href="{{ route('terms') }}">Terms</a>
                <a href="{{ route('contact') }}">Contact</a>
                <a href="{{ route('sitemap') }}">Sitemap</a>
            </div>
        </div>
    </footer>

    <div id="cookie-consent" class="cookie-consent" role="dialog" aria-live="polite" aria-label="Cookie preferences">
        <p>
            EduBridge uses essential cookies and optional analytics cookies to understand usage and improve learning journeys.
        </p>
        <div class="cookie-actions">
            <button type="button" class="accept" id="cookie-accept">Accept analytics</button>
            <button type="button" class="reject" id="cookie-reject">Reject analytics</button>
        </div>
    </div>

    <script>
        (function () {
            const header = document.getElementById('public-header');
            const navShell = document.getElementById('public-nav-shell');
            const toggle = document.getElementById('public-nav-toggle');
            const mobileQuery = window.matchMedia('(max-width: 900px)');

            const setMenuState = function (isOpen) {
                if (!navShell) {
                    return;
                }

                navShell.setAttribute('data-open', isOpen ? 'true' : 'false');

                if (toggle) {
                    toggle.setAttribute('aria-expanded', isOpen ? 'true' : 'false');
                }
            };

            if (toggle && navShell) {
                toggle.addEventListener('click', function () {
                    const isOpen = navShell.getAttribute('data-open') === 'true';
                    setMenuState(!isOpen);
                });

                navShell.querySelectorAll('a').forEach((link) => {
                    link.addEventListener('click', function () {
                        if (mobileQuery.matches) {
                            setMenuState(false);
                        }
                    });
                });
            }

            const updateHeaderState = function () {
                if (!header) {
                    return;
                }

                header.classList.toggle('is-scrolled', window.scrollY > 18);
            };

            updateHeaderState();
            window.addEventListener('scroll', updateHeaderState, { passive: true });
            window.addEventListener('resize', function () {
                if (!mobileQuery.matches) {
                    setMenuState(false);
                }

                updateHeaderState();
            }, { passive: true });

            const prefersReducedMotion = window.matchMedia('(prefers-reduced-motion: reduce)').matches;

            if (!prefersReducedMotion && !('ontouchstart' in window)) {
                document.querySelectorAll('.js-magnetic').forEach((element) => {
                    const reset = function () {
                        element.style.setProperty('--mx', '0px');
                        element.style.setProperty('--my', '0px');
                        element.style.setProperty('--gx', '50%');
                        element.style.setProperty('--gy', '50%');
                    };

                    reset();

                    element.addEventListener('mousemove', function (event) {
                        const bounds = element.getBoundingClientRect();
                        const offsetX = event.clientX - bounds.left;
                        const offsetY = event.clientY - bounds.top;
                        const ratioX = offsetX / bounds.width;
                        const ratioY = offsetY / bounds.height;

                        element.style.setProperty('--mx', `${(ratioX - 0.5) * 10}px`);
                        element.style.setProperty('--my', `${(ratioY - 0.5) * 8}px`);
                        element.style.setProperty('--gx', `${Math.max(0, Math.min(100, ratioX * 100))}%`);
                        element.style.setProperty('--gy', `${Math.max(0, Math.min(100, ratioY * 100))}%`);
                    });

                    element.addEventListener('mouseleave', reset);
                });
            }

            const storageKey = 'edubridge_cookie_consent';
            let consent = null;

            try {
                consent = window.localStorage ? localStorage.getItem(storageKey) : null;
            } catch (error) {
                consent = null;
            }
            const banner = document.getElementById('cookie-consent');
            const acceptButton = document.getElementById('cookie-accept');
            const rejectButton = document.getElementById('cookie-reject');

            const updateGoogleConsent = function (granted) {
                if (typeof window.gtag !== 'function') {
                    return;
                }

                window.gtag('consent', 'update', {
                    analytics_storage: granted ? 'granted' : 'denied',
                    ad_storage: 'denied',
                    ad_user_data: 'denied',
                    ad_personalization: 'denied',
                });
            };

            if (!consent && banner) {
                banner.style.display = 'block';
            }

            if (acceptButton) {
                acceptButton.addEventListener('click', function () {
                    try {
                        localStorage.setItem(storageKey, 'accepted');
                    } catch (error) {
                        return;
                    }

                    updateGoogleConsent(true);
                    if (banner) {
                        banner.style.display = 'none';
                    }
                });
            }

            if (rejectButton) {
                rejectButton.addEventListener('click', function () {
                    try {
                        localStorage.setItem(storageKey, 'rejected');
                    } catch (error) {
                        return;
                    }

                    updateGoogleConsent(false);
                    if (banner) {
                        banner.style.display = 'none';
                    }
                });
            }

            if (consent === 'accepted') {
                updateGoogleConsent(true);
            }

            if (consent === 'rejected') {
                updateGoogleConsent(false);
            }
        })();
    </script>
</body>
</html>
