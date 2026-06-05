<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">

        <title>{{ config('app.name', 'Factory Manager') }}</title>

        <link rel="preconnect" href="https://fonts.bunny.net">
        <link href="https://fonts.bunny.net/css?family=figtree:400,500,600,700&display=swap" rel="stylesheet" />

        <link rel="icon" href="/img/foomake_fav.ico?v=3" type="image/x-icon">

        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>
    <body class="bg-[#f7f9fc] font-sans text-slate-950 antialiased">
        @php
            $logoAsset = file_exists(resource_path('img/foomake_logo.png'))
                ? \Illuminate\Support\Facades\Vite::asset('resources/img/foomake_logo.png')
                : null;
        @endphp

        <div
            class="min-h-screen overflow-hidden"
            x-data="{
                isLoginOpen: false,
                authPanel: 'login',
                isAuthAccordionReady: false,
                openLogin() {
                    this.authPanel = 'login';
                    this.isAuthAccordionReady = false;
                    this.isLoginOpen = true;
                    this.$nextTick(() => {
                        window.setTimeout(() => {
                            this.$refs.loginEmail?.focus();
                            this.isAuthAccordionReady = true;
                        }, 520);
                    });
                },
                openRegister() {
                    this.authPanel = 'register';
                    this.isAuthAccordionReady = false;
                    this.isLoginOpen = true;
                    this.$nextTick(() => {
                        window.setTimeout(() => {
                            this.$refs.registerName?.focus();
                            this.isAuthAccordionReady = true;
                        }, 520);
                    });
                },
                closeLogin() {
                    this.isLoginOpen = false;
                    this.isAuthAccordionReady = false;
                },
            }"
            x-on:keydown.escape.window="closeLogin()"
        >
            <header class="relative z-20">
                <nav class="mx-auto flex max-w-7xl items-center justify-between px-4 py-5 sm:px-6 lg:px-8" aria-label="Global">
                    <a href="{{ url('/') }}" class="flex items-center gap-3">
                        @if ($logoAsset)
                            <img src="{{ $logoAsset }}" alt="{{ config('app.name', 'Factory Manager') }}" class="h-12 w-auto object-contain sm:h-14">
                        @else
                            <span class="flex h-20 w-20 items-center justify-center rounded-md bg-blue-950 text-base font-semibold text-white shadow-sm sm:h-24 sm:w-24">
                                FM
                            </span>
                        @endif
                    </a>

                    @if (Route::has('login'))
                        <div class="flex items-center gap-3">
                            @auth
                                <a
                                    href="{{ url('/dashboard') }}"
                                    class="inline-flex items-center rounded-md bg-blue-950 px-3 py-2 text-sm font-semibold text-white shadow-sm transition hover:bg-blue-900 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-blue-950"
                                >
                                    Dashboard
                                </a>
                            @else
                                <button
                                    type="button"
                                    class="group relative inline-flex h-12 w-12 items-center justify-center rounded-full border border-blue-950/25 bg-transparent text-blue-950 transition duration-300 hover:border-blue-950 hover:shadow-[0_0_22px_rgba(23,37,84,0.35)] focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-blue-950"
                                    x-on:click="openLogin()"
                                    aria-label="Open sign in drawer"
                                >
                                    <svg class="relative h-6 w-6 transition duration-300 group-hover:translate-x-0.5" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.6" stroke="currentColor" aria-hidden="true">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M17.25 8.25 21 12m0 0-3.75 3.75M21 12H3" />
                                    </svg>
                                </button>
                            @endauth
                        </div>
                    @endif
                </nav>
            </header>

            <main>
                <section class="relative -mt-20 flex min-h-screen items-center overflow-hidden pt-20">
                    <div class="absolute inset-x-0 bottom-0 -z-10 h-44 bg-white"></div>
                    <div class="absolute right-0 top-20 -z-10 h-72 w-72 rounded-full bg-blue-100/70 blur-3xl"></div>

                    <div class="mx-auto grid w-full max-w-7xl items-center gap-12 px-4 pb-14 pt-10 sm:px-6 lg:grid-cols-[0.92fr_1.08fr] lg:px-8 lg:pb-18 lg:pt-14">
                        <div class="max-w-2xl">
                            <div class="inline-flex items-center gap-2 rounded-full border border-blue-200 bg-white px-3 py-1 text-xs font-medium text-blue-950 shadow-sm">
                                <span class="h-1.5 w-1.5 rounded-full bg-blue-950"></span>
                                For bakeries, farm kitchens, roasters, and fermenters
                            </div>

                            <h1 class="mt-7 text-5xl font-semibold leading-none text-stone-950 sm:text-6xl lg:text-7xl">
                                Keep the day’s batches moving without another clipboard.
                            </h1>

                            <p class="mt-6 max-w-xl text-base leading-7 text-stone-600 sm:text-lg">
                                Track ingredients, supplier packs, inventory counts, prep work, make orders, and market orders in one place built for small-batch food production.
                            </p>

                            <div class="mt-8 flex flex-col gap-3 sm:flex-row">
                                @auth
                                    <a
                                        href="{{ url('/dashboard') }}"
                                        class="inline-flex items-center justify-center rounded-md bg-blue-950 px-5 py-3 text-sm font-semibold text-white shadow-sm transition hover:bg-blue-900 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-blue-950"
                                    >
                                        Open today’s board
                                    </a>
                                @else
                                    <button
                                        type="button"
                                        class="group inline-flex items-center gap-4 rounded-full border border-blue-950/25 bg-transparent px-5 py-3 text-sm font-semibold text-blue-950 transition duration-300 hover:border-blue-950 hover:shadow-[0_0_26px_rgba(23,37,84,0.30)] focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-blue-950"
                                        x-on:click="openLogin()"
                                    >
                                        Enter the kitchen board
                                        <span class="relative inline-flex h-10 w-10 items-center justify-center rounded-full border border-blue-950/30 transition duration-300 group-hover:border-blue-950 group-hover:shadow-[0_0_18px_rgba(23,37,84,0.32)]">
                                            <svg class="relative h-5 w-5 transition duration-300 group-hover:translate-x-0.5" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.6" stroke="currentColor" aria-hidden="true">
                                                <path stroke-linecap="round" stroke-linejoin="round" d="M17.25 8.25 21 12m0 0-3.75 3.75M21 12H3" />
                                            </svg>
                                        </span>
                                    </button>
                                @endauth
                            </div>

                            <div class="mt-10 max-w-xl border-y border-stone-200 py-5">
                                <p class="text-sm font-semibold text-stone-950">Built around the work you already do:</p>
                                <div class="mt-4 grid gap-3 text-sm text-stone-600 sm:grid-cols-2">
                                    <p>Receive oats from a supplier.</p>
                                    <p>Count flour before a run.</p>
                                    <p>Prep tomorrow’s granola batch.</p>
                                    <p>Pack the Saturday market order.</p>
                                </div>
                            </div>
                        </div>

                        <div class="relative mx-auto w-full max-w-3xl">
                            <div class="grid gap-5 lg:grid-cols-[0.92fr_1.08fr]">
                                <div class="rounded-3xl border border-slate-200 bg-white p-4 shadow-xl shadow-slate-950/5">
                                    <div class="flex items-center justify-between border-b border-stone-100 pb-4">
                                        <div>
                                            <p class="text-sm font-semibold text-stone-950">Today’s prep list</p>
                                            <p class="mt-1 text-xs text-stone-500">Thursday · farm kitchen</p>
                                        </div>
                                        <span class="rounded-full bg-blue-50 px-2.5 py-1 text-xs font-medium text-blue-950">Mobile-first</span>
                                    </div>

                                    <div class="mt-4 divide-y divide-stone-100">
                                        <div class="py-3">
                                            <div class="flex items-start justify-between gap-3">
                                                <div>
                                                    <p class="text-sm font-semibold text-stone-950">Count rye flour</p>
                                                    <p class="mt-1 text-xs text-stone-500">Inventory Count #31</p>
                                                </div>
                                                <span class="rounded-full bg-amber-50 px-2 py-1 text-xs font-medium text-amber-800">Counting</span>
                                            </div>
                                            <div class="mt-3 flex items-end justify-between">
                                                <div>
                                                    <p class="text-xs font-medium uppercase text-stone-500">Scanned</p>
                                                    <p class="mt-1 text-xl font-semibold text-stone-950">344 g</p>
                                                </div>
                                                <button type="button" class="rounded-md bg-blue-950 px-3 py-2 text-xs font-semibold text-white shadow-sm">
                                                    Complete
                                                </button>
                                            </div>
                                        </div>

                                        <div class="flex items-center justify-between py-3">
                                            <div>
                                                <p class="text-sm font-semibold text-stone-950">Receive oat pack</p>
                                                <p class="mt-1 text-xs text-stone-500">Supplier Pack · 25 kg</p>
                                            </div>
                                            <span class="text-lg font-semibold text-stone-300">›</span>
                                        </div>

                                        <div class="flex items-center justify-between py-3">
                                            <div>
                                                <p class="text-sm font-semibold text-stone-950">Prep granola run</p>
                                                <p class="mt-1 text-xs text-stone-500">Make Order #1042</p>
                                            </div>
                                            <span class="text-lg font-semibold text-stone-300">›</span>
                                        </div>

                                        <div class="flex items-center justify-between py-3">
                                            <div>
                                                <p class="text-sm font-semibold text-stone-950">Pack maple bars</p>
                                                <p class="mt-1 text-xs text-stone-500">Market Order #218</p>
                                            </div>
                                            <span class="text-lg font-semibold text-stone-300">›</span>
                                        </div>
                                    </div>
                                </div>

                                <div class="space-y-5">
                                    <div class="rounded-3xl border border-slate-200 bg-white p-5 shadow-xl shadow-slate-950/5">
                                        <div class="flex items-start justify-between gap-4">
                                            <div>
                                                <p class="text-sm font-semibold text-stone-950">Maple granola</p>
                                                <p class="mt-1 text-xs text-stone-500">Oats · Honey · Pecans · Sea salt</p>
                                            </div>
                                            <span class="rounded-full bg-blue-50 px-2 py-1 text-xs font-medium text-blue-950">Ready</span>
                                        </div>
                                        <div class="mt-5 h-2 rounded-full bg-stone-100">
                                            <div class="h-2 w-9/12 rounded-full bg-blue-950"></div>
                                        </div>
                                        <div class="mt-3 flex justify-between text-xs text-stone-500">
                                            <span>Batch ingredients staged</span>
                                            <span>75%</span>
                                        </div>
                                    </div>

                                    <div class="rounded-3xl border border-slate-200 bg-[#eef3fb] p-5 shadow-xl shadow-slate-950/5">
                                        <p class="text-sm font-semibold text-stone-950">Ingredient shelf</p>
                                        <div class="mt-4 grid grid-cols-3 gap-2">
                                            <div class="rounded-2xl bg-white p-3">
                                                <p class="text-xs text-stone-500">Oats</p>
                                                <p class="mt-1 text-lg font-semibold text-stone-950">82 kg</p>
                                            </div>
                                            <div class="rounded-2xl bg-white p-3">
                                                <p class="text-xs text-stone-500">Rye</p>
                                                <p class="mt-1 text-lg font-semibold text-stone-950">14 kg</p>
                                            </div>
                                            <div class="rounded-2xl bg-white p-3">
                                                <p class="text-xs text-stone-500">Honey</p>
                                                <p class="mt-1 text-lg font-semibold text-stone-950">9 L</p>
                                            </div>
                                        </div>
                                    </div>

                                    <div class="rounded-3xl border border-slate-200 bg-white p-5 shadow-xl shadow-slate-950/5">
                                        <div class="flex items-center justify-between">
                                            <p class="text-sm font-semibold text-stone-950">Saturday market</p>
                                            <span class="text-xs font-medium text-stone-500">19 orders</span>
                                        </div>
                                        <div class="mt-4 flex items-center gap-3">
                                            <div class="h-10 w-10 rounded-full bg-blue-100"></div>
                                            <div class="h-10 w-10 rounded-full bg-amber-100"></div>
                                            <div class="h-10 w-10 rounded-full bg-sky-100"></div>
                                            <div class="flex h-10 w-10 items-center justify-center rounded-full bg-stone-100 text-xs font-semibold text-stone-600">+8</div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </section>

                <section class="bg-white">
                    <div class="mx-auto grid max-w-7xl gap-4 px-4 py-8 sm:px-6 md:grid-cols-3 lg:px-8">
                        <div class="border-l border-blue-300 pl-4">
                            <p class="text-sm font-semibold text-stone-950">Made for small batches</p>
                            <p class="mt-2 text-sm leading-6 text-stone-600">Materials, supplier packs, counts, and make orders stay close to the way you actually produce.</p>
                        </div>
                        <div class="border-l border-blue-300 pl-4">
                            <p class="text-sm font-semibold text-stone-950">Useful on a phone</p>
                            <p class="mt-2 text-sm leading-6 text-stone-600">Taskers can see and complete assigned work without stepping away from the bench.</p>
                        </div>
                        <div class="border-l border-blue-300 pl-4">
                            <p class="text-sm font-semibold text-stone-950">Calm enough for daily use</p>
                            <p class="mt-2 text-sm leading-6 text-stone-600">No giant ERP ceremony. Just the day’s ingredients, batches, orders, and responsibilities.</p>
                        </div>
                    </div>

                    <div class="mx-auto max-w-7xl px-4 pb-10 sm:px-6 lg:px-8">
                        <div class="border-t border-stone-200 pt-6">
                            <p class="text-xs font-semibold uppercase text-stone-500">Learn more</p>
                            <div class="mt-4 flex flex-wrap gap-x-5 gap-y-2 text-sm font-medium text-blue-950">
                                <a href="{{ route('marketing.pages.show', ['slug' => 'food-manufacturing-mrp']) }}" class="hover:text-blue-800">
                                    Food manufacturing MRP
                                </a>
                                <a href="{{ route('marketing.pages.show', ['slug' => 'inventory-management-for-food-manufacturers']) }}" class="hover:text-blue-800">
                                    Inventory management
                                </a>
                                <a href="{{ route('marketing.pages.show', ['slug' => 'recipe-management-software']) }}" class="hover:text-blue-800">
                                    Recipe management
                                </a>
                                <a href="{{ route('marketing.pages.show', ['slug' => 'purchase-order-software-for-food-manufacturers']) }}" class="hover:text-blue-800">
                                    Purchase orders
                                </a>
                                <a href="{{ route('marketing.pages.show', ['slug' => 'production-planning-for-small-food-manufacturers']) }}" class="hover:text-blue-800">
                                    Production planning
                                </a>
                                <a href="{{ route('marketing.pages.show', ['slug' => 'mrp-for-small-manufacturers']) }}" class="hover:text-blue-800">
                                    MRP for small manufacturers
                                </a>
                                <a href="{{ route('privacy') }}" class="hover:text-blue-800">
                                    Privacy
                                </a>
                            </div>
                        </div>
                    </div>
                </section>
            </main>

            @guest
                <div
                    class="fixed inset-0 z-50 overflow-hidden"
                    x-show="isLoginOpen"
                    x-cloak
                    aria-labelledby="login-drawer-title"
                    role="dialog"
                    aria-modal="true"
                    x-on:click.self="closeLogin()"
                >
                    <div
                        class="absolute inset-0 bg-stone-950/30 transition-opacity"
                        x-show="isLoginOpen"
                        x-transition:enter="ease-out duration-300"
                        x-transition:enter-start="opacity-0"
                        x-transition:enter-end="opacity-100"
                        x-transition:leave="ease-in duration-200"
                        x-transition:leave-start="opacity-100"
                        x-transition:leave-end="opacity-0"
                        x-on:click="closeLogin()"
                    ></div>

                    <div class="absolute inset-y-0 right-0 flex max-w-full sm:pl-12">
                        <div
                            class="w-[22rem] max-w-full transform-gpu bg-white shadow-2xl sm:w-screen sm:max-w-md"
                            x-show="isLoginOpen"
                            x-transition:enter="transform transition ease-in-out duration-300 sm:duration-500"
                            x-transition:enter-start="translate-x-full"
                            x-transition:enter-end="translate-x-0"
                            x-transition:leave="transform transition ease-in-out duration-300 sm:duration-500"
                            x-transition:leave-start="translate-x-0"
                            x-transition:leave-end="translate-x-full"
                        >
                            <div class="relative flex h-full overflow-hidden bg-white">
                                <button
                                    type="button"
                                    class="absolute right-4 top-4 z-10 rounded-md text-slate-400 transition hover:text-slate-600 focus:outline-none focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-blue-950"
                                    x-on:click="closeLogin()"
                                >
                                    <span class="sr-only">Close login drawer</span>
                                    <svg class="h-6 w-6" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" aria-hidden="true">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12" />
                                    </svg>
                                </button>

                                <div class="flex min-w-0 flex-1">
                                    <button
                                        type="button"
                                        class="flex w-14 shrink-0 items-center justify-center bg-[#11284c] text-sm font-semibold uppercase text-white"
                                        x-on:click="authPanel = 'register'; $nextTick(() => $refs.registerName?.focus())"
                                    >
                                        <span class="-rotate-90 whitespace-nowrap">Register</span>
                                    </button>

                                    <div
                                        class="min-w-0 overflow-hidden bg-white"
                                        x-bind:class="[
                                            authPanel === 'register' ? 'w-[calc(100%-7rem)] opacity-100' : 'w-0 opacity-0',
                                            isAuthAccordionReady ? 'transition-[width,opacity] duration-500 ease-in-out' : '',
                                        ]"
                                    >
                                        <div class="flex h-full w-[calc(100vw-7rem)] max-w-[21rem] flex-col justify-center overflow-y-auto px-6 py-10 sm:px-8">
                                            <div class="mx-auto flex w-full max-w-sm flex-col items-center">
                                                @if ($logoAsset)
                                                    <img src="{{ $logoAsset }}" alt="{{ config('app.name', 'Factory Manager') }}" class="h-32 w-auto object-contain sm:h-36">
                                                @else
                                                    <div class="flex h-28 w-28 items-center justify-center rounded-2xl bg-blue-950 text-2xl font-semibold text-white shadow-sm">
                                                        FM
                                                    </div>
                                                @endif

                                                <h2 id="login-drawer-title" class="mt-8 text-center text-2xl font-semibold text-stone-950">
                                                    welcome
                                                </h2>
                                                <p class="mt-2 text-center text-sm text-stone-500">
                                                    your sanity awaits
                                                </p>
                                            </div>

                                            <div class="mx-auto mt-8 w-full max-w-sm">
                                                @if (Route::has('register'))
                                                    <form method="POST" action="{{ route('register') }}">
                                                        @csrf

                                                        <div>
                                                            <label for="drawer-name" class="block text-sm font-medium text-stone-700">Name</label>
                                                            <input
                                                                id="drawer-name"
                                                                type="text"
                                                                name="name"
                                                                value="{{ old('name') }}"
                                                                required
                                                                autocomplete="name"
                                                                x-ref="registerName"
                                                                class="mt-2 block w-full rounded-md border-slate-300 bg-white px-3 py-2 text-slate-950 shadow-sm focus:border-blue-950 focus:ring-blue-950 sm:text-sm"
                                                            >
                                                            <x-input-error :messages="$errors->get('name')" class="mt-2" />
                                                        </div>

                                                        <div class="mt-5">
                                                            <label for="drawer-register-email" class="block text-sm font-medium text-stone-700">Email</label>
                                                            <input
                                                                id="drawer-register-email"
                                                                type="email"
                                                                name="email"
                                                                value="{{ old('email') }}"
                                                                required
                                                                autocomplete="username"
                                                                class="mt-2 block w-full rounded-md border-slate-300 bg-white px-3 py-2 text-slate-950 shadow-sm focus:border-blue-950 focus:ring-blue-950 sm:text-sm"
                                                            >
                                                            <x-input-error :messages="$errors->get('email')" class="mt-2" />
                                                        </div>

                                                        <div class="mt-5">
                                                            <label for="drawer-register-password" class="block text-sm font-medium text-stone-700">Password</label>
                                                            <input
                                                                id="drawer-register-password"
                                                                type="password"
                                                                name="password"
                                                                required
                                                                autocomplete="new-password"
                                                                class="mt-2 block w-full rounded-md border-slate-300 bg-white px-3 py-2 text-slate-950 shadow-sm focus:border-blue-950 focus:ring-blue-950 sm:text-sm"
                                                            >
                                                            <x-input-error :messages="$errors->get('password')" class="mt-2" />
                                                        </div>

                                                        <div class="mt-5">
                                                            <label for="drawer-password-confirmation" class="block text-sm font-medium text-stone-700">Confirm password</label>
                                                            <input
                                                                id="drawer-password-confirmation"
                                                                type="password"
                                                                name="password_confirmation"
                                                                required
                                                                autocomplete="new-password"
                                                                class="mt-2 block w-full rounded-md border-slate-300 bg-white px-3 py-2 text-slate-950 shadow-sm focus:border-blue-950 focus:ring-blue-950 sm:text-sm"
                                                            >
                                                            <x-input-error :messages="$errors->get('password_confirmation')" class="mt-2" />
                                                        </div>

                                                        <button
                                                            type="submit"
                                                            class="mt-7 flex w-full justify-center rounded-md bg-blue-950 px-4 py-3 text-sm font-semibold text-white shadow-sm transition hover:bg-blue-900 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-blue-950"
                                                        >
                                                            Create workspace
                                                        </button>
                                                    </form>
                                                @endif
                                            </div>
                                        </div>
                                    </div>

                                    <button
                                        type="button"
                                        class="flex w-14 shrink-0 items-center justify-center border-l border-white bg-[#11284c] text-sm font-semibold uppercase text-white"
                                        x-on:click="authPanel = 'login'; $nextTick(() => $refs.loginEmail?.focus())"
                                    >
                                        <span class="-rotate-90 whitespace-nowrap">Login</span>
                                    </button>

                                    <div
                                        class="min-w-0 overflow-hidden bg-white"
                                        x-bind:class="[
                                            authPanel === 'login' ? 'w-[calc(100%-7rem)] opacity-100' : 'w-0 opacity-0',
                                            isAuthAccordionReady ? 'transition-[width,opacity] duration-500 ease-in-out' : '',
                                        ]"
                                    >
                                        <div class="flex h-full w-[calc(100vw-7rem)] max-w-[21rem] flex-col justify-center overflow-y-auto px-6 py-10 sm:px-8">
                                            <div class="mx-auto flex w-full max-w-sm flex-col items-center">
                                                @if ($logoAsset)
                                                    <img src="{{ $logoAsset }}" alt="{{ config('app.name', 'Factory Manager') }}" class="h-32 w-auto object-contain sm:h-36">
                                                @else
                                                    <div class="flex h-28 w-28 items-center justify-center rounded-2xl bg-blue-950 text-2xl font-semibold text-white shadow-sm">
                                                        FM
                                                    </div>
                                                @endif

                                                <h2 class="mt-8 text-center text-2xl font-semibold text-stone-950">
                                                    hello again
                                                </h2>
                                                <p class="mt-2 text-center text-sm text-stone-500">
                                                    Sign in to today’s production board.
                                                </p>
                                            </div>

                                            <div class="mx-auto mt-8 w-full max-w-sm">
                                                <form method="POST" action="{{ route('login') }}">
                                                    @csrf

                                                    <div>
                                                        <label for="drawer-email" class="block text-sm font-medium text-stone-700">Email</label>
                                                        <input
                                                            id="drawer-email"
                                                            type="email"
                                                            name="email"
                                                            value="{{ old('email') }}"
                                                            required
                                                            autocomplete="username"
                                                            x-ref="loginEmail"
                                                            class="mt-2 block w-full rounded-md border-slate-300 bg-white px-3 py-2 text-slate-950 shadow-sm focus:border-blue-950 focus:ring-blue-950 sm:text-sm"
                                                        >
                                                        <x-input-error :messages="$errors->get('email')" class="mt-2" />
                                                    </div>

                                                    <div class="mt-5">
                                                        <label for="drawer-password" class="block text-sm font-medium text-stone-700">Password</label>
                                                        <input
                                                            id="drawer-password"
                                                            type="password"
                                                            name="password"
                                                            required
                                                            autocomplete="current-password"
                                                            class="mt-2 block w-full rounded-md border-slate-300 bg-white px-3 py-2 text-slate-950 shadow-sm focus:border-blue-950 focus:ring-blue-950 sm:text-sm"
                                                        >
                                                        <x-input-error :messages="$errors->get('password')" class="mt-2" />
                                                    </div>

                                                    <div class="mt-5 flex items-center justify-between">
                                                        <label for="drawer-remember" class="flex items-center gap-2 text-sm text-stone-600">
                                                            <input
                                                                id="drawer-remember"
                                                                type="checkbox"
                                                                name="remember"
                                                                class="rounded border-slate-300 text-blue-950 shadow-sm focus:ring-blue-950"
                                                            >
                                                            Remember me
                                                        </label>

                                                        @if (Route::has('password.request'))
                                                            <a href="{{ route('password.request') }}" class="text-sm font-medium text-blue-950 transition hover:text-blue-900">
                                                                Forgot password?
                                                            </a>
                                                        @endif
                                                    </div>

                                                    <button
                                                        type="submit"
                                                        class="mt-7 flex w-full justify-center rounded-md bg-blue-950 px-4 py-3 text-sm font-semibold text-white shadow-sm transition hover:bg-blue-900 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-blue-950"
                                                    >
                                                        Log in
                                                    </button>
                                                </form>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            @endguest
        </div>
    </body>
</html>
