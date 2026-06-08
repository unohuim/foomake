<x-app-layout>
    @php
        $todo = $todo ?? ['responsibilities' => [], 'stageTasks' => []];
        $responsibilities = $todo['responsibilities'] ?? [];
        $stageTasks = $todo['stageTasks'] ?? [];
        $hasTodo = count($responsibilities) > 0 || count($stageTasks) > 0;
    @endphp

    <x-slot name="header">
        <h2 class="pt-6 font-semibold text-xl text-gray-800 leading-tight">
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
                                <h4 class="-mx-3 bg-blue-100 px-3 py-2 text-sm font-semibold text-gray-900 sm:mx-0 sm:rounded-md">Workflow Responsibilities</h4>

                                @if (count($responsibilities) === 0)
                                    <p class="mt-3 text-sm text-gray-500">No assigned responsibilities.</p>
                                @else
                                    <div class="-mx-3 mt-0 overflow-hidden border-y border-gray-200 sm:mx-0 sm:mt-3 sm:rounded-lg sm:border">
                                        <ul class="divide-y divide-gray-200">
                                            @foreach ($responsibilities as $responsibility)
                                                <li>
                                                    <a
                                                        href="{{ $responsibility['url'] }}"
                                                        class="block px-4 py-3 hover:bg-gray-50 sm:hidden"
                                                        data-dashboard-todo-mobile-row
                                                    >
                                                        <div class="space-y-1">
                                                            <div class="flex items-start justify-between gap-4">
                                                                <span class="min-w-0 font-medium text-gray-900">
                                                                    {{ $responsibility['title'] }}
                                                                </span>

                                                                @if ($responsibility['dueDate'])
                                                                    <span class="shrink-0 text-sm text-gray-500">
                                                                        {{ $responsibility['dueDate'] }}
                                                                    </span>
                                                                @endif
                                                            </div>

                                                            <div class="flex items-center justify-between gap-4">
                                                                <div class="min-w-0 flex flex-wrap items-center gap-2">
                                                                    @if ($responsibility['domainName'] ?? null)
                                                                        <span class="text-xs font-medium text-gray-600">
                                                                            {{ $responsibility['domainName'] }}
                                                                        </span>
                                                                    @endif
                                                                </div>

                                                                @if ($responsibility['stage'])
                                                                    <span class="inline-flex w-fit shrink-0 items-center rounded-full border border-gray-200 bg-gray-50 px-2.5 py-1 text-xs font-medium text-gray-700">
                                                                        {{ $responsibility['stage'] }}
                                                                    </span>
                                                                @endif
                                                            </div>
                                                        </div>
                                                    </a>

                                                    <div class="hidden px-4 py-3 sm:block">
                                                        <div class="space-y-1">
                                                            <div class="flex items-start justify-between gap-4">
                                                                <a
                                                                    href="{{ $responsibility['url'] }}"
                                                                    class="min-w-0 font-medium text-gray-900 underline-offset-2 hover:text-gray-700 hover:underline"
                                                                >
                                                                    {{ $responsibility['title'] }}
                                                                </a>

                                                                @if ($responsibility['dueDate'])
                                                                    <span class="shrink-0 text-sm text-gray-500">
                                                                        {{ $responsibility['dueDate'] }}
                                                                    </span>
                                                                @endif
                                                            </div>

                                                            <div class="flex items-center justify-between gap-4">
                                                                <div class="min-w-0 flex flex-wrap items-center gap-2">
                                                                    @if ($responsibility['domainName'] ?? null)
                                                                        <span class="text-xs font-medium text-gray-600">
                                                                            {{ $responsibility['domainName'] }}
                                                                        </span>
                                                                    @endif
                                                                </div>

                                                                @if ($responsibility['stage'])
                                                                    <span class="inline-flex w-fit shrink-0 items-center rounded-full border border-gray-200 bg-gray-50 px-2.5 py-1 text-xs font-medium text-gray-700">
                                                                        {{ $responsibility['stage'] }}
                                                                    </span>
                                                                @endif
                                                            </div>
                                                        </div>
                                                    </div>
                                                </li>
                                            @endforeach
                                        </ul>
                                    </div>
                                @endif
                            </div>

                            <div>
                                <h4 class="-mx-3 bg-blue-100 px-3 py-2 text-sm font-semibold text-gray-900 sm:mx-0 sm:rounded-md">Stage Tasks</h4>

                                @if (count($stageTasks) === 0)
                                    <p class="mt-3 text-sm text-gray-500">No assigned stage tasks.</p>
                                @else
                                    <div class="-mx-3 mt-0 overflow-hidden border-y border-gray-200 sm:mx-0 sm:mt-3 sm:rounded-lg sm:border">
                                        <ul class="divide-y divide-gray-200">
                                            @foreach ($stageTasks as $task)
                                                <li>
                                                    <a
                                                        href="{{ $task['url'] }}"
                                                        class="block px-4 py-3 hover:bg-gray-50 sm:hidden"
                                                        data-dashboard-todo-mobile-row
                                                    >
                                                        <div class="space-y-1">
                                                            <div class="flex items-start justify-between gap-4">
                                                                <span class="min-w-0 font-medium text-gray-900">
                                                                    {{ $task['title'] }}
                                                                </span>

                                                                @if ($task['dueDate'])
                                                                    <span class="shrink-0 text-sm text-gray-500">
                                                                        {{ $task['dueDate'] }}
                                                                    </span>
                                                                @endif
                                                            </div>

                                                            <div class="flex items-center justify-between gap-4">
                                                                <div class="min-w-0 flex flex-wrap items-center gap-2">
                                                                    @if ($task['domainName'] ?? null)
                                                                        <span class="text-xs font-medium text-gray-600">
                                                                            {{ $task['domainName'] }}
                                                                        </span>
                                                                    @endif
                                                                </div>

                                                                @if ($task['status'])
                                                                    <span class="inline-flex w-fit shrink-0 items-center rounded-full border border-gray-200 bg-gray-50 px-2.5 py-1 text-xs font-medium text-gray-700">
                                                                        {{ $task['status'] }}
                                                                    </span>
                                                                @endif
                                                            </div>
                                                        </div>
                                                    </a>

                                                    <div class="hidden px-4 py-3 sm:block">
                                                        <div class="space-y-1">
                                                            <div class="flex items-start justify-between gap-4">
                                                                <a
                                                                    href="{{ $task['url'] }}"
                                                                    class="min-w-0 font-medium text-gray-900 underline-offset-2 hover:text-gray-700 hover:underline"
                                                                >
                                                                    {{ $task['title'] }}
                                                                </a>

                                                                @if ($task['dueDate'])
                                                                    <span class="shrink-0 text-sm text-gray-500">
                                                                        {{ $task['dueDate'] }}
                                                                    </span>
                                                                @endif
                                                            </div>

                                                            <div class="flex items-center justify-between gap-4">
                                                                <div class="min-w-0 flex flex-wrap items-center gap-2">
                                                                    @if ($task['domainName'] ?? null)
                                                                        <span class="text-xs font-medium text-gray-600">
                                                                            {{ $task['domainName'] }}
                                                                        </span>
                                                                    @endif
                                                                </div>

                                                                @if ($task['status'])
                                                                    <span class="inline-flex w-fit shrink-0 items-center rounded-full border border-gray-200 bg-gray-50 px-2.5 py-1 text-xs font-medium text-gray-700">
                                                                        {{ $task['status'] }}
                                                                    </span>
                                                                @endif
                                                            </div>
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
