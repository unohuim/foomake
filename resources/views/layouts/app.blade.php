<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">

        <title>{{ config('app.name', 'Laravel') }}</title>

        <!-- Fonts -->
        <link rel="preconnect" href="https://fonts.bunny.net">
        <link href="https://fonts.bunny.net/css?family=figtree:400,500,600&display=swap" rel="stylesheet" />

        <!-- Scripts -->
        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>
    <body class="font-sans antialiased">
        <div class="{{ $stickyShell ? 'flex h-screen flex-col overflow-hidden bg-gray-100' : 'min-h-screen bg-gray-100' }}">
            <div class="{{ $stickyShell ? 'sticky top-0 z-40 shrink-0' : '' }}">
                @include('layouts.navigation')
            </div>

            <!-- Page Heading -->
            @isset($header)
                <header class="{{ $stickyShell ? 'sticky top-16 z-30 shrink-0 bg-white shadow' : 'bg-white shadow' }}">
                    <div class="max-w-7xl mx-auto py-6 px-4 sm:px-6 lg:px-8">
                        {{ $header }}
                    </div>
                </header>
            @endisset

            <!-- Page Content -->
            <main class="{{ $stickyShell ? 'min-h-0 flex-1 overflow-hidden' : '' }}">
                {{ $slot }}
            </main>
        </div>
    </body>
</html>
