<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('title', 'Retro Rocket – Try it now')</title>

    @vite(['resources/css/app.css', 'resources/js/app.js'])

    <!-- Twitter Card -->
    <meta name="twitter:card" content="summary_large_image">
    <meta name="twitter:title" content="@yield('meta_title', config('mission-control.branding.app_name'))">
    <meta name="twitter:description" content="@yield('meta_description', config('mission-control.branding.tagline'))">
    <meta name="twitter:image" content="@yield('meta_description', config('mission-control.branding.logo'))">

    <!-- Open Graph -->
    <meta property="og:type" content="website">
    <meta property="og:title" content="@yield('meta_title', config('mission-control.branding.app_name'))">
    <meta property="og:description" content="@yield('meta_description', config('mission-control.branding.tagline'))">
    <meta property="og:image" content="@yield('meta_description', config('mission-control.branding.logo'))">
    <meta property="og:url" content="{{ url()->current() }}">
    <meta property="og:site_name" content="{{ config('mission-control.branding.app_name') }}">

    @stack('head')
</head>
<body>
<div class="wrap">
    <div class="header">
        <div class="brand">
            <h1>{{ config('mission-control.branding.app_name') }}</h1>
            <p>{{ config('mission-control.branding.tagline') }}</p>
        </div>
        <div>
            @yield('header_right')
        </div>
    </div>

    @yield('content')

    <div style="margin-top: 18px" class="note">
        Powered by <b><img src="/img/missioncontrol.png" alt="Logo" style="height: 1.2em; vertical-align: bottom" /> Mission Control</b> version 0.0.1a
    </div>
</div>

@stack('scripts')
</body>
</html>
