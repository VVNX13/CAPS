<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no, viewport-fit=cover">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <title>@yield('title', config('app.name'))</title>

    <link rel="manifest" href="{{ asset_versioned('manifest.webmanifest') }}">
    <meta name="theme-color" content="#25382d">
    <meta name="mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
    <meta name="apple-mobile-web-app-title" content="{{ config('app.name') }}">
    <link rel="apple-touch-icon" href="{{ asset_versioned('assets/icons/icon-192.png') }}">
    <link rel="icon" type="image/png" sizes="192x192" href="{{ asset_versioned('assets/icons/icon-192.png') }}">

    <link rel="stylesheet" href="{{ asset_versioned('assets/css/style.css') }}">
    @stack('head')
</head>
<body class="@yield('body-class')">
    @yield('content')

    <script>
        // Line icons for client-rendered markup; same set as the Blade icon component (App\Support\Icon).
        window.__ICONS__ = @json(\App\Support\Icon::PATHS);
        function icon(name, cls) {
            return '<svg class="icon icon-' + name + (cls ? ' ' + cls : '') + '" viewBox="0 0 24 24" fill="none" stroke="currentColor"'
                + ' stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false">'
                + (window.__ICONS__[name] || '') + '</svg>';
        }
        // Icon followed by escaped text — for status lines that carry server messages.
        function iconText(name, text) {
            const safe = String(text ?? '').replace(/[&<>"']/g, (c) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' })[c]);
            return icon(name) + ' <span>' + safe + '</span>';
        }
    </script>
    <script src="{{ asset_versioned('assets/js/csrf.js') }}"></script>
    <script src="{{ asset_versioned('assets/js/pwa.js') }}"></script>
    <script src="{{ asset_versioned('assets/js/nav.js') }}"></script>
    @stack('scripts')
</body>
</html>
