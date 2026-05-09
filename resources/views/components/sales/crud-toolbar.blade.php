@props([
    'variant' => 'desktop',
    'searchPlaceholder' => 'Search',
    'importTitle' => 'Import',
    'importAriaLabel' => 'Import',
    'createTitle' => 'Create',
    'createAriaLabel' => 'Create',
    'showImport' => false,
    'showCreate' => true,
])

@php
    $isMobile = $variant === 'mobile';
    $wrapperClasses = $isMobile
        ? 'md:hidden'
        : 'hidden md:block';
    $toolbarClasses = $isMobile
        ? 'sticky top-0 z-10 border-b border-gray-100 bg-white p-4'
        : 'sticky top-0 z-20 border-b border-gray-100 bg-white px-6 py-4';
    $buttonClasses = $isMobile
        ? 'inline-flex h-10 w-10 shrink-0 items-center justify-center rounded-md border border-gray-300 text-gray-600 transition hover:bg-gray-50 hover:text-gray-900'
        : 'inline-flex h-10 w-10 items-center justify-center rounded-md border border-gray-300 text-gray-600 transition hover:bg-gray-50 hover:text-gray-900';
@endphp

<div
    class="{{ $wrapperClasses }}"
    @if ($isMobile)
        data-crud-toolbar-mobile
    @else
        data-crud-toolbar-desktop
    @endif
>
    <div class="{{ $toolbarClasses }}">
        <div class="flex items-center gap-3" data-crud-toolbar>
            <div class="relative flex-1" data-crud-toolbar-search>
                <div class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-3 text-gray-400">
                    <svg class="h-5 w-5" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" d="m21 21-4.35-4.35m0 0A7.95 7.95 0 1 0 5.4 5.4a7.95 7.95 0 0 0 11.25 11.25Z" />
                    </svg>
                </div>
                <input
                    type="search"
                    class="block w-full rounded-md border-gray-300 pl-10 text-sm shadow-sm focus:border-blue-500 focus:ring-blue-500"
                    placeholder="{{ $searchPlaceholder }}"
                    aria-label="{{ $searchPlaceholder }}"
                    x-model="search"
                    x-on:input.debounce.200ms="handleSearchInput()"
                />
                <div
                    class="pointer-events-none absolute inset-y-0 right-0 flex items-center pr-3 text-gray-400 transition-opacity duration-150"
                    :class="isLoadingList ? 'opacity-100' : 'opacity-0'"
                    aria-hidden="true"
                >
                    <svg class="h-4 w-4 animate-pulse" xmlns="http://www.w3.org/2000/svg" fill="currentColor" viewBox="0 0 24 24">
                        <circle cx="12" cy="12" r="3" />
                    </svg>
                </div>
            </div>

            @if ($showImport)
                <button
                    type="button"
                    class="{{ $buttonClasses }}"
                    title="{{ $importTitle }}"
                    aria-label="{{ $importAriaLabel }}"
                    data-crud-toolbar-import-button
                    x-on:click="openImportPanel()"
                >
                    <svg class="h-5 w-5" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 16.5V4.5m0 12 4.5-4.5M12 16.5l-4.5-4.5M3.75 19.5h16.5" />
                    </svg>
                </button>
            @endif

            @if ($showCreate)
                <button
                    type="button"
                    class="{{ $buttonClasses }}"
                    title="{{ $createTitle }}"
                    aria-label="{{ $createAriaLabel }}"
                    data-crud-toolbar-create-button
                    x-on:click="openCreatePanel()"
                >
                    <svg class="h-5 w-5" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15" />
                    </svg>
                </button>
            @endif
        </div>
    </div>
</div>
