<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">

        <title inertia>{{ config('app.name', 'Laravel') }}</title>

        <meta name="description" content="Dashboards con lenguaje natural: describe lo que quieres entender y la IA diseña el dashboard, lo valida y calcula los números en tu propio servidor.">
        <meta name="theme-color" content="#1F6B51">

        <link rel="icon" href="{{ asset('favicon.svg') }}" type="image/svg+xml">
        <link rel="icon" href="{{ asset('favicon.ico') }}" sizes="32x32">
        <link rel="apple-touch-icon" href="{{ asset('apple-touch-icon.png') }}">

        {{-- Link preview (LinkedIn, chat apps, social networks). --}}
        <meta property="og:type" content="website">
        <meta property="og:site_name" content="InsightFlow">
        <meta property="og:title" content="InsightFlow · Pregúntale a tus datos">
        <meta property="og:description" content="Describe lo que quieres entender y la IA diseña el dashboard. Se valida y se calcula en tu propio servidor.">
        <meta property="og:image" content="{{ asset('og-image.png') }}">
        <meta property="og:image:width" content="1200">
        <meta property="og:image:height" content="630">
        <meta name="twitter:card" content="summary_large_image">

        <link rel="preconnect" href="https://fonts.bunny.net">
        <link href="https://fonts.bunny.net/css?family=inter:400,500,600,700" rel="stylesheet" />

        @routes
        @vite(['resources/js/app.ts'])
        @inertiaHead
    </head>
    <body class="font-sans antialiased">
        @inertia
    </body>
</html>
