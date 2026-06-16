@props([
    'title' => __('Working...'),
    'subtitle' => __('Waiting for the server response.'),
])

<div
    {{ $attributes->merge(['class' => 'fixed inset-0 z-50 flex items-center justify-center bg-slate-950/35 px-4 py-6 backdrop-blur-sm']) }}
    aria-live="polite"
    aria-busy="true"
>
    <div class="flex max-w-sm flex-col items-center rounded-2xl bg-white px-6 py-5 text-center shadow-2xl ring-1 ring-black/5">
        <svg class="h-10 w-10 animate-spin text-indigo-600" viewBox="0 0 24 24" fill="none" aria-hidden="true">
            <circle class="opacity-20" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="3"></circle>
            <path class="opacity-90" fill="currentColor" d="M12 2a10 10 0 0 1 10 10h-3a7 7 0 1 0-7 7v3A10 10 0 0 1 12 2z"></path>
        </svg>
        <p class="mt-3 text-sm font-semibold text-slate-700">{{ $title }}</p>
        <p class="mt-1 text-xs text-slate-500">{{ $subtitle }}</p>
    </div>
</div>
