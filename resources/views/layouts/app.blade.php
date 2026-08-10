<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        @include('layouts.google-analytics')

        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">

        <title>{{ config('app.name', 'Laravel') }}</title>

       <link rel="icon" href="/img/foomake_fav.ico?v=3" type="image/x-icon">

        <!-- Fonts -->
        <link rel="preconnect" href="https://fonts.bunny.net">
        <link href="https://fonts.bunny.net/css?family=figtree:400,500,600&display=swap" rel="stylesheet" />

        <!-- Scripts -->
        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>
    <body class="font-sans antialiased">
        <div class="{{ $stickyShell ? 'flex h-screen flex-col overflow-hidden bg-gray-100' : 'min-h-screen bg-gray-100' }}">
            <div class="sticky top-0 z-40 {{ $stickyShell ? 'shrink-0' : '' }}">
                @php
                    $verificationGrace = app(\App\Support\Auth\EmailVerificationGracePeriod::class);
                    $verificationGraceUser = auth()->user();
                    $showVerificationGraceBanner = $verificationGraceUser
                        && $verificationGrace->isActive($verificationGraceUser)
                        && ! session(\App\Http\Controllers\Auth\EmailVerificationGraceBannerController::DISMISSED_SESSION_KEY, false);
                @endphp

                @if ($showVerificationGraceBanner)
                    <div class="bg-blue-300 px-4 py-3 text-blue-950 sm:px-6 lg:px-8">
                        <div class="mx-auto flex max-w-7xl flex-col gap-3 text-sm sm:flex-row sm:items-center sm:justify-between">
                            <p class="font-bold">
                                {{ __('Your email is not verified. Verify it before :time to keep app access.', ['time' => $verificationGrace->endsAt($verificationGraceUser)->format('M j, Y g:i A')]) }}
                            </p>

                            <form method="POST" action="{{ route('verification.grace-banner.destroy') }}" class="shrink-0">
                                @csrf
                                @method('DELETE')
                                <button
                                    type="submit"
                                    class="inline-flex h-7 w-7 items-center justify-center rounded-full text-lg font-semibold leading-none text-blue-950 transition hover:bg-blue-200 focus:outline-none focus:ring-2 focus:ring-blue-900 focus:ring-offset-2 focus:ring-offset-blue-300"
                                    aria-label="{{ __('Dismiss verification reminder') }}"
                                >
                                    {{ __('x') }}
                                </button>
                            </form>
                        </div>
                    </div>
                @endif

                @include('layouts.navigation')

                <!-- Page Heading -->
                @isset($header)
                    <header class="bg-white shadow">
                        <div class="max-w-7xl mx-auto pb-0 px-4 sm:pb-6 sm:px-6 lg:px-8">
                            {{ $header }}
                        </div>
                    </header>
                @endisset
            </div>

            <!-- Page Content -->
            <main class="{{ $stickyShell ? 'min-h-0 flex-1 overflow-hidden' : '' }}">
                {{ $slot }}
            </main>
        </div>
    </body>
</html>
