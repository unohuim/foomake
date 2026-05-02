@props([
    'align' => 'left',
    'width' => '64',
    'mobile' => false,
    'active' => false,
    'panelId' => null,
])

@php
$alignmentClasses = match ($align) {
    'right' => 'right-0 origin-top-right',
    default => 'left-0 origin-top-left',
};

$widthClasses = match ($width) {
    '64' => 'w-64',
    default => $width,
};

$desktopTriggerClasses = $active
    ? 'inline-flex items-center gap-2 rounded-full border border-slate-600 bg-slate-800 px-4 py-2 text-sm font-medium text-white shadow-sm shadow-slate-950/25 transition duration-200 ease-out'
    : 'inline-flex items-center gap-2 rounded-full border border-transparent px-4 py-2 text-sm font-medium text-slate-200 transition duration-200 ease-out hover:border-slate-700 hover:bg-slate-800/80 hover:text-white';

$mobileTriggerClasses = $active
    ? 'flex w-full items-center justify-between rounded-xl border border-slate-700 bg-slate-800/90 px-4 py-3 text-left text-sm font-medium text-white shadow-sm transition duration-200 ease-out'
    : 'flex w-full items-center justify-between rounded-xl border border-transparent px-4 py-3 text-left text-sm font-medium text-slate-200 transition duration-200 ease-out hover:border-slate-700 hover:bg-slate-800/70 hover:text-white';

$desktopPanelClasses = trim($widthClasses . ' absolute z-50 mt-3 rounded-2xl border border-slate-700/80 bg-slate-900/95 p-2 shadow-xl shadow-slate-950/40 backdrop-blur ' . $alignmentClasses);
$mobilePanelId = $panelId ?: 'nav-panel-' . md5((string) $slot);
@endphp

@if ($mobile)
    <div class="space-y-2" x-data="{ open: @js($active) }" data-nav-mobile-group="{{ $attributes->get('data-nav-mobile-group') }}">
        <button
            type="button"
            class="{{ $mobileTriggerClasses }}"
            x-on:click="open = !open"
            x-bind:aria-expanded="open ? 'true' : 'false'"
            aria-controls="{{ $mobilePanelId }}"
            {{ $attributes->except('data-nav-mobile-group') }}
        >
            <span>{{ $trigger }}</span>
            <svg class="h-4 w-4 transition duration-200 ease-out" x-bind:class="open ? 'rotate-180' : ''" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                <path fill-rule="evenodd" d="M5.23 7.21a.75.75 0 011.06.02L10 11.168l3.71-3.938a.75.75 0 111.08 1.04l-4.25 4.512a.75.75 0 01-1.08 0L5.21 8.27a.75.75 0 01.02-1.06z" clip-rule="evenodd" />
            </svg>
        </button>

        <div
            id="{{ $mobilePanelId }}"
            class="space-y-2 rounded-2xl border border-slate-800 bg-slate-950/60 p-2"
            x-cloak
            x-show="open"
            x-transition:enter="transition ease-out duration-200"
            x-transition:enter-start="opacity-0 -translate-y-1"
            x-transition:enter-end="opacity-100 translate-y-0"
            x-transition:leave="transition ease-in duration-150"
            x-transition:leave-start="opacity-100 translate-y-0"
            x-transition:leave-end="opacity-0 -translate-y-1"
        >
            {{ $content }}
        </div>
    </div>
@else
    <div class="relative" x-data="{ open: false }" x-on:click.outside="open = false">
        <button
            type="button"
            class="{{ $desktopTriggerClasses }}"
            x-on:click="open = !open"
            x-bind:aria-expanded="open ? 'true' : 'false'"
            {{ $attributes }}
        >
            <span>{{ $trigger }}</span>
            <svg class="h-4 w-4 transition duration-200 ease-out" x-bind:class="open ? 'rotate-180' : ''" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                <path fill-rule="evenodd" d="M5.23 7.21a.75.75 0 011.06.02L10 11.168l3.71-3.938a.75.75 0 111.08 1.04l-4.25 4.512a.75.75 0 01-1.08 0L5.21 8.27a.75.75 0 01.02-1.06z" clip-rule="evenodd" />
            </svg>
        </button>

        <div
            class="{{ $desktopPanelClasses }}"
            x-cloak
            x-show="open"
            x-transition:enter="transition ease-out duration-200"
            x-transition:enter-start="opacity-0 translate-y-1 scale-95"
            x-transition:enter-end="opacity-100 translate-y-0 scale-100"
            x-transition:leave="transition ease-in duration-150"
            x-transition:leave-start="opacity-100 translate-y-0 scale-100"
            x-transition:leave-end="opacity-0 translate-y-1 scale-95"
        >
            {{ $content }}
        </div>
    </div>
@endif
