<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">

        <title>{{ $page->title }} | FooMake</title>
        <meta name="description" content="{{ $page->description }}">
        <link rel="canonical" href="{{ $canonicalUrl }}">
        <meta property="og:title" content="{{ $page->title }}">
        <meta property="og:description" content="{{ $page->description }}">
        <meta property="og:url" content="{{ $canonicalUrl }}">
        <meta property="og:type" content="article">

        @if ($page->noindex)
            <meta name="robots" content="noindex,nofollow">
        @endif

        <link rel="preconnect" href="https://fonts.bunny.net">
        <link href="https://fonts.bunny.net/css?family=figtree:400,500,600,700&display=swap" rel="stylesheet" />

        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>
    <body class="bg-white font-sans text-slate-950 antialiased" data-marketing-page>
        <header class="border-b border-slate-200 bg-white">
            <nav class="mx-auto flex max-w-6xl items-center justify-between px-4 py-4 sm:px-6 lg:px-8" aria-label="Marketing">
                <a href="{{ url('/') }}" class="text-sm font-semibold text-blue-950">
                    FooMake
                </a>
                <a
                    href="{{ url('/register') }}"
                    class="rounded-md bg-blue-950 px-3 py-2 text-sm font-semibold text-white shadow-sm transition hover:bg-blue-900 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-blue-950"
                >
                    Start beta access
                </a>
            </nav>
        </header>

        <main>
            <section class="border-b border-slate-200 bg-[#f7f9fc]">
                <div class="mx-auto max-w-4xl px-4 py-14 sm:px-6 lg:px-8 lg:py-18">
                    <p class="text-sm font-semibold uppercase tracking-wide text-blue-950">
                        FooMake beta
                    </p>
                    <h1 class="mt-4 text-4xl font-semibold leading-tight text-slate-950 sm:text-5xl">
                        {{ $page->headline }}
                    </h1>
                    <p class="mt-5 max-w-3xl text-lg leading-8 text-slate-600">
                        {{ $page->description }}
                    </p>
                    <div class="mt-8">
                        <a
                            href="{{ $page->ctaUrl }}"
                            class="inline-flex items-center rounded-md bg-blue-950 px-5 py-3 text-sm font-semibold text-white shadow-sm transition hover:bg-blue-900 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-blue-950"
                        >
                            {{ $page->ctaLabel }}
                        </a>
                    </div>
                </div>
            </section>

            <section class="bg-white">
                <div class="mx-auto max-w-3xl px-4 py-12 sm:px-6 lg:px-8">
                    <article class="space-y-8 text-base leading-7 text-slate-700 [&_a]:font-semibold [&_a]:text-blue-950 [&_h2]:mt-10 [&_h2]:text-2xl [&_h2]:font-semibold [&_h2]:leading-tight [&_h2]:text-slate-950 [&_h3]:mt-8 [&_h3]:text-lg [&_h3]:font-semibold [&_h3]:text-slate-950 [&_li]:mt-2 [&_p]:mt-4 [&_strong]:font-semibold [&_strong]:text-slate-950 [&_ul]:mt-4 [&_ul]:list-disc [&_ul]:pl-5">
                        {!! $page->html !!}
                    </article>
                </div>
            </section>

            <section class="border-t border-slate-200 bg-[#f7f9fc]">
                <div class="mx-auto max-w-4xl px-4 py-12 text-center sm:px-6 lg:px-8">
                    <h2 class="text-2xl font-semibold text-slate-950">
                        Simple MRP software for small food manufacturers who have outgrown spreadsheets.
                    </h2>
                    <p class="mt-4 text-base leading-7 text-slate-600">
                        FooMake is in beta, ready for teams to use, and actively improved around practical manufacturing workflows.
                    </p>
                    <div class="mt-7">
                        <a
                            href="{{ $page->ctaUrl }}"
                            class="inline-flex items-center rounded-md bg-blue-950 px-5 py-3 text-sm font-semibold text-white shadow-sm transition hover:bg-blue-900 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-blue-950"
                        >
                            {{ $page->ctaLabel }}
                        </a>
                    </div>
                </div>
            </section>
        </main>
    </body>
</html>
