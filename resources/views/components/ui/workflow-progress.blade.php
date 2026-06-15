@props([
    'steps' => [],
    'do_draft' => false,
    'neutral_draft' => false,
])

@php
    $shouldShowDraft = filter_var($do_draft, FILTER_VALIDATE_BOOLEAN);
    $isNeutralDraft = filter_var($neutral_draft, FILTER_VALIDATE_BOOLEAN);
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
        ->filter(fn ($step) => $step['label'] !== '' && ($shouldShowDraft || $step['label'] !== 'DRAFT'))
        ->values()
        ->map(fn ($step, $index) => array_merge($step, [
            'number' => str_pad((string) ($index + 1), 2, '0', STR_PAD_LEFT),
        ]))
        ->values();

    $hasActiveStep = $normalizedSteps->where('current', true)->isNotEmpty()
        || $normalizedSteps->where('status', 'completed')->isNotEmpty();

    if (
        ! $isNeutralDraft
        && $normalizedSteps->isNotEmpty()
        && ! $hasActiveStep
    ) {
        $normalizedSteps = $normalizedSteps
            ->map(fn ($step, $index) => array_merge($step, [
                'status' => $index === 0 ? 'current' : $step['status'],
                'current' => $index === 0,
            ]))
            ->values();

        $hasActiveStep = true;
    }

    $activeMobileStep = null;

    if ($normalizedSteps->isNotEmpty() && ($hasActiveStep || ! $isNeutralDraft)) {
        $activeMobileStep = $normalizedSteps->search(fn ($step) => $step['current'] || $step['status'] === 'current');

        if ($activeMobileStep === false) {
            $activeMobileStep = $normalizedSteps->search(fn ($step) => $step['status'] === 'upcoming');
        }

        if ($activeMobileStep === false) {
            $activeMobileStep = max(0, $normalizedSteps->count() - 1);
        }
    }
@endphp

@if ($normalizedSteps->isNotEmpty())
    <nav
        {{ $attributes->merge(['class' => 'w-full']) }}
        aria-label="Progress"
        x-data="{ activeWorkflowStep: @js($activeMobileStep) }"
        x-cloak
    >
        <div
            class="flex overflow-hidden rounded-md border border-gray-300 bg-white md:hidden"
            role="tablist"
            aria-label="Workflow stages"
            data-workflow-progress-mobile-tabs
        >
            @foreach ($normalizedSteps as $step)
                @php
                    $isCompleted = $step['status'] === 'completed';
                    $isCurrent = $step['status'] === 'current' || $step['current'];
                    $mobileCircleClasses = match (true) {
                        $isCompleted => 'border-indigo-600 bg-indigo-600 text-white',
                        $isCurrent => 'border-indigo-600 bg-white text-indigo-600',
                        default => 'border-gray-300 bg-white text-gray-500',
                    };
                @endphp

                <button
                    type="button"
                    role="tab"
                    class="relative min-h-16 overflow-hidden bg-white py-2 pl-3 pr-6 transition-[flex-basis,flex-grow] duration-300 ease-out will-change-[flex-basis]"
                    :class="activeWorkflowStep === {{ $loop->index }} ? 'basis-0 grow' : 'basis-16 grow-0'"
                    :aria-selected="activeWorkflowStep === {{ $loop->index }} ? 'true' : 'false'"
                    x-on:click="activeWorkflowStep = {{ $loop->index }}"
                >
                    <span
                        class="flex h-full items-center"
                        :class="activeWorkflowStep === {{ $loop->index }} ? 'justify-start gap-3' : 'justify-center'"
                    >
                        <span class="flex size-9 shrink-0 items-center justify-center rounded-full border-2 {{ $mobileCircleClasses }}">
                            @if ($isCompleted)
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
                            @else
                                <span class="text-sm font-semibold">{{ $step['number'] }}</span>
                            @endif
                        </span>

                        <span
                            class="min-w-0 truncate text-left text-sm font-medium transition-[max-width,opacity,transform] duration-300 ease-out {{ $isCurrent ? 'text-indigo-600' : 'text-gray-900' }}"
                            :class="activeWorkflowStep === {{ $loop->index }} ? 'max-w-48 translate-x-0 opacity-100' : 'max-w-0 -translate-x-1 opacity-0'"
                        >
                            {{ $step['label'] }}
                        </span>
                    </span>

                    @if (! $loop->last)
                        <span aria-hidden="true" class="pointer-events-none absolute right-0 top-0 h-full w-5 md:hidden">
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
                        </span>
                    @endif
                </button>
            @endforeach
        </div>

        <ol role="list" class="hidden divide-y divide-gray-300 rounded-md border border-gray-300 bg-white md:flex md:divide-y-0">
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
