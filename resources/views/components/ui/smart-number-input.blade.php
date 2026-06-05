@props([
    'name',
    'value' => '',
    'type' => 'decimal',
    'currency' => null,
    'precision' => null,
    'min' => null,
    'max' => null,
    'step' => null,
    'placeholder' => '',
    'disabled' => false,
    'disabledExpression' => null,
    'readonly' => false,
    'readonlyExpression' => null,
    'required' => false,
    'event' => null,
    'emitOnInput' => true,
    'emitOnChange' => true,
    'emitOnBlur' => true,
    'debounceMs' => 300,
    'afterInput' => null,
    'afterChange' => null,
    'afterBlur' => null,
    'afterFocus' => null,
    'prefix' => null,
    'suffix' => null,
    'rawMode' => 'value',
    'inputmode' => null,
    'autocomplete' => 'off',
])

@php
    $resolvedPrecision = $precision;

    if ($resolvedPrecision === null) {
        $resolvedPrecision = match ($type) {
            'integer' => 0,
            'money' => 2,
            'percent' => 2,
            default => 6,
        };
    }

    $resolvedInputmode = $inputmode ?? ($type === 'integer' ? 'numeric' : 'decimal');
    $resolvedSuffix = $suffix ?? ($type === 'percent' ? '%' : null);
    $resolvedCurrency = $currency ? strtoupper((string) $currency) : null;
    $resolvedPrefix = $prefix ?? ($type === 'money' ? '$' : null);
    $inputId = $attributes->get('id') ?? 'smart-number-' . \Illuminate\Support\Str::uuid();
    $rootAttributes = $attributes->except('id');
@endphp

<div
    data-smart-number-input-root
    x-data="smartNumberInput({
        name: @js($name),
        value: @js((string) $value),
        type: @js($type),
        currency: @js($resolvedCurrency),
        precision: {{ (int) $resolvedPrecision }},
        event: @js($event),
        emitOnInput: @js((bool) $emitOnInput),
        emitOnChange: @js((bool) $emitOnChange),
        emitOnBlur: @js((bool) $emitOnBlur),
        debounceMs: {{ (int) $debounceMs }},
        rawMode: @js($rawMode),
    })"
    x-modelable="rawValue"
    {{ $rootAttributes->merge(['class' => 'w-full']) }}
>
    <div
        class="mt-1 flex w-full items-center rounded-md border border-gray-300 shadow-sm transition focus-within:border-blue-500 focus-within:ring-2 focus-within:ring-blue-500/20 {{ $disabled || $readonly ? 'bg-gray-50 text-gray-500' : 'bg-white text-gray-900' }}"
        @if ($disabledExpression || $readonlyExpression)
            x-bind:class="{{ $disabledExpression ?: $readonlyExpression }} ? 'bg-gray-50 text-gray-500' : 'bg-white text-gray-900'"
        @endif
    >
        @if ($resolvedPrefix)
            <span class="pointer-events-none flex shrink-0 items-center pl-3 text-sm text-gray-500">
                {{ $resolvedPrefix }}
            </span>
        @endif

        <input
            id="{{ $inputId }}"
            type="text"
            inputmode="{{ $resolvedInputmode }}"
            autocomplete="{{ $autocomplete }}"
            class="block min-w-0 flex-1 border-0 bg-transparent px-3 py-2 text-sm text-gray-900 placeholder:text-gray-400 focus:outline-none focus:ring-0 disabled:cursor-not-allowed disabled:bg-transparent disabled:text-gray-500"
            placeholder="{{ $placeholder }}"
            x-model="displayValue"
            x-bind:size="inputSize()"
            x-on:input="handleInput($event){{ $afterInput ? '; ' . $afterInput : '' }}"
            x-on:change="handleChange($event){{ $afterChange ? '; ' . $afterChange : '' }}"
            x-on:blur="handleBlur($event){{ $afterBlur ? '; ' . $afterBlur : '' }}"
            @if ($afterFocus) x-on:focus="{{ $afterFocus }}" @endif
            @if ($disabledExpression) x-bind:disabled="{{ $disabledExpression }}" @endif
            @if ($readonlyExpression) x-bind:readonly="{{ $readonlyExpression }}" @endif
            @disabled($disabled)
            @readonly($readonly)
            @required($required)
            @if ($readonly) aria-readonly="true" @endif
            @if ($min !== null) min="{{ $min }}" @endif
            @if ($max !== null) max="{{ $max }}" @endif
            @if ($step !== null) step="{{ $step }}" @endif
        />

        @if ($resolvedSuffix)
            <span class="pointer-events-none flex shrink-0 items-center pr-3 text-sm text-gray-500">
                {{ $resolvedSuffix }}
            </span>
        @endif

        @if ($type === 'money' && $resolvedCurrency)
            <span class="pointer-events-none flex shrink-0 items-center border-l border-gray-200 px-3 text-xs font-medium uppercase tracking-wide text-gray-500">
                {{ $resolvedCurrency }}
            </span>
        @endif
    </div>

    <input type="hidden" name="{{ $name }}" x-model="rawValue" />
</div>
