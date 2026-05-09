@props([
    'triggerAriaLabel' => 'Actions',
    'showEdit' => false,
    'showArchive' => false,
    'editLabel' => 'Edit',
    'archiveLabel' => 'Archive',
    'editAction' => '',
    'archiveAction' => '',
    'wrapperClass' => '',
])

@php
    $hasMenu = $showEdit || $showArchive;
    $containerClasses = trim('relative inline-flex ' . $wrapperClass);
    $triggerClasses = 'inline-flex h-8 w-8 shrink-0 items-center justify-center rounded-md text-gray-500 transition hover:bg-gray-100 hover:text-gray-700';
    $menuClasses = 'absolute right-0 z-20 mt-2 w-40 rounded-md border border-gray-200 bg-white py-1 shadow-lg';
    $menuItemClasses = 'flex w-full items-center px-3 py-2 text-left text-sm text-gray-700 transition hover:bg-gray-50';
@endphp

<div
    class="{{ $containerClasses }}"
    x-data="{ open: false }"
    data-crud-action-cell
    x-on:keydown.escape.window="open = false"
    x-on:click.outside="open = false"
>
    <button
        type="button"
        class="{{ $triggerClasses }}"
        aria-label="{{ $triggerAriaLabel }}"
        @if ($hasMenu)
            aria-haspopup="menu"
            x-bind:aria-expanded="open ? 'true' : 'false'"
            x-on:click="open = !open"
        @else
            aria-expanded="false"
            x-on:click.prevent
        @endif
        data-crud-action-trigger
    >
        <svg class="h-5 w-5" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" aria-hidden="true">
            <path stroke-linecap="round" stroke-linejoin="round" d="M12 6.75a.75.75 0 1 0 0-1.5.75.75 0 0 0 0 1.5Zm0 6a.75.75 0 1 0 0-1.5.75.75 0 0 0 0 1.5Zm0 6a.75.75 0 1 0 0-1.5.75.75 0 0 0 0 1.5Z" />
        </svg>
    </button>

    @if ($hasMenu)
        <div
            class="{{ $menuClasses }}"
            x-show="open"
            x-cloak
            data-crud-action-menu
            role="menu"
        >
            @if ($showEdit)
                <button
                    type="button"
                    class="{{ $menuItemClasses }}"
                    data-crud-action-item-edit
                    role="menuitem"
                    x-on:click="open = false; {{ $editAction }}"
                >
                    {{ $editLabel }}
                </button>
            @endif

            @if ($showArchive)
                <button
                    type="button"
                    class="{{ $menuItemClasses }} text-yellow-700 hover:bg-yellow-50 hover:text-yellow-800"
                    data-crud-action-item-archive
                    role="menuitem"
                    x-on:click="open = false; {{ $archiveAction }}"
                >
                    {{ $archiveLabel }}
                </button>
            @endif
        </div>
    @endif
</div>
