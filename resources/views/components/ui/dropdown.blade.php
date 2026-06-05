@props([
    'align' => 'right',
    'width' => 'w-48',
    'buttonClass' => 'inline-flex items-center gap-x-1.5 rounded-md bg-white px-3 py-2 text-sm font-semibold text-gray-900 shadow-sm ring-1 ring-gray-300 ring-inset hover:bg-gray-50',
    'menuClass' => 'py-1',
    'disabledExpression' => '',
    'titleExpression' => '',
])

@php
    $alignmentClasses = match ($align) {
        'left' => 'left-0 origin-top-left',
        default => 'right-0 origin-top-right',
    };
@endphp

<div
    {{ $attributes->class(['relative inline-block text-left']) }}
    x-data="{ open: false }"
    x-on:click.outside="open = false"
    x-on:keydown.escape.window="open = false"
    x-on:close.stop="open = false"
>
    <div>
        <button
            type="button"
            class="{{ $buttonClass }}"
            id="{{ $attributes->get('id', 'dropdown') }}-button"
            x-on:click="open = ! open"
            x-bind:aria-expanded="open ? 'true' : 'false'"
            @if ($disabledExpression !== '') x-bind:disabled="{{ $disabledExpression }}" @endif
            @if ($titleExpression !== '') x-bind:title="{{ $titleExpression }}" @endif
            aria-haspopup="true"
        >
            {{ $trigger }}
            <svg class="-mr-1 size-5 text-gray-400" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                <path fill-rule="evenodd" d="M5.22 8.22a.75.75 0 0 1 1.06 0L10 11.94l3.72-3.72a.75.75 0 1 1 1.06 1.06l-4.25 4.25a.75.75 0 0 1-1.06 0L5.22 9.28a.75.75 0 0 1 0-1.06Z" clip-rule="evenodd" />
            </svg>
        </button>
    </div>

    <div
        class="absolute {{ $alignmentClasses }} z-50 mt-2 {{ $width }} rounded-md bg-white shadow-lg ring-1 ring-black/5 focus:outline-none"
        x-show="open"
        x-transition:enter="transition ease-out duration-100"
        x-transition:enter-start="scale-95 opacity-0"
        x-transition:enter-end="scale-100 opacity-100"
        x-transition:leave="transition ease-in duration-75"
        x-transition:leave-start="scale-100 opacity-100"
        x-transition:leave-end="scale-95 opacity-0"
        x-cloak
        role="menu"
        aria-orientation="vertical"
        aria-labelledby="{{ $attributes->get('id', 'dropdown') }}-button"
    >
        <div class="{{ $menuClass }}" role="none">
            {{ $slot }}
        </div>
    </div>
</div>
