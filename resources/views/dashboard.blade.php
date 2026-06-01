<x-app-layout>
    @php
        $todo = $todo ?? ['responsibilities' => [], 'stageTasks' => []];
        $responsibilities = $todo['responsibilities'] ?? [];
        $stageTasks = $todo['stageTasks'] ?? [];
        $hasTodo = count($responsibilities) > 0 || count($stageTasks) > 0;
    @endphp

    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            {{ __('Dashboard') }}
        </h2>
    </x-slot>

    <div class="py-12">
        <div class="mx-auto max-w-7xl space-y-6 sm:px-6 lg:px-8">
            <div data-dashboard-todo-section>
                <x-detail-section-card
                    title="Todo"
                    description="Assigned workflow responsibilities and stage tasks."
                    :default-open="true"
                >
                    @if (! $hasTodo)
                        <div class="rounded-lg border border-dashed border-gray-200 bg-gray-50 px-4 py-5 text-sm text-gray-600">
                            No assigned work.
                        </div>
                    @else
                        <div class="space-y-6">
                            <div>
                                <h4 class="text-sm font-semibold text-gray-900">Workflow Responsibilities</h4>

                                @if (count($responsibilities) === 0)
                                    <p class="mt-3 text-sm text-gray-500">No assigned responsibilities.</p>
                                @else
                                    <div class="mt-3 overflow-hidden rounded-lg border border-gray-200">
                                        <ul class="divide-y divide-gray-200">
                                            @foreach ($responsibilities as $responsibility)
                                                <li class="px-4 py-3">
                                                    <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                                                        <div class="min-w-0">
                                                            <a
                                                                href="{{ $responsibility['url'] }}"
                                                                class="font-medium text-gray-900 underline-offset-2 hover:text-gray-700 hover:underline"
                                                            >
                                                                {{ $responsibility['title'] }}
                                                            </a>
                                                            <div class="mt-1 flex flex-wrap gap-x-4 gap-y-1 text-sm text-gray-500">
                                                                <span>{{ $responsibility['resource'] }}</span>

                                                                @if ($responsibility['stage'])
                                                                    <span>Stage: {{ $responsibility['stage'] }}</span>
                                                                @endif

                                                                @if ($responsibility['dueDate'])
                                                                    <span>Due: {{ $responsibility['dueDate'] }}</span>
                                                                @endif
                                                            </div>
                                                        </div>

                                                        @if ($responsibility['status'])
                                                            <span class="inline-flex w-fit items-center rounded-full border border-gray-200 bg-gray-50 px-2.5 py-1 text-xs font-medium text-gray-700">
                                                                {{ $responsibility['status'] }}
                                                            </span>
                                                        @endif
                                                    </div>
                                                </li>
                                            @endforeach
                                        </ul>
                                    </div>
                                @endif
                            </div>

                            <div>
                                <h4 class="text-sm font-semibold text-gray-900">Stage Tasks</h4>

                                @if (count($stageTasks) === 0)
                                    <p class="mt-3 text-sm text-gray-500">No assigned stage tasks.</p>
                                @else
                                    <div class="mt-3 overflow-hidden rounded-lg border border-gray-200">
                                        <ul class="divide-y divide-gray-200">
                                            @foreach ($stageTasks as $task)
                                                <li class="px-4 py-3">
                                                    <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                                                        <div class="min-w-0">
                                                            <a
                                                                href="{{ $task['url'] }}"
                                                                class="font-medium text-gray-900 underline-offset-2 hover:text-gray-700 hover:underline"
                                                            >
                                                                {{ $task['title'] }}
                                                            </a>
                                                            <div class="mt-1 flex flex-wrap gap-x-4 gap-y-1 text-sm text-gray-500">
                                                                <span>{{ $task['resource'] }}</span>

                                                                @if ($task['stage'])
                                                                    <span>Stage: {{ $task['stage'] }}</span>
                                                                @endif

                                                                @if ($task['dueDate'])
                                                                    <span>Due: {{ $task['dueDate'] }}</span>
                                                                @endif
                                                            </div>
                                                        </div>

                                                        <div class="flex flex-wrap items-center gap-2">
                                                            @if ($task['status'])
                                                                <span class="inline-flex w-fit items-center rounded-full border border-gray-200 bg-gray-50 px-2.5 py-1 text-xs font-medium text-gray-700">
                                                                    {{ $task['status'] }}
                                                                </span>
                                                            @endif

                                                            @if (($task['canComplete'] ?? false) && ! empty($task['completeUrl']))
                                                                <form method="POST" action="{{ $task['completeUrl'] }}">
                                                                    @csrf
                                                                    @method('PATCH')
                                                                    <button
                                                                        type="submit"
                                                                        class="inline-flex h-8 items-center justify-center rounded-lg border border-gray-300 px-3 text-xs font-semibold uppercase tracking-widest text-gray-700 transition hover:bg-gray-50"
                                                                    >
                                                                        Complete
                                                                    </button>
                                                                </form>
                                                            @endif
                                                        </div>
                                                    </div>
                                                </li>
                                            @endforeach
                                        </ul>
                                    </div>
                                @endif
                            </div>
                        </div>
                    @endif
                </x-detail-section-card>
            </div>
        </div>
    </div>
</x-app-layout>
