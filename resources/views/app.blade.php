<!DOCTYPE html>
@auth
    @php $portal = auth()->user()->role; @endphp
@else
    @php $portal = 'student'; @endphp
@endauth
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" data-portal="{{ $portal }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="theme-color" content="#E8553E">
        <meta name="app-base-url" content="{{ rtrim(url('/'), '/') }}">
        <link rel="manifest" href="{{ asset('manifest.json') }}">
        @stack('seo')

        @if($portal === 'student')
        <link rel="preconnect" href="https://fonts.googleapis.com">
        <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
        <link href="https://fonts.googleapis.com/css2?family=Fredoka+One&family=Nunito:wght@400;600;700;800&display=swap" rel="stylesheet">
        @endif

        @if($portal === 'admin')
        <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
        @endif

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

        <title inertia>{{ config('app.name', 'Laravel') }}</title>

        <!-- Scripts -->
        @routes
        @vite(['resources/js/app.js', "resources/js/Pages/{$page['component']}.vue"])
        @inertiaHead
    </head>
    <body class="antialiased">
        @inertia
    </body>
</html>
