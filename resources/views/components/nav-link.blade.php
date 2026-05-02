@props([
    'active' => false,
    'mobile' => false,
    'as' => 'a',
])

@php
$classes = $mobile
    ? (($active ?? false)
        ? 'flex w-full items-center justify-between rounded-xl border border-slate-700 bg-slate-800/90 px-4 py-3 text-left text-sm font-medium text-white shadow-sm transition duration-200 ease-out'
        : 'flex w-full items-center justify-between rounded-xl border border-transparent px-4 py-3 text-left text-sm font-medium text-slate-200 transition duration-200 ease-out hover:border-slate-700 hover:bg-slate-800/70 hover:text-white')
    : (($active ?? false)
        ? 'inline-flex items-center rounded-full border border-slate-600 bg-slate-800 px-4 py-2 text-sm font-medium text-white shadow-sm shadow-slate-950/25 transition duration-200 ease-out'
        : 'inline-flex items-center rounded-full border border-transparent px-4 py-2 text-sm font-medium text-slate-200 transition duration-200 ease-out hover:border-slate-700 hover:bg-slate-800/80 hover:text-white');

$ariaCurrent = ($active ?? false) ? 'page' : null;
@endphp

@if ($as === 'button')
    <button {{ $attributes->merge(['class' => $classes, 'aria-current' => $ariaCurrent, 'type' => 'button']) }}>
        {{ $slot }}
    </button>
@else
    <a {{ $attributes->merge(['class' => $classes, 'aria-current' => $ariaCurrent]) }}>
        {{ $slot }}
    </a>
@endif
