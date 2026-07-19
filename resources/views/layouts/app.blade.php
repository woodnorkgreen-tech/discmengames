<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover" />
    <meta name="csrf-token" content="{{ csrf_token() }}" />
    <meta name="app-url" content="{{ rtrim(config('app.url'), '/') }}" />
    <meta name="theme-color" content="#100F0D" />
    <meta name="description" content="Discmen Entertainment's live football prediction and trivia experience." />
    <title>@yield('title', 'Discmen Final Whistle')</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @stack('head')
</head>
<body class="@yield('body-class', 'bg-gray-950 text-white min-h-screen')">
    @yield('content')
    @stack('scripts')
</body>
</html>
