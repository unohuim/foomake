@props([
    'title' => '',
    'description' => '',
    'defaultOpen' => false,
    'createButtonLabel' => 'Create',
    'showCreateButton' => false,
])

<section
    class="-mx-1 !-mt-px w-auto min-w-0 overflow-visible border border-gray-500 bg-white shadow-sm first:!mt-0 sm:mx-0 sm:!mt-6 sm:first:!mt-0 sm:w-full sm:rounded-2xl sm:border-gray-200"
    data-detail-section-card
    x-data="{
        open: @js((bool) $defaultOpen),
        descriptionExpanded: @js((bool) $defaultOpen),
        descriptionTimer: null,
        toggleSection() {
            this.open = !this.open;
            clearTimeout(this.descriptionTimer);
            this.descriptionTimer = setTimeout(() => {
                this.descriptionExpanded = this.open;
            }, 400);
        },
    }"
>
    <div class="flex items-start justify-between gap-2 bg-blue-50 px-3 py-4 sm:gap-3 sm:px-6 sm:py-5">
        <div class="min-w-0 flex-1">
            <h3 class="text-sm font-semibold text-gray-900 sm:text-lg">{{ $title }}</h3>

            @if ($description !== '')
                <p
                    class="mt-0.5 text-[0.65rem] text-gray-500 sm:mt-1 sm:overflow-visible sm:whitespace-normal sm:text-sm sm:text-clip"
                    :class="descriptionExpanded ? 'whitespace-normal' : 'truncate'"
                >
                    {{ $description }}
                </p>
            @endif
        </div>

        <button
            type="button"
            class="inline-flex h-5 w-5 shrink-0 items-center justify-center rounded-md border border-gray-300 text-gray-600 transition hover:bg-gray-50 hover:text-gray-900 sm:h-8 sm:w-8 sm:rounded-lg"
            x-bind:aria-expanded="open ? 'true' : 'false'"
            x-on:click="toggleSection()"
            aria-label="Toggle section"
            data-detail-section-toggle
        >
            <svg class="h-2.5 w-2.5 text-gray-400 transition duration-[400ms] ease-in-out sm:h-4 sm:w-4" :class="open ? 'rotate-180' : ''" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" aria-hidden="true">
                <path stroke-linecap="round" stroke-linejoin="round" d="m19.5 8.25-7.5 7.5-7.5-7.5" />
            </svg>
        </button>
    </div>

    <div
        class="grid min-w-0 transition-[grid-template-rows] duration-[400ms] ease-in-out"
        :class="open ? 'grid-rows-[1fr]' : 'grid-rows-[0fr]'"
        :aria-hidden="open ? 'false' : 'true'"
        x-cloak
    >
        <div class="min-h-0 overflow-hidden">
            <div
                class="border-t border-gray-100 bg-white px-3 py-2 opacity-0 transition-opacity duration-[400ms] ease-in-out sm:px-6 sm:py-5"
                :class="open ? 'opacity-100' : 'opacity-0'"
                data-detail-section-body
            >
                @if (isset($toolbar) || isset($actions) || $showCreateButton)
                    <div class="mb-2 flex flex-col gap-2 sm:mb-4 sm:flex-row sm:items-center sm:justify-between sm:gap-3">
                        <div class="min-w-0 flex-1">
                            {{ $toolbar ?? '' }}
                        </div>

                        <div class="flex w-full justify-end sm:ml-auto sm:w-auto" data-js-crud-section-create-wrapper>
                            {{ $actions ?? '' }}

                            @if ($showCreateButton)
                                <button
                                    type="button"
                                    class="inline-flex h-8 w-8 items-center justify-center rounded-lg border border-gray-300 text-gray-600 transition hover:bg-gray-50 hover:text-gray-900 sm:h-10 sm:w-10"
                                    aria-label="{{ $createButtonLabel }}"
                                >
                                    <svg class="h-4 w-4 sm:h-5 sm:w-5" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" aria-hidden="true">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15" />
                                    </svg>
                                </button>
                            @endif
                        </div>
                    </div>
                @endif

                @if (isset($addRowLeft) || isset($addRowRight))
                    <div
                        class="mb-2 flex flex-col gap-2 sm:mb-4 sm:flex-row sm:items-end sm:justify-between sm:gap-3"
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
        </div>
    </div>
</section>
