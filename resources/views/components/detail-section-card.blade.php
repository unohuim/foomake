@props([
    'title' => '',
    'description' => '',
    'defaultOpen' => false,
    'createButtonLabel' => 'Create',
    'showCreateButton' => false,
])

<section
    class="overflow-visible rounded-2xl border border-gray-200 bg-white shadow-sm"
    data-detail-section-card
    x-data="{ open: @js((bool) $defaultOpen) }"
>
    <div class="flex items-start justify-between gap-3 px-3 py-4 sm:px-6 sm:py-5">
        <div class="min-w-0 flex-1">
            <h3 class="text-lg font-semibold text-gray-900">{{ $title }}</h3>

            @if ($description !== '')
                <p class="mt-1 text-sm text-gray-500">{{ $description }}</p>
            @endif
        </div>

        <button
            type="button"
            class="inline-flex h-10 w-10 shrink-0 items-center justify-center rounded-lg border border-gray-300 text-gray-600 transition hover:bg-gray-50 hover:text-gray-900"
            x-bind:aria-expanded="open ? 'true' : 'false'"
            x-on:click="open = !open"
            aria-label="Toggle section"
            data-detail-section-toggle
        >
            <svg class="h-5 w-5 text-gray-400 transition" :class="open ? 'rotate-180' : ''" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" aria-hidden="true">
                <path stroke-linecap="round" stroke-linejoin="round" d="m19.5 8.25-7.5 7.5-7.5-7.5" />
            </svg>
        </button>
    </div>

    <div class="border-t border-gray-100 px-3 py-4 sm:px-6 sm:py-5" x-show="open" x-cloak>
        @if (isset($toolbar) || isset($actions) || $showCreateButton)
            <div class="mb-4 flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                <div class="min-w-0 flex-1">
                    {{ $toolbar ?? '' }}
                </div>

                <div class="flex w-full justify-end sm:ml-auto sm:w-auto" data-js-crud-section-create-wrapper>
                    {{ $actions ?? '' }}

                    @if ($showCreateButton)
                        <button
                            type="button"
                            class="inline-flex h-10 w-10 items-center justify-center rounded-lg border border-gray-300 text-gray-600 transition hover:bg-gray-50 hover:text-gray-900"
                            aria-label="{{ $createButtonLabel }}"
                        >
                            <svg class="h-5 w-5" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" aria-hidden="true">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15" />
                            </svg>
                        </button>
                    @endif
                </div>
            </div>
        @endif

        @if (isset($addRowLeft) || isset($addRowRight))
            <div
                class="mb-4 flex flex-col gap-3 sm:flex-row sm:items-end sm:justify-between"
                data-detail-section-add-row
            >
                <div class="min-w-0 flex-1" data-detail-section-add-row-left>
                    {{ $addRowLeft ?? '' }}
                </div>

                <div class="flex justify-end sm:shrink-0" data-detail-section-add-row-right>
                    {{ $addRowRight ?? '' }}
                </div>
            </div>
        @endif

        {{ $slot }}
    </div>
</section>
