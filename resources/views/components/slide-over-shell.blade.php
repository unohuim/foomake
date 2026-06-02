@props([
    'open',
    'close',
    'submit' => null,
    'title' => null,
    'titleExpression' => null,
    'description' => null,
    'descriptionExpression' => null,
    'titleId' => 'slide-over-title',
    'maxWidth' => 'max-w-md',
])

<div
    {{ $attributes->merge(['class' => 'fixed inset-0 z-50 overflow-hidden']) }}
    x-show="{{ $open }}"
    x-cloak
    role="dialog"
    aria-modal="true"
    aria-labelledby="{{ $titleId }}"
>
    <div class="absolute inset-0 overflow-hidden">
        <div
            class="absolute inset-0 bg-gray-500 bg-opacity-25 transition-opacity"
            x-show="{{ $open }}"
            x-transition:enter="ease-in-out duration-300"
            x-transition:enter-start="opacity-0"
            x-transition:enter-end="opacity-100"
            x-transition:leave="ease-in-out duration-300"
            x-transition:leave-start="opacity-100"
            x-transition:leave-end="opacity-0"
            x-on:click="{{ $close }}"
        ></div>

        <div
            tabindex="0"
            class="absolute inset-0 pl-10 focus:outline-none sm:pl-16"
            x-on:click="{{ $close }}"
        >
            <div class="pointer-events-none fixed inset-y-0 right-0 flex max-w-full pl-10">
                <div class="pointer-events-auto w-screen {{ $maxWidth }}" x-on:click.stop>
                    <form
                        class="relative flex h-full flex-col divide-y divide-gray-200 bg-white shadow-xl"
                        x-show="{{ $open }}"
                        x-transition:enter="transform transition ease-in-out duration-300 sm:duration-500"
                        x-transition:enter-start="translate-x-full"
                        x-transition:enter-end="translate-x-0"
                        x-transition:leave="transform transition ease-in-out duration-300 sm:duration-500"
                        x-transition:leave-start="translate-x-0"
                        x-transition:leave-end="translate-x-full"
                        @if ($submit) x-on:submit.prevent="{{ $submit }}" @endif
                    >
                        <div class="h-0 flex-1 overflow-y-auto">
                            <div class="bg-blue-600 px-4 py-6 sm:px-6">
                                <div class="flex items-start justify-between gap-4">
                                    <div>
                                        @if ($titleExpression)
                                            <h2 id="{{ $titleId }}" class="text-lg font-semibold text-white" x-text="{{ $titleExpression }}"></h2>
                                        @else
                                            <h2 id="{{ $titleId }}" class="text-lg font-semibold text-white">{{ $title }}</h2>
                                        @endif

                                        @if ($descriptionExpression)
                                            <p class="mt-1 text-sm text-blue-100" x-text="{{ $descriptionExpression }}"></p>
                                        @elseif ($description)
                                            <p class="mt-1 text-sm text-blue-100">{{ $description }}</p>
                                        @endif
                                    </div>

                                    <div class="flex h-7 items-center">
                                        <button
                                            type="button"
                                            class="relative rounded-md text-blue-100 hover:text-white focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-white"
                                            x-on:click="{{ $close }}"
                                        >
                                            <span class="absolute -inset-2.5"></span>
                                            <span class="sr-only">{{ __('Close panel') }}</span>
                                            <svg
                                                viewBox="0 0 24 24"
                                                fill="none"
                                                stroke="currentColor"
                                                stroke-width="1.5"
                                                aria-hidden="true"
                                                class="size-6"
                                            >
                                                <path d="M6 18 18 6M6 6l12 12" stroke-linecap="round" stroke-linejoin="round" />
                                            </svg>
                                        </button>
                                    </div>
                                </div>
                            </div>

                            <div class="px-4 py-6 sm:px-6">
                                {{ $slot }}
                            </div>
                        </div>

                        @isset($footer)
                            <div class="flex shrink-0 justify-end gap-3 px-4 py-4 sm:px-6">
                                {{ $footer }}
                            </div>
                        @endisset
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>
