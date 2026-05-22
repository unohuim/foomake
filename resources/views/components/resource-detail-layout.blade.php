<x-app-layout :sticky-shell="true">
    <x-slot name="header">
        {{ $header ?? '' }}
    </x-slot>

    <div {{ $attributes->class(['relative flex h-full min-h-0 flex-col']) }} data-resource-detail-layout>
        <div class="min-h-0 flex-1 overflow-y-auto" data-resource-detail-scroll>
            {{ $slot }}
        </div>

        {{ $overlays ?? '' }}
    </div>
</x-app-layout>
