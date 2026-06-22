@props([
    'alpineComponent' => null,
    'alpineData' => [],
    'items' => [],
    'title' => '',
    'titleClass' => 'font-semibold text-2xl leading-tight text-gray-900',
])

@php
    $alpineExpression = $alpineComponent === null
        ? null
        : $alpineComponent . '(' . \Illuminate\Support\Js::from($alpineData)->toHtml() . ')';
@endphp

<div
    {{ $attributes->class(['space-y-0 sm:space-y-4']) }}
    @if ($alpineExpression !== null) x-data="{{ $alpineExpression }}" @endif
    data-resource-detail-header
>
    <div class="order-first w-full" data-resource-detail-breadcrumb>
        <x-ui.breadcrumbs :items="$items" :full-bleed="true" />
    </div>

    @isset($top)
        <div class="-mx-4 w-screen max-w-none sm:mx-0 sm:w-auto sm:max-w-5xl sm:px-6 lg:px-8" data-resource-detail-header-top>
            {{ $top }}
        </div>
    @endisset

    <div class="max-w-5xl px-4 pt-4 pb-4 sm:px-6 sm:pt-6 sm:pb-6 lg:px-8" data-resource-detail-header-body>
        <div class="flex items-center justify-between gap-3 sm:items-center">
            <div class="min-w-0 flex-1">
                @isset($titleAbove)
                    <div class="mb-0.5 text-[0.65rem] font-medium uppercase tracking-wide text-gray-500 sm:text-xs" data-resource-detail-header-title-above>
                        {{ $titleAbove }}
                    </div>
                @endisset

                <div class="flex flex-wrap items-center gap-6 sm:gap-8" data-resource-detail-header-title-row>
                    <h1 @class([$titleClass]) data-resource-detail-header-title>{{ $title }}</h1>

                    @isset($titleSuffix)
                        {{ $titleSuffix }}
                    @endisset
                </div>

                @isset($metadata)
                    <div class="mt-1 space-y-2 sm:mt-2" data-resource-detail-header-metadata>
                        {{ $metadata }}
                    </div>
                @elseif (trim((string) $slot) !== '')
                    <div class="mt-1 sm:mt-2" data-resource-detail-header-metadata>
                        {{ $slot }}
                    </div>
                @endif
            </div>

            @isset($actions)
                <div class="flex shrink-0 items-center justify-end" data-resource-detail-header-actions>
                    {{ $actions }}
                </div>
            @endisset
        </div>
    </div>
</div>
