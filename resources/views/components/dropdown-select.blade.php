@props([
    'name',
    'options' => [],
    'optionsExpression' => '',
    'selectedValue' => '',
    'placeholder' => 'Select an option',
    'label' => null,
    'errorMessages' => null,
    'errorExpression' => null,
    'disabledExpression' => '',
])

@php
    $dropdownId = 'dropdown-select-' . \Illuminate\Support\Str::uuid();
    $buttonId = $dropdownId . '-button';
    $listId = $dropdownId . '-listbox';
@endphp

<div
    data-dropdown-select-root
    x-data="dropdownSelect({
        name: @js($name),
        options: {{ \Illuminate\Support\Js::from($options) }},
        optionsExpression: @js($optionsExpression),
        selectedValue: @js($selectedValue),
        placeholder: @js($placeholder),
        buttonId: @js($buttonId),
        listId: @js($listId),
        disabledExpression: @js($disabledExpression),
    })"
    x-modelable="selectedValue"
    x-on:click.outside="closeDropdown()"
    x-on:keydown.arrow-down.prevent="highlightNext()"
    x-on:keydown.arrow-up.prevent="highlightPrevious()"
    x-on:keydown.enter.prevent="selectHighlighted()"
    x-on:keydown.escape.prevent="closeDropdown()"
    {{ $attributes }}
>
    @if ($label)
        <x-input-label :for="$buttonId" :value="$label" />
    @endif

    <div class="relative mt-1">
        <input type="hidden" name="{{ $name }}" x-model="selectedValue" />

        <button
            id="{{ $buttonId }}"
            type="button"
            class="flex w-full items-center justify-between rounded-xl border border-gray-300 bg-white px-4 py-3 text-left text-sm text-gray-900 shadow-sm transition focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-500/20 disabled:cursor-not-allowed disabled:bg-gray-100"
            aria-haspopup="listbox"
            x-bind:aria-expanded="open.toString()"
            x-bind:aria-controls="listId"
            x-bind:aria-activedescendant="activeDescendantId()"
            x-bind:disabled="isDisabled()"
            x-on:click="toggleDropdown()"
        >
            <span
                class="block truncate"
                x-bind:class="selectedLabel === '' ? 'text-gray-500' : 'text-gray-900'"
                x-text="selectedLabel || placeholder"
            ></span>

            <span class="ml-3 text-gray-400">
                <svg class="h-5 w-5" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                    <path fill-rule="evenodd" d="M5.23 7.21a.75.75 0 0 1 1.06.02L10 11.168l3.71-3.938a.75.75 0 1 1 1.08 1.04l-4.25 4.5a.75.75 0 0 1-1.08 0l-4.25-4.5a.75.75 0 0 1 .02-1.06Z" clip-rule="evenodd" />
                </svg>
            </span>
        </button>

        <div x-ref="slotOptions" class="hidden">
            {{ $slot }}
        </div>

        <div
            class="absolute z-20 mt-2 w-full overflow-hidden rounded-2xl border border-gray-200 bg-white p-2 shadow-xl ring-1 ring-black/5"
            x-cloak
            x-show="open"
            role="listbox"
            id="{{ $listId }}"
        >
            <template x-for="(option, index) in allOptions()" :key="option.value">
                <button
                    type="button"
                    class="flex w-full items-center justify-between rounded-xl px-3 py-3 text-left text-sm transition"
                    role="option"
                    x-bind:id="optionDomId(index)"
                    x-bind:aria-selected="isSelected(option).toString()"
                    x-on:mouseenter="highlightedIndex = index"
                    x-on:click="selectOption(option)"
                    x-bind:class="highlightedIndex === index ? 'bg-blue-50 text-blue-900' : 'text-gray-900 hover:bg-gray-50'"
                >
                    <span class="block truncate" x-text="option.label"></span>

                    <span class="ml-3 text-blue-600" x-show="isSelected(option)">
                        <svg class="h-5 w-5" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                            <path fill-rule="evenodd" d="M16.704 5.29a1 1 0 0 1 .006 1.414l-8 8.07a1 1 0 0 1-1.42 0l-4-4.035a1 1 0 0 1 1.42-1.41l3.29 3.32 7.29-7.36a1 1 0 0 1 1.414 0Z" clip-rule="evenodd" />
                        </svg>
                    </span>
                </button>
            </template>
        </div>
    </div>

    @if ($errorMessages)
        <x-input-error :messages="$errorMessages" class="mt-2" />
    @endif

    @if ($errorExpression)
        <p
            class="mt-2 text-sm text-red-600"
            x-show="{{ $errorExpression }}"
            x-text="{{ $errorExpression }}"
        ></p>
    @endif
</div>
