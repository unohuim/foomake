<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <title>Privacy | FooMake</title>
        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>
    <body class="bg-white font-sans text-slate-950 antialiased">
        <main class="mx-auto max-w-3xl px-6 py-16 sm:py-20">
            <a href="{{ url('/') }}" class="text-sm font-semibold text-blue-700 hover:text-blue-800">FooMake</a>

            <div class="mt-8">
                <p class="text-sm font-semibold uppercase tracking-wide text-blue-950">Privacy</p>
                <h1 class="mt-3 text-4xl font-semibold tracking-tight text-slate-950">Privacy policy</h1>
                <p class="mt-5 text-base leading-7 text-slate-600">
                    FooMake uses necessary cookies for app login, security, and normal account access.
                </p>
            </div>

            <div class="mt-10 space-y-8 text-sm leading-7 text-slate-700">
                <section>
                    <h2 class="text-lg font-semibold text-slate-950">First-party attribution</h2>
                    <p class="mt-3">
                        FooMake may use a first-party visitor ID to understand signup or demo attribution. This is limited to basic source information such as landing page, referrer, and UTM campaign parameters.
                    </p>
                    <p class="mt-3">
                        This attribution helps FooMake understand which public pages and campaigns lead to beta signups without collecting behavioral tracking data.
                    </p>
                </section>

                <section>
                    <h2 class="text-lg font-semibold text-slate-950">What FooMake does not use</h2>
                    <p class="mt-3">
                        FooMake does not currently use Google Analytics, Google Tag Manager, ad pixels, heatmaps, session replay, clickstream tracking, scroll-depth tracking, or time-on-page tracking.
                    </p>
                </section>

                <section>
                    <h2 class="text-lg font-semibold text-slate-950">What attribution stores</h2>
                    <p class="mt-3">
                        Attribution records may store first and latest landing page, first and latest referrer, first and latest UTM source, medium, campaign, content, and term, plus first seen, last seen, and converted timestamps.
                    </p>
                    <p class="mt-3">
                        Attribution records do not store IP address, user agent profiling, full route history, session replay, heatmaps, ad identifiers, or third-party analytics identifiers.
                    </p>
                </section>
            </div>
        </main>
    </body>
</html>
