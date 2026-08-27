<script setup>
import { Head } from "@inertiajs/vue3";

const props = defineProps({
    page: {
        type: Object,
        required: true,
    },
    canonicalUrl: {
        type: String,
        required: true,
    },
    authRoutes: {
        type: Object,
        required: true,
    },
});
</script>

<template>
    <Head>
        <title>{{ page.title }}</title>
        <meta name="description" :content="page.description">
        <link rel="canonical" :href="canonicalUrl">
        <meta property="og:title" :content="page.title">
        <meta property="og:description" :content="page.description">
        <meta property="og:url" :content="canonicalUrl">
        <meta property="og:type" content="article">
        <meta v-if="page.noindex" name="robots" content="noindex,nofollow">
    </Head>

    <header class="border-b border-slate-200 bg-white">
        <nav class="mx-auto flex max-w-6xl items-center justify-between px-4 py-4 sm:px-6 lg:px-8" aria-label="Marketing">
            <a href="/" class="cursor-pointer text-sm font-semibold text-blue-950">
                FooMake
            </a>
            <a
                :href="authRoutes.registerUrl"
                class="cursor-pointer rounded-md bg-blue-950 px-3 py-2 text-sm font-semibold text-white shadow-sm transition hover:bg-blue-900 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-blue-950"
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
                    {{ page.headline }}
                </h1>
                <p class="mt-5 max-w-3xl text-lg leading-8 text-slate-600">
                    {{ page.description }}
                </p>
                <div class="mt-8">
                    <a
                        :href="page.ctaUrl"
                        class="inline-flex cursor-pointer items-center rounded-md bg-blue-950 px-5 py-3 text-sm font-semibold text-white shadow-sm transition hover:bg-blue-900 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-blue-950"
                    >
                        {{ page.ctaLabel }}
                    </a>
                </div>
            </div>
        </section>

        <section class="bg-white">
            <div class="mx-auto max-w-3xl px-4 py-12 sm:px-6 lg:px-8">
                <article
                    class="space-y-8 text-base leading-7 text-slate-700 [&_a]:font-semibold [&_a]:text-blue-950 [&_h2]:mt-10 [&_h2]:text-2xl [&_h2]:font-semibold [&_h2]:leading-tight [&_h2]:text-slate-950 [&_h3]:mt-8 [&_h3]:text-lg [&_h3]:font-semibold [&_h3]:text-slate-950 [&_li]:mt-2 [&_p]:mt-4 [&_strong]:font-semibold [&_strong]:text-slate-950 [&_ul]:mt-4 [&_ul]:list-disc [&_ul]:pl-5"
                    v-html="page.html"
                />
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
                        :href="page.ctaUrl"
                        class="inline-flex cursor-pointer items-center rounded-md bg-blue-950 px-5 py-3 text-sm font-semibold text-white shadow-sm transition hover:bg-blue-900 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-blue-950"
                    >
                        {{ page.ctaLabel }}
                    </a>
                </div>
            </div>
        </section>
    </main>
</template>
