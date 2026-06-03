@props([
    'steps' => [],
])

@php
    $normalizedSteps = collect($steps)
        ->map(fn ($step, $index) => [
            'label' => (string) data_get($step, 'label', ''),
            'status' => in_array(data_get($step, 'status'), ['completed', 'current', 'upcoming'], true)
                ? data_get($step, 'status')
                : 'upcoming',
            'url' => data_get($step, 'url'),
            'current' => (bool) data_get($step, 'current', data_get($step, 'status') === 'current'),
            'number' => str_pad((string) ($index + 1), 2, '0', STR_PAD_LEFT),
        ])
        ->filter(fn ($step) => $step['label'] !== '')
        ->values();
@endphp

@if ($normalizedSteps->isNotEmpty())
    <nav {{ $attributes->merge(['class' => 'w-full']) }} aria-label="Progress">
        <ol role="list" class="divide-y divide-gray-300 rounded-md border border-gray-300 bg-white md:flex md:divide-y-0">
            @foreach ($normalizedSteps as $step)
                @php
                    $isCompleted = $step['status'] === 'completed';
                    $isCurrent = $step['status'] === 'current' || $step['current'];
                    $tag = $step['url'] ? 'a' : 'span';
                @endphp

                <li class="relative md:flex md:flex-1">
                    @if ($isCompleted)
                        <{{ $tag }}
                            @if ($step['url']) href="{{ $step['url'] }}" @endif
                            class="group flex w-full items-center"
                        >
                            <span class="flex items-center px-4 py-3 text-sm font-medium sm:px-6">
                                <span class="flex size-9 shrink-0 items-center justify-center rounded-full bg-indigo-600 group-hover:bg-indigo-700">
                                    <svg
                                        viewBox="0 0 24 24"
                                        fill="currentColor"
                                        aria-hidden="true"
                                        class="size-5 text-white"
                                    >
                                        <path
                                            fill-rule="evenodd"
                                            clip-rule="evenodd"
                                            d="M19.916 4.626a.75.75 0 0 1 .208 1.04l-9 13.5a.75.75 0 0 1-1.154.114l-6-6a.75.75 0 0 1 1.06-1.06l5.353 5.353 8.493-12.74a.75.75 0 0 1 1.04-.207Z"
                                        />
                                    </svg>
                                </span>
                                <span class="ml-4 text-sm font-medium text-gray-900">{{ $step['label'] }}</span>
                            </span>
                        </{{ $tag }}>
                    @elseif ($isCurrent)
                        <{{ $tag }}
                            @if ($step['url']) href="{{ $step['url'] }}" @endif
                            aria-current="step"
                            class="flex w-full items-center px-4 py-3 text-sm font-medium sm:px-6"
                        >
                            <span class="flex size-9 shrink-0 items-center justify-center rounded-full border-2 border-indigo-600">
                                <span class="text-sm font-semibold text-indigo-600">{{ $step['number'] }}</span>
                            </span>
                            <span class="ml-4 text-sm font-medium text-indigo-600">{{ $step['label'] }}</span>
                        </{{ $tag }}>
                    @else
                        <{{ $tag }}
                            @if ($step['url']) href="{{ $step['url'] }}" @endif
                            class="group flex w-full items-center"
                        >
                            <span class="flex items-center px-4 py-3 text-sm font-medium sm:px-6">
                                <span class="flex size-9 shrink-0 items-center justify-center rounded-full border-2 border-gray-300 group-hover:border-gray-400">
                                    <span class="text-sm font-semibold text-gray-500 group-hover:text-gray-900">{{ $step['number'] }}</span>
                                </span>
                                <span class="ml-4 text-sm font-medium text-gray-500 group-hover:text-gray-900">{{ $step['label'] }}</span>
                            </span>
                        </{{ $tag }}>
                    @endif

                    @if (! $loop->last)
                        <div aria-hidden="true" class="absolute right-0 top-0 hidden h-full w-5 md:block">
                            <svg
                                viewBox="0 0 22 80"
                                fill="none"
                                preserveAspectRatio="none"
                                class="size-full text-gray-300"
                            >
                                <path
                                    d="M0 -2L20 40L0 82"
                                    stroke="currentcolor"
                                    vector-effect="non-scaling-stroke"
                                    stroke-linejoin="round"
                                />
                            </svg>
                        </div>
                    @endif
                </li>
            @endforeach
        </ol>
    </nav>
@endif
