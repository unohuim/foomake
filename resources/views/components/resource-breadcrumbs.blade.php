@props([
    'items' => [],
])

@php
    $homeUrl = $items[0]['url'] ?? null;
    $trailItems = array_slice($items, 1);
@endphp

<nav
    aria-label="Breadcrumb"
    {{ $attributes->class('relative left-1/2 right-1/2 mt-4 flex w-screen -translate-x-1/2 border-y border-gray-200 bg-white') }}
>
    <ol
        role="list"
        class="mx-auto flex w-full max-w-7xl items-stretch px-4 sm:px-6 lg:px-8"
    >
        <li class="flex items-stretch">
            @if ($homeUrl)
                <a
                    href="{{ $homeUrl }}"
                    class="flex items-center py-3 text-gray-500 transition hover:text-gray-700"
                >
                    <span class="sr-only">Home</span>
                    <svg
                        class="h-5 w-5 shrink-0"
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
            @else
                <span class="flex items-center py-3 text-gray-400">
                    <span class="sr-only">Home</span>
                    <svg
                        class="h-5 w-5 shrink-0"
                        xmlns="http://www.w3.org/2000/svg"
                        fill="none"
                        viewBox="0 0 24 24"
                        stroke-width="1.5"
                        stroke="currentColor"
                        aria-hidden="true"
                    >
                        <path stroke-linecap="round" stroke-linejoin="round" d="m2.25 12 8.954-8.955a1.125 1.125 0 0 1 1.591 0L21.75 12M4.5 9.75v10.125A1.125 1.125 0 0 0 5.625 21h4.5v-5.25c0-.621.504-1.125 1.125-1.125h1.5c.621 0 1.125.504 1.125 1.125V21h4.5a1.125 1.125 0 0 0 1.125-1.125V9.75M8.25 21h7.5" />
                    </svg>
                </span>
            @endif
        </li>

        @foreach ($trailItems as $item)
            @php
                $isLast = $loop->last;
                $label = $item['label'] ?? '';
                $url = $item['url'] ?? null;
            @endphp

            <li class="flex items-stretch">
                <div class="flex items-stretch px-2 sm:px-3" aria-hidden="true">
                    <svg
                        class="h-full w-6 shrink-0 text-gray-200"
                        xmlns="http://www.w3.org/2000/svg"
                        fill="none"
                        viewBox="0 0 24 44"
                        preserveAspectRatio="none"
                        stroke="currentColor"
                    >
                        <path d="M.293 0l22 22-22 22" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" />
                    </svg>
                </div>

                @if ($url && ! $isLast)
                    <a
                        href="{{ $url }}"
                        class="flex items-center py-3 text-sm font-medium text-gray-500 transition hover:text-gray-700"
                    >
                        {{ $label }}
                    </a>
                @elseif ($isLast)
                    <span
                        aria-current="page"
                        class="flex items-center py-3 text-sm font-medium text-gray-700"
                    >
                        {{ $label }}
                    </span>
                @else
                    <span class="flex items-center py-3 text-sm font-medium text-gray-500">
                        {{ $label }}
                    </span>
                @endif
            </li>
        @endforeach
    </ol>
</nav>
