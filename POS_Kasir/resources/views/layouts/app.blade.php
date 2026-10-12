<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no, viewport-fit=cover">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <title>@yield('title', config('app.name'))</title>

    <link rel="manifest" href="{{ asset_versioned('manifest.webmanifest') }}">
    <meta name="theme-color" content="#1e3a8a">
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

    <script src="{{ asset_versioned('assets/js/csrf.js') }}"></script>
    <script src="{{ asset_versioned('assets/js/pwa.js') }}"></script>
    <script src="{{ asset_versioned('assets/js/nav.js') }}"></script>
    @stack('scripts')
</body>
</html>
