@props([
    'items' => [],
    'fullBleed' => false,
])

@php
    $normalizedItems = collect($items)
        ->map(fn ($item) => [
            'label' => (string) data_get($item, 'label', ''),
            'url' => data_get($item, 'url'),
            'current' => data_get($item, 'current'),
        ])
        ->filter(fn ($item) => $item['label'] !== '')
        ->values();

    if (($normalizedItems->first()['label'] ?? null) === 'Home') {
        $normalizedItems = $normalizedItems->slice(1)->values();
    }

    $explicitCurrentIndex = $normalizedItems->search(fn ($item) => $item['current'] === true);
    $currentIndex = $explicitCurrentIndex === false ? $normalizedItems->count() - 1 : $explicitCurrentIndex;
    $homeUrl = \Illuminate\Support\Facades\Route::has('dashboard') ? route('dashboard') : url('/');
    $navClasses = $fullBleed
        ? 'relative left-1/2 right-1/2 flex w-screen -translate-x-1/2 border-y border-gray-200 bg-white'
        : 'flex w-full border-y border-gray-200 bg-white';
    $listClasses = $fullBleed
        ? 'mx-auto flex w-full max-w-7xl items-center space-x-4 px-4 sm:px-6 lg:px-8'
        : 'flex w-full items-center space-x-4 px-4 sm:px-6 lg:px-8';
@endphp

<nav
    {{ $attributes->merge(['class' => $navClasses]) }}
    aria-label="Breadcrumb"
>
    <ol role="list" class="{{ $listClasses }}">
        <li class="flex">
            <div class="flex items-center">
                <a href="{{ $homeUrl }}" class="py-1.5 text-gray-400 transition hover:text-gray-500">
                    <span class="sr-only">Home</span>
                    <svg
                        class="size-4 shrink-0"
                        xmlns="http://www.w3.org/2000/svg"
                        fill="none"
                        viewBox="0 0 24 24"
                        stroke-width="1.5"
                        stroke="currentColor"
                        aria-hidden="true"
                    >
                        <path stroke-linecap="round" stroke-linejoin="round" d="m2.25 12 8.954-8.955a1.125 1.125 0 0 1 1.591 0L21.75 12M4.5 9.75v10.125A1.125 1.125 0 0 0 5.625 21h4.5v-5.25c0-.621.504-1.125 1.125-1.125h1.5c.621 0 1.125.504 1.125 1.125V21h4.5a1.125 1.125 0 0 0 1.125-1.125V9.75M8.25 21h7.5" />
                    </svg>
                </a>
            </div>
        </li>

        @foreach ($normalizedItems as $item)
            @php
                $isCurrent = $loop->index === $currentIndex || $item['current'] === true;
                $url = $item['url'];
            @endphp

            <li class="flex">
                <div class="flex items-center">
                    <svg
                        class="h-6 w-4 shrink-0 text-gray-300"
                        viewBox="0 0 24 44"
                        preserveAspectRatio="none"
                        fill="currentColor"
                        aria-hidden="true"
                    >
                        <path d="M.293 0l22 22-22 22h1.414l22-22-22-22H.293z" />
                    </svg>

                    @if ($url && ! $isCurrent)
                        <a href="{{ $url }}" class="ml-4 py-1.5 text-xs font-medium text-gray-500 transition hover:text-gray-700">
                            {{ $item['label'] }}
                        </a>
                    @elseif ($url && $isCurrent)
                        <a href="{{ $url }}" aria-current="page" class="ml-4 py-1.5 text-xs font-medium text-gray-700 transition hover:text-gray-900">
                            {{ $item['label'] }}
                        </a>
                    @else
                        <span
                            @if ($isCurrent) aria-current="page" @endif
                            class="ml-4 py-1.5 text-xs font-medium {{ $isCurrent ? 'text-gray-700' : 'text-gray-500' }}"
                        >
                            {{ $item['label'] }}
                        </span>
                    @endif
                </div>
            </li>
        @endforeach
    </ol>
</nav>
