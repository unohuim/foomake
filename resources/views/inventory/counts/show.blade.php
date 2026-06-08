<x-resource-detail-layout>
    @php
        $isDraftSetup = $inventoryCount->workflow_stage_id === null && $inventoryCount->posted_at === null;
        $breadcrumbItems = [
            [
                'label' => 'Inventory Counts',
                'url' => route('inventory.counts.index'),
                'current' => false,
            ],
            [
                'label' => $inventoryCount->name,
                'url' => null,
                'current' => true,
            ],
        ];
    @endphp

    <x-slot name="header">
        <x-resource-detail-header-breadcrumb
            :items="$breadcrumbItems"
            :title="$inventoryCount->name"
            title-class="font-semibold text-xl text-gray-800 leading-tight"
        >
            <x-slot name="metadata">
                <div class="space-y-2">
                    <p class="text-sm font-medium text-gray-500">
                        {{ $inventoryCount->counted_at->format('F j, Y') }}
                    </p>

                    <span
                        class="inline-flex items-center rounded-md bg-slate-100 px-2.5 py-1 text-xs font-semibold text-slate-700"
                        data-workflow-status-badge="workflow_status_badge"
                    >
                        {{ $payload['count']['workflow_status_label'] ?? __('Draft') }}
                    </span>
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
        class="pt-0 pb-12 sm:pt-6"
        data-page="inventory-count-show"
        data-payload="inventory-count-show-payload"
        x-data="inventoryCountShow"
        @inventory-count-previous.window="moveToPreviousWorkflowStage()"
        @inventory-count-submit.window="submitToWorkflow()"
        @inventory-count-advance.window="advanceWorkflow()"
    >
        <x-ui.toast visible="toast.show" type="toast.type" message="toast.message" />

        <div class="max-w-7xl mx-auto space-y-6 sm:px-6 lg:px-8">
            <x-ui.workflow-progress
                :steps="$payload['workflowProgressSteps'] ?? []"
                data-workflow-progress-panel
            />

            @if ($payload['count']['show_details_section'] ?? false)
                <x-detail-section-card
                    title="Details"
                    :description="($payload['count']['can_edit_details'] ?? false)
                        ? __('Update count metadata separately from material lines and workflow tasks.')
                        : __('Review count metadata separately from material lines and workflow tasks.')"
                    :default-open="false"
                >
                    <div class="space-y-3">
                        @if (($payload['count']['can_view_counted_at'] ?? false) || ($payload['count']['can_view_assignment'] ?? false))
                            <div class="grid grid-cols-2 gap-2">
                                @if ($payload['count']['can_view_counted_at'] ?? false)
                                    <div class="space-y-1">
                                        <p class="text-[10px] font-semibold uppercase tracking-wide text-gray-500 sm:text-xs">{{ __('Count Date') }}</p>
                                        <input
                                            type="date"
                                            class="block w-full rounded-xl border border-gray-300 bg-white px-3 py-2 text-sm text-gray-900 shadow-sm transition focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-500/20 disabled:cursor-not-allowed disabled:bg-gray-100"
                                            x-model="details.counted_at_iso"
                                            x-bind:disabled="!count.can_edit_counted_at || detailsCountedAtSaving"
                                            x-on:change="saveDetails('counted_at')"
                                        />
                                    </div>
                                @endif

                                @if ($payload['count']['can_view_assignment'] ?? false)
                                    <div class="space-y-1">
                                        <p class="text-[10px] font-semibold uppercase tracking-wide text-gray-500 sm:text-xs">{{ __('Assigned To') }}</p>
                                        <x-dropdown-select
                                            name="inventory_count_assigned_to_user_id"
                                            options-expression="details.assignee_options"
                                            placeholder="Unassigned"
                                            disabled-expression="!count.can_edit_assignment || detailsAssignmentSaving"
                                            button-class="flex w-full items-center justify-between rounded-xl border border-gray-300 bg-white px-3 py-2 text-left text-sm text-gray-900 shadow-sm transition focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-500/20 disabled:cursor-not-allowed disabled:bg-gray-100"
                                            x-model="details.assigned_to_user_id"
                                            class="w-full"
                                        />
                                    </div>
                                @endif
                            </div>
                        @endif
                    </div>
                </x-detail-section-card>
            @endif

            <div
                data-js-crud-section-root
                data-section-key="countLines"
            ></div>

            <div
                class="!-mt-px sm:!mt-6"
                data-inventory-count-tasks-section
                x-data="taskCreateSection"
                x-on:task-created.window="appendCreatedTask($event)"
            >
                @php
                    $currentStageTasks = $payload['count']['current_stage_tasks'] ?? [];
                @endphp

                <x-detail-section-card
                    title="Tasks"
                    :description="__('Complete required workflow tasks before moving the inventory count forward.')"
                    :default-open="false"
                >
                    <x-slot name="actions">
                        <button
                            type="button"
                            class="inline-flex h-8 w-8 shrink-0 items-center justify-center rounded-lg border border-gray-300 text-gray-600 transition hover:bg-gray-50 hover:text-gray-900"
                            x-on:click="showTaskCreate = true"
                            aria-label="{{ __('Create task') }}"
                        >
                            <svg class="h-4 w-4" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" aria-hidden="true">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15" />
                            </svg>
                        </button>
                    </x-slot>

                    @if (count($currentStageTasks) > 0)
                        <div class="space-y-0 sm:space-y-3">
                            @foreach ($currentStageTasks as $task)
                                <article
                                    class="-mx-3 rounded-none border-y border-gray-200 bg-gray-50 px-3 py-2 sm:mx-0 sm:rounded-xl sm:border sm:border-gray-100 sm:p-4"
                                    data-inventory-count-task-row
                                >
                                    <div class="flex items-center gap-3">
                                        <div class="min-w-0 flex-1 space-y-1.5">
                                            <div class="flex min-w-0 items-center gap-3">
                                                <p class="truncate text-sm font-semibold text-gray-900">{{ $task['title'] ?? __('Task') }}</p>
                                                <span
                                                    class="inline-flex shrink-0 items-center rounded-full px-2.5 py-1 text-xs font-medium {{ ($task['is_completed'] ?? false) ? 'bg-emerald-100 text-emerald-700' : 'bg-gray-200 text-gray-700' }}"
                                                    data-inventory-count-task-status
                                                >
                                                    {{ $task['status'] ?? __('open') }}
                                                </span>
                                            </div>

                                            <div class="flex min-w-0 items-center gap-3 text-xs text-gray-600 sm:text-sm">
                                                @if (($task['assigned_to_display'] ?? '') !== '')
                                                    <p class="truncate text-gray-700" data-inventory-count-task-assigned-to>
                                                        {{ $task['assigned_to_display'] }}
                                                    </p>
                                                @endif
                                                @if (($task['due_date'] ?? '') !== '')
                                                    <p class="shrink-0 text-gray-600">
                                                        {{ $task['due_date'] }}
                                                    </p>
                                                @endif
                                            </div>
                                        </div>

                                        @if (($task['available_actions'] ?? []) === ['complete'] && ! empty($task['complete_url']))
                                            <form
                                                class="shrink-0 self-center"
                                                method="POST"
                                                action="{{ $task['complete_url'] }}"
                                                x-on:submit.prevent="completeInventoryCountTask($event)"
                                                data-inventory-count-task-complete-form
                                            >
                                                @csrf
                                                @method('PATCH')
                                                <button
                                                    type="submit"
                                                    class="inline-flex items-center rounded-md border border-slate-300 px-2 py-0.5 text-[0.65rem] font-semibold uppercase tracking-wide text-slate-700 transition hover:bg-slate-50 sm:px-3 sm:py-1.5 sm:text-xs sm:tracking-widest"
                                                    data-inventory-count-task-complete-button
                                                >
                                                    {{ __('Complete') }}
                                                </button>
                                            </form>
                                        @endif
                                    </div>
                                </article>
                            @endforeach
                        </div>
                    @else
                        <div
                            class="rounded-xl border border-dashed border-gray-300 px-4 py-8 text-center text-sm text-gray-500"
                            x-show="createdTasks.length === 0"
                        >
                            {{ __('No tasks for the current stage.') }}
                        </div>
                    @endif

                    <template x-if="createdTasks.length > 0">
                        <div class="mt-3 space-y-0 sm:space-y-3">
                            <template x-for="task in createdTasks" :key="task.id">
                                <article class="-mx-3 rounded-none border-y border-gray-200 bg-gray-50 px-3 py-2 sm:mx-0 sm:rounded-xl sm:border sm:border-gray-100 sm:p-4">
                                    <div class="flex items-center gap-3">
                                        <div class="min-w-0 flex-1 space-y-1.5">
                                            <div class="flex min-w-0 items-center gap-3">
                                                <p class="truncate text-sm font-semibold text-gray-900" x-text="task.title || 'Task'"></p>
                                                <span
                                                    class="inline-flex shrink-0 items-center rounded-full px-2.5 py-1 text-xs font-medium"
                                                    x-bind:class="taskStatusClasses(task)"
                                                    x-text="task.status || 'open'"
                                                ></span>
                                            </div>

                                            <div class="flex min-w-0 items-center gap-3 text-xs text-gray-600 sm:text-sm">
                                                <p class="truncate text-gray-700" x-show="task.assigned_to_user_name" x-text="task.assigned_to_user_name"></p>
                                                <p class="shrink-0 text-gray-600" x-show="task.due_date" x-text="task.due_date"></p>
                                            </div>
                                        </div>

                                        <div
                                            class="shrink-0 self-center"
                                            x-show="task.can_complete && !task.is_completed"
                                        >
                                            <button
                                                type="button"
                                                class="inline-flex items-center rounded-md border border-slate-300 px-2 py-0.5 text-[0.65rem] font-semibold uppercase tracking-wide text-slate-700 transition hover:bg-slate-50 disabled:cursor-not-allowed disabled:opacity-50 sm:px-3 sm:py-1.5 sm:text-xs sm:tracking-widest"
                                                x-bind:disabled="isTaskCompleting(task)"
                                                x-on:click="completeCreatedTask(task)"
                                            >
                                                {{ __('Complete') }}
                                            </button>
                                        </div>
                                    </div>
                                </article>
                            </template>
                        </div>
                    </template>
                </x-detail-section-card>

                @include('tasks.partials.create-task-slide-over', [
                    'users' => collect(data_get($payload, 'taskCreate.users', [])),
                    'workflowDomainId' => data_get($payload, 'taskCreate.workflowDomainId'),
                    'domainRecordId' => $inventoryCount->id,
                    'workflowStageId' => $inventoryCount->workflow_stage_id,
                ])
            </div>

            <x-notes-feed :config="$notesFeed" />
        </div>
    </div>
</x-resource-detail-layout>
