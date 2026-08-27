<script setup>
import { Head } from "@inertiajs/vue3";
</script>

<template>
    <Head title="Privacy" />

    <main class="mx-auto max-w-3xl px-6 py-16 sm:py-20">
        <a href="/" class="cursor-pointer text-sm font-semibold text-blue-700 hover:text-blue-800">FooMake</a>

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
                <h2 class="text-lg font-semibold text-slate-950">Google Analytics</h2>
                <p class="mt-3">
                    FooMake uses Google Analytics to understand aggregate site usage, traffic sources, and page performance across public and authenticated pages.
                </p>
                <p class="mt-3">
                    FooMake does not currently use Google Tag Manager, ad pixels, heatmaps, session replay, or clickstream tracking.
                </p>
            </section>

            <section>
                <h2 class="text-lg font-semibold text-slate-950">What attribution stores</h2>
                <p class="mt-3">
                    Attribution records may store first and latest landing page, first and latest referrer, first and latest UTM source, medium, campaign, content, and term, plus first seen, last seen, and converted timestamps.
                </p>
                <p class="mt-3">
                    FooMake attribution records do not store IP address, user agent profiling, full route history, session replay, heatmaps, ad identifiers, or Google Analytics client identifiers.
                </p>
            </section>
        </div>
    </main>
</template>
