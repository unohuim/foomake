@props([
    'visible' => 'toast.visible',
    'type' => 'toast.type',
    'message' => 'toast.message',
    'dismiss' => null,
])

@php
    $dismissExpression = $dismiss ?? "{$visible} = false";
@endphp

<div
    {{ $attributes->class(['pointer-events-none fixed inset-x-3 top-3 z-50 flex w-auto max-w-none sm:left-auto sm:right-6 sm:top-6 sm:w-full sm:max-w-sm']) }}
    x-show="{{ $visible }}"
    x-transition:enter="transform ease-out duration-300 transition"
    x-transition:enter-start="translate-y-2 opacity-0 sm:translate-y-0 sm:translate-x-2"
    x-transition:enter-end="translate-y-0 opacity-100 sm:translate-x-0"
    x-transition:leave="transition ease-in duration-100"
    x-transition:leave-start="opacity-100"
    x-transition:leave-end="opacity-0"
    x-cloak
    aria-live="assertive"
    role="status"
>
    <div class="pointer-events-auto w-full overflow-hidden rounded-lg bg-white shadow-lg ring-1 ring-black/5">
        <div class="p-4">
            <div class="flex items-start">
                <div class="shrink-0">
                    <svg
                        x-show="{{ $type }} === 'success'"
                        class="size-6 text-green-400"
                        xmlns="http://www.w3.org/2000/svg"
                        fill="none"
                        viewBox="0 0 24 24"
                        stroke-width="1.5"
                        stroke="currentColor"
                        aria-hidden="true"
                    >
                        <path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75 11.25 15 15 9.75M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z" />
                    </svg>
                    <svg
                        x-show="{{ $type }} !== 'success'"
                        class="size-6 text-red-400"
                        xmlns="http://www.w3.org/2000/svg"
                        fill="none"
                        viewBox="0 0 24 24"
                        stroke-width="1.5"
                        stroke="currentColor"
                        aria-hidden="true"
                    >
                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m9-.75a9 9 0 1 1-18 0 9 9 0 0 1 18 0Zm-9 3.75h.008v.008H12v-.008Z" />
                    </svg>
                </div>
                <div class="ml-3 w-0 flex-1 pt-0.5">
                    <p class="text-sm font-medium text-gray-900" x-text="{{ $message }}"></p>
                </div>
                <div class="ml-4 flex shrink-0">
                    <button
                        type="button"
                        class="inline-flex rounded-md bg-white text-gray-400 hover:text-gray-500 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2"
                        x-on:click="{{ $dismissExpression }}"
                    >
                        <span class="sr-only">{{ __('Close') }}</span>
                        <svg class="size-5" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12" />
                        </svg>
                    </button>
                </div>
            </div>
        </div>
    </div>
</div>
