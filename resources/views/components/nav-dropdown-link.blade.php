@props([
    'active' => false,
    'mobile' => false,
    'as' => 'a',
])

@php
$classes = $mobile
    ? (($active ?? false)
        ? 'block w-full rounded-xl border border-slate-700 bg-slate-800 px-4 py-3 text-left text-sm font-medium text-white shadow-sm transition duration-200 ease-out'
        : 'block w-full rounded-xl border border-transparent px-4 py-3 text-left text-sm font-medium text-slate-200 transition duration-200 ease-out hover:border-slate-700 hover:bg-slate-800/70 hover:text-white')
    : (($active ?? false)
        ? 'block w-full rounded-xl border border-slate-700 bg-slate-800 px-4 py-3 text-left text-sm font-medium text-white shadow-sm transition duration-200 ease-out'
        : 'block w-full rounded-xl border border-transparent px-4 py-3 text-left text-sm font-medium text-slate-200 transition duration-200 ease-out hover:border-slate-700 hover:bg-slate-800/70 hover:text-white');

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
