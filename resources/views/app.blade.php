<!DOCTYPE html>
@auth
    @php $portal = auth()->user()->role; @endphp
@else
    @php $portal = 'student'; @endphp
@endauth
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" data-portal="{{ $portal }}">
    <head>
        <meta charset="utf-8">
        <!-- Google tag (gtag.js) -->
        <script async src="https://www.googletagmanager.com/gtag/js?id={{ config('services.ga.measurement_id') ?: config('services.google_analytics.measurement_id') }}"></script>
        <script>
          window.dataLayer = window.dataLayer || [];
          function gtag(){dataLayer.push(arguments);}
          gtag('js', new Date());

          gtag('config', '{{ config('services.ga.measurement_id') ?: config('services.google_analytics.measurement_id') }}');
        </script>
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
