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
    {{ $attributes->class(['space-y-4']) }}
    @if ($alpineExpression !== null) x-data="{{ $alpineExpression }}" @endif
    data-resource-detail-header
>
    <div class="order-first w-full" data-resource-detail-breadcrumb>
        <x-ui.breadcrumbs :items="$items" :full-bleed="true" />
    </div>

    <div class="max-w-5xl px-4 sm:px-6 lg:px-8" data-resource-detail-header-body>
        <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
            <div class="min-w-0 flex-1">
                <div class="flex flex-wrap items-center gap-3" data-resource-detail-header-title-row>
                    <h1 @class([$titleClass])>{{ $title }}</h1>

                    @isset($titleSuffix)
                        {{ $titleSuffix }}
                    @endisset
                </div>

                @isset($metadata)
                    <div class="mt-2 space-y-2" data-resource-detail-header-metadata>
                        {{ $metadata }}
                    </div>
                @elseif (trim((string) $slot) !== '')
                    <div class="mt-2" data-resource-detail-header-metadata>
                        {{ $slot }}
                    </div>
                @endif
            </div>

            @isset($actions)
                <div class="flex shrink-0 items-start justify-end" data-resource-detail-header-actions>
                    {{ $actions }}
                </div>
            @endisset
        </div>
    </div>
</div>
