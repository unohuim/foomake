<x-resource-detail-layout>
    @php
        $isDraftSetup = $inventoryCount->workflow_stage_id === null && $inventoryCount->posted_at === null;
        $breadcrumbItems = [
            [
                'label' => 'Home',
                'url' => url('/'),
            ],
            [
                'label' => 'Inventory Counts',
                'url' => route('inventory.counts.index'),
            ],
            [
                'label' => 'ID# ' . $inventoryCount->id,
                'url' => null,
            ],
        ];
    @endphp

    <x-slot name="header">
        <x-resource-detail-header-breadcrumb
            :items="$breadcrumbItems"
            :title="__('Inventory Count')"
            title-class="font-semibold text-xl text-gray-800 leading-tight"
        >
            <x-slot name="metadata">
                <div class="space-y-2">
                    <div class="flex items-center gap-3">
                        <span class="rounded-full bg-gray-100 px-3 py-1 text-xs font-semibold text-gray-600">
                            #{{ $inventoryCount->id }}
                        </span>
                    </div>

                    <div class="flex flex-wrap items-center gap-2 text-sm text-gray-500">
                        <span class="rounded-full bg-white px-3 py-1 ring-1 ring-gray-200">
                            {{ __('Counted') }}: {{ $inventoryCount->counted_at->format('Y-m-d H:i') }}
                        </span>
                        <span class="rounded-full bg-white px-3 py-1 ring-1 ring-gray-200">
                            {{ __('Line Items') }}: {{ $inventoryCount->lines_count }}
                        </span>
                        <span class="rounded-full bg-white px-3 py-1 ring-1 ring-gray-200">
                            {{ __('Workflow Stage') }}: {{ $inventoryCount->workflowStage?->name ?? __('Draft') }}
                        </span>
                    </div>

                    <p class="text-sm text-gray-600">
                        {{ $inventoryCount->notes ?: __('No notes') }}
                    </p>
                </div>
            </x-slot>

            @can('inventory-adjustments-execute')
                @if ($previousWorkflowActionLabel || $nextWorkflowActionLabel)
                    <x-slot name="actions">
                        <div x-data="{}" class="flex items-center justify-end gap-3">
                            @if ($previousWorkflowActionLabel && $previousWorkflowActionEvent)
                                <button
                                    type="button"
                                    class="inline-flex items-center justify-center rounded-md border border-slate-300 px-4 py-2 text-xs font-semibold uppercase tracking-widest text-slate-700 transition hover:bg-slate-50 focus:outline-none focus:ring-2 focus:ring-slate-500 focus:ring-offset-2"
                                    x-on:click.prevent="window.dispatchEvent(new CustomEvent('{{ $previousWorkflowActionEvent }}'))"
                                >
                                    {{ $previousWorkflowActionLabel }}
                                </button>
                            @endif

                            @if ($nextWorkflowActionLabel && $nextWorkflowActionEvent)
                                <button
                                    type="button"
                                    class="inline-flex items-center justify-center rounded-md border border-transparent bg-slate-900 px-4 py-2 text-xs font-semibold uppercase tracking-widest text-white transition hover:bg-slate-800 focus:outline-none focus:ring-2 focus:ring-slate-500 focus:ring-offset-2"
                                    x-on:click.prevent="window.dispatchEvent(new CustomEvent('{{ $nextWorkflowActionEvent }}'))"
                                >
                                    {{ $nextWorkflowActionLabel }}
                                </button>
                            @endif
                        </div>
                    </x-slot>
                @endif
            @endcan
        </x-resource-detail-header-breadcrumb>
    </x-slot>

    <script type="application/json" id="inventory-count-show-payload">@json($payload)</script>

    <div
        class="py-12"
        data-page="inventory-count-show"
        data-payload="inventory-count-show-payload"
        x-data="inventoryCountShow"
        @inventory-count-previous.window="moveToPreviousWorkflowStage()"
        @inventory-count-submit.window="submitToWorkflow()"
        @inventory-count-advance.window="advanceWorkflow()"
    >
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-6">
            <div x-cloak x-show="toast.show" class="fixed top-5 right-5 z-50">
                <div
                    class="px-4 py-2 rounded-md text-sm text-white"
                    :class="toast.type === 'success' ? 'bg-green-600' : 'bg-red-600'"
                >
                    <span x-text="toast.message"></span>
                </div>
            </div>

            <div
                data-js-crud-section-root
                data-section-key="countLines"
            ></div>

            <div
                data-js-crud-section-root
                data-section-key="tasks"
            >
                @php
                    $currentStageTasks = $payload['count']['current_stage_tasks'] ?? [];
                @endphp

                <section class="overflow-visible rounded-2xl border border-gray-200 bg-white shadow-sm" data-js-crud-section-card>
                    <div class="flex items-start justify-between gap-3 px-3 py-4 sm:px-6 sm:py-5">
                        <div class="min-w-0 flex-1">
                            <h3 class="text-lg font-semibold text-gray-900">{{ __('Tasks') }}</h3>
                            <p class="mt-1 text-sm text-gray-500">
                                {{ __('Complete required workflow tasks before moving the inventory count forward.') }}
                            </p>
                        </div>
                    </div>

                    <div class="border-t border-gray-100 px-3 py-4 sm:px-6 sm:py-5">
                        @if (count($currentStageTasks) > 0)
                            <div class="space-y-3">
                                @foreach ($currentStageTasks as $task)
                                    <article class="rounded-xl border border-gray-100 bg-gray-50 p-3 sm:p-4">
                                        <div class="flex flex-col sm:flex-row gap-4 sm:items-center sm:justify-between">
                                            <div class="min-w-0 flex-1">
                                                <div class="flex items-center gap-3">
                                                    <p class="truncate text-sm font-semibold text-gray-900">{{ $task['title'] ?? __('Task') }}</p>
                                                    <span class="inline-flex items-center rounded-full px-2.5 py-1 text-xs font-medium {{ ($task['is_completed'] ?? false) ? 'bg-emerald-100 text-emerald-700' : 'bg-gray-200 text-gray-700' }}">
                                                        {{ $task['status'] ?? __('open') }}
                                                    </span>
                                                </div>
                                                <div class="mt-1 flex flex-wrap items-center gap-4">
                                                    <p class="text-sm text-gray-600">
                                                        <span class="text-gray-500">{{ __('Assigned By: ') }}</span>
                                                        <span class="text-gray-700">{{ $task['assigned_by_user_name'] ?? '—' }}</span>
                                                    </p>
                                                    @if (($task['assigned_to_display'] ?? '') !== '')
                                                        <p class="text-sm text-gray-600">
                                                            <span class="text-gray-500">{{ __('Assigned To: ') }}</span>
                                                            <span class="text-gray-700">{{ $task['assigned_to_display'] }}</span>
                                                        </p>
                                                    @endif
                                                    @if (($task['completed_by_display'] ?? '') !== '')
                                                        <p class="text-sm text-gray-600">
                                                            <span class="text-gray-500">{{ __('Completed By: ') }}</span>
                                                            <span class="text-gray-700">{{ $task['completed_by_display'] }}</span>
                                                        </p>
                                                    @endif
                                                </div>
                                            </div>

                                            @if (($task['available_actions'] ?? []) === ['complete'] && ! empty($task['complete_url']))
                                                <div class="flex items-center justify-end gap-3 self-center">
                                                    <form method="POST" action="{{ $task['complete_url'] }}">
                                                        @csrf
                                                        @method('PATCH')
                                                        <button
                                                            type="submit"
                                                            class="inline-flex items-center rounded-lg border border-slate-300 px-3 py-1.5 text-xs font-semibold uppercase tracking-widest text-slate-700 transition hover:bg-slate-50"
                                                        >
                                                            {{ __('Complete') }}
                                                        </button>
                                                    </form>
                                                </div>
                                            @endif
                                        </div>
                                    </article>
                                @endforeach
                            </div>
                        @else
                            <div class="rounded-xl border border-dashed border-gray-300 px-4 py-8 text-center text-sm text-gray-500">
                                {{ __('No tasks for the current stage.') }}
                            </div>
                        @endif
                    </div>
                </section>
            </div>
        </div>
    </div>
</x-resource-detail-layout>
