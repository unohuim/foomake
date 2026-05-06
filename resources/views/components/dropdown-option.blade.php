@props([
    'value' => '',
    'label' => '',
    'meta' => [],
])

@php
    $option = [
        'value' => $value,
        'label' => $label !== '' ? $label : trim((string) $slot),
        'meta' => $meta,
    ];

    $encodedOption = e(json_encode($option, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT));
@endphp

<div data-dropdown-option data-option="{{ $encodedOption }}" class="hidden">
    {{ $slot !== '' ? $slot : $label }}
</div>
