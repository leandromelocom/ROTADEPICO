<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">

        <title>Rota de Pico</title>
        <link rel="stylesheet" href="{{ asset('app-fallback.css') }}">

        @if (file_exists(public_path('build/manifest.json')) || file_exists(public_path('hot')))
            @vite(['resources/css/app.css', 'resources/js/app.js'])
        @endif
        <script src="{{ asset('app-fallback.js') }}" defer></script>
    </head>
    <body>
        <div class="auth-page">
            <div class="guest-shell">
                <a href="/">
                    <x-application-logo />
                </a>
                <div class="auth-card">
                    {{ $slot }}
                </div>
            </div>
        </div>
    </body>
</html>
