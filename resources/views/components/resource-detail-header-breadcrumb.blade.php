@props([
    'items' => [],
    'title' => '',
    'titleClass' => 'font-semibold text-2xl leading-tight text-gray-900',
])

<div class="space-y-4" data-resource-detail-header>
    <div class="max-w-5xl px-1 sm:px-6 lg:px-8" data-resource-detail-header-body>
        <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
            <div class="min-w-0 flex-1">
                <h1 @class([$titleClass])>{{ $title }}</h1>

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

    <div class="order-last max-w-5xl px-1 sm:px-6 lg:px-8" data-resource-detail-breadcrumb>
        <x-resource-breadcrumbs :items="$items" :full-bleed="false" />
    </div>
</div>
