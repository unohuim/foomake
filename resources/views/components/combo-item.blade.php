@props([
    'value' => '',
    'label' => '',
    'description' => '',
    'searchText' => '',
    'meta' => [],
])

@php
    $item = [
        'value' => $value,
        'label' => $label !== '' ? $label : trim((string) $slot),
        'description' => $description,
        'search_text' => $searchText,
        'meta' => $meta,
    ];

    $encodedItem = e(json_encode($item, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT));
@endphp

<div data-combo-item data-item="{{ $encodedItem }}" class="hidden">
    {{ $slot !== '' ? $slot : $label }}
</div>
