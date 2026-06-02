<x-resource-detail-layout
    class="relative"
    data-page="manufacturing-make-orders-show"
    data-payload="manufacturing-make-orders-show-payload"
    x-data="manufacturingMakeOrdersShow"
    x-on:make-order-next-stage.window="moveWorkflowStageTo($event.detail.workflowStageId)"
>
    @php
        $makeOrderPayload = $payload['makeOrder'] ?? [];
    @endphp

    <x-slot name="header">
        <div
            x-data="{
                workflowState: @js($makeOrderPayload['workflow_state'] ?? 'DRAFT'),
                nextStageAction: @js($payload['workflow']['next_stage_action'] ?? null),
                syncHeader(detail) {
                    if (!detail || typeof detail !== 'object') {
                        return;
                    }

                    if (Object.prototype.hasOwnProperty.call(detail, 'workflowState')) {
                        this.workflowState = detail.workflowState || 'DRAFT';
                    }

                    if (Object.prototype.hasOwnProperty.call(detail, 'nextStageAction')) {
                        this.nextStageAction = detail.nextStageAction || null;
                    }
                },
            }"
            x-on:make-order-header-sync.window="syncHeader($event.detail)"
        >
            <x-resource-detail-header-breadcrumb
                :items="$payload['breadcrumbs'] ?? []"
                :title="$makeOrderPayload['title'] ?? ('Make Order ' . $makeOrder->id)"
            >
                <x-slot name="titleSuffix">
                    <span
                        class="inline-flex items-center rounded-full bg-gray-100 px-2.5 py-1 text-xs font-medium text-gray-700"
                        x-text="workflowState || 'DRAFT'"
                    >
                        {{ data_get($makeOrderPayload, 'workflow_state', 'DRAFT') }}
                    </span>
                </x-slot>

                <x-slot name="metadata">
                    <div
                        class="flex flex-wrap items-center gap-x-3 gap-y-2 text-sm text-gray-500"
                        data-resource-detail-header-metadata-group="primary"
                    >
                        <span class="rounded-full bg-gray-100 px-2.5 py-1 text-xs font-medium text-gray-700">
                            {{ $makeOrderPayload['recipe_name'] ?? '—' }}
                        </span>
                        <span
                            class="rounded-full bg-gray-100 px-2.5 py-1 text-xs font-medium text-gray-700"
                            x-text="'{{ __('Runs') }} ' + (makeOrder.runs_text || '—')"
                        >
                            {{ __('Runs') }} {{ $makeOrderPayload['runs_text'] ?? '—' }}
                        </span>
                    </div>

                    <div
                        class="flex flex-wrap items-center gap-x-3 gap-y-2 text-sm text-gray-500"
                        data-resource-detail-header-metadata-group="secondary"
                    >
                        <span class="rounded-full bg-gray-100 px-2.5 py-1 text-xs font-medium text-gray-700">
                            {{ $makeOrderPayload['output_item_name'] ?? '—' }}
                        </span>
                        <span
                            class="rounded-full bg-gray-100 px-2.5 py-1 text-xs font-medium text-gray-700"
                            x-text="'{{ __('Expected Output') }} ' + (makeOrder.expected_output_qty_text || '—')"
                        >
                            {{ __('Expected Output') }} {{ $makeOrderPayload['expected_output_qty_text'] ?? '—' }}
                        </span>
                    </div>
                </x-slot>

                <x-slot name="actions">
                    <div
                        class="flex items-center justify-end"
                        x-show="nextStageAction && nextStageAction.label"
                    >
                        <button
                            type="button"
                            class="inline-flex items-center justify-center rounded-md border border-transparent bg-slate-900 px-4 py-2 text-xs font-semibold uppercase tracking-widest text-white transition hover:bg-slate-800 focus:outline-none focus:ring-2 focus:ring-slate-500 focus:ring-offset-2"
                            x-on:click.prevent="window.dispatchEvent(new CustomEvent('make-order-next-stage', { detail: { workflowStageId: nextStageAction.id } }))"
                            x-text="nextStageAction?.label || ''"
                        >
                            {{ $payload['workflow']['next_stage_action']['label'] ?? '' }}
                        </button>
                    </div>
                </x-slot>
            </x-resource-detail-header-breadcrumb>
        </div>
    </x-slot>

    <script type="application/json" id="manufacturing-make-orders-show-payload">@json($payload)</script>

    <div class="fixed right-6 top-6 z-50" x-cloak x-show="toast.visible">
        <div
            class="rounded-md px-4 py-3 text-sm shadow-md"
            :class="toast.type === 'success' ? 'bg-green-50 text-green-700' : 'bg-red-50 text-red-700'"
            x-text="toast.message"
        ></div>
    </div>

    <div class="mx-auto max-w-5xl space-y-4 px-1 py-8 sm:space-y-6 sm:px-6 sm:py-12 lg:px-8">
        <x-detail-section-card
            title="Details"
            :description="__('Runs, expected output, actual output, due date, and assignment live here. Workflow movement stays in the header action.')"
            :default-open="$payload['workflow']['default_open'] ?? false"
        >
            <div class="space-y-3" data-make-order-workflow-metadata-row>
                <div class="grid grid-cols-3 gap-2">
                    <div class="space-y-1">
                        <p class="text-[10px] font-semibold uppercase tracking-wide text-gray-500 sm:text-xs">{{ __('Runs') }}</p>
                        <input
                            type="text"
                            value="{{ $makeOrderPayload['runs_text'] ?? '' }}"
                            class="block w-full rounded-xl border border-gray-300 bg-white px-2.5 py-2 text-xs text-gray-900 shadow-sm transition focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-500/20 sm:px-3 sm:text-sm"
                            x-model="makeOrder.runs_text"
                            x-on:input="recalculateExpectedOutputQtyFromRuns()"
                            x-on:change="saveMakeOrderDetailQuantity('runs')"
                        />
                    </div>

                    <div class="space-y-1">
                        <p class="text-[10px] font-semibold uppercase tracking-wide text-gray-500 sm:text-xs">{{ __('Expected Output') }}</p>
                        <input
                            type="text"
                            value="{{ $makeOrderPayload['expected_output_qty_text'] ?? '' }}"
                            readonly
                            aria-readonly="true"
                            class="block w-full cursor-not-allowed rounded-xl border border-gray-300 bg-gray-50 px-2.5 py-2 text-xs text-gray-600 shadow-sm transition focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-500/20 sm:px-3 sm:text-sm"
                            x-model="makeOrder.expected_output_qty_text"
                        />
                    </div>

                    <div class="space-y-1">
                        <p class="text-[10px] font-semibold uppercase tracking-wide text-gray-500 sm:text-xs">{{ __('Actual Output') }}</p>
                        <input
                            type="text"
                            value="{{ $makeOrderPayload['actual_output_qty_text'] ?? '' }}"
                            class="block w-full rounded-xl border border-gray-300 bg-white px-2.5 py-2 text-xs text-gray-900 shadow-sm transition focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-500/20 sm:px-3 sm:text-sm"
                            x-model="makeOrder.actual_output_qty_text"
                            x-on:change="saveMakeOrderDetailQuantity('actual_output_qty')"
                        />
                    </div>
                </div>

                <div class="grid grid-cols-2 gap-2">
                    <div class="space-y-1">
                        <p class="text-[10px] font-semibold uppercase tracking-wide text-gray-500 sm:text-xs">{{ __('Due Date') }}</p>
                        <input
                            type="date"
                            class="block w-full rounded-xl border border-gray-300 bg-white px-3 py-2 text-sm text-gray-900 shadow-sm transition focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-500/20 disabled:cursor-not-allowed disabled:bg-gray-100"
                            x-model="workflow.due_date"
                            x-bind:disabled="!workflow.can_edit_due_date || workflowDueDateSaving"
                            x-on:change="saveWorkflowDueDate()"
                        />
                    </div>

                    <div class="space-y-1">
                        <p class="text-[10px] font-semibold uppercase tracking-wide text-gray-500 sm:text-xs">{{ __('Assigned To') }}</p>
                        <x-dropdown-select
                            name="workflow_made_by_user_id"
                            options-expression="workflow.assignee_options"
                            placeholder="Unassigned"
                            disabled-expression="!workflow.can_edit_assignment || workflowAssignmentSaving"
                            button-class="flex w-full items-center justify-between rounded-xl border border-gray-300 bg-white px-3 py-2 text-left text-sm text-gray-900 shadow-sm transition focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-500/20 disabled:cursor-not-allowed disabled:bg-gray-100"
                            x-model="workflow.made_by_user_id"
                            class="w-full"
                        />
                    </div>
                </div>
            </div>
        </x-detail-section-card>

        <x-detail-section-card
            title="Tasks"
            :description="__('Complete current stage tasks separately from workflow metadata editing.')"
            :default-open="false"
        >
            <div class="rounded-xl border border-gray-200 bg-white">
                <div class="border-b border-gray-100 px-4 py-3">
                    <h4 class="text-sm font-semibold text-gray-900">{{ __('Current Stage Tasks') }}</h4>
                </div>
                <div class="divide-y divide-gray-100">
                    <template x-if="workflow.current_stage_tasks.length === 0">
                        <div class="px-4 py-4 text-sm text-gray-500">{{ __('No tasks for the current workflow stage.') }}</div>
                    </template>

                    <template x-for="task in workflow.current_stage_tasks" :key="task.id">
                        <div class="flex flex-col gap-3 px-4 py-4 sm:flex-row sm:items-center sm:justify-between">
                            <div class="min-w-0 flex-1">
                                <p class="text-sm font-medium text-gray-900" x-text="task.title"></p>
                                <p class="mt-1 text-sm text-gray-500" x-show="task.description" x-text="task.description"></p>
                                <p class="mt-1 text-xs text-gray-500" x-show="task.assigned_to_user_name">
                                    <span>{{ __('Assigned To') }}:</span>
                                    <span x-text="task.assigned_to_user_name"></span>
                                </p>
                            </div>

                            <div class="flex items-center gap-2">
                                <span
                                    class="inline-flex items-center rounded-full px-2.5 py-1 text-xs font-medium"
                                    x-bind:class="task.is_completed ? 'bg-emerald-100 text-emerald-700' : 'bg-amber-100 text-amber-700'"
                                    x-text="task.is_completed ? 'Completed' : 'Open'"
                                ></span>

                                <button
                                    type="button"
                                    class="inline-flex h-9 items-center justify-center rounded-lg border border-gray-300 px-3 text-sm font-medium text-gray-700 transition hover:bg-gray-50 disabled:cursor-not-allowed disabled:opacity-50"
                                    x-show="task.can_complete"
                                    x-on:click="completeWorkflowTask(task)"
                                    x-bind:disabled="workflowTaskSavingIds.includes(task.id)"
                                >
                                    {{ __('Complete') }}
                                </button>
                            </div>
                        </div>
                    </template>
                </div>
            </div>
        </x-detail-section-card>

        <x-ingredients-detail-section
            title="Ingredients"
            :description="__('Make Order ingredient lines are editable snapshot rows and do not mutate the source recipe version.')"
            :default-open="true"
            item-header="Ingredient"
            :context-text-expression="''"
            :show-on-hand="true"
            :show-actions="true"
            :show-row-actions-menu="false"
        />

        <x-notes-feed :config="$payload['notesFeed']" />
    </div>
</x-resource-detail-layout>
