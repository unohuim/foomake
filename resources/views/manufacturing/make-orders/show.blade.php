<x-resource-detail-layout
    class="relative"
    data-page="manufacturing-make-orders-show"
    data-payload="manufacturing-make-orders-show-payload"
    x-data="manufacturingMakeOrdersShow"
    x-on:make-order-header-action.window="performHeaderWorkflowAction($event.detail)"
    x-on:make-order-next-stage.window="moveWorkflowStageTo($event.detail.workflowStageId)"
>
    @php
        $makeOrderPayload = $payload['makeOrder'] ?? [];
    @endphp

    <x-slot name="header">
        <div>
            <x-resource-detail-header-breadcrumb
                :items="$payload['breadcrumbs'] ?? []"
                :title="$makeOrderPayload['output_item_name'] ?? '—'"
                x-data="makeOrderHeaderState('manufacturing-make-orders-show-payload')"
            >
                <x-slot name="top">
                    <x-ui.validation-banner class="mt-0 sm:mt-3" />
                </x-slot>

                <x-slot name="titleAbove">
                    {{ 'MO-' . $makeOrder->id }}
                </x-slot>

                <x-slot name="titleSuffix">
                    <span class="text-sm font-medium text-gray-500">
                        {{ $makeOrderPayload['due_date'] ?? 'No due date' }}
                    </span>
                </x-slot>

                <x-slot name="metadata">
                    <div class="space-y-1 text-sm text-gray-600">
                        <div class="flex flex-wrap items-baseline gap-x-2 gap-y-1 text-[0.65rem] font-medium uppercase tracking-wide text-gray-500">
                            <span>{{ __('Recipe') }}</span>
                            <span class="text-[0.85rem] font-semibold text-gray-700">{{ $makeOrderPayload['recipe_name'] ?? '—' }}</span>
                            <span class="text-gray-400">{{ $makeOrderPayload['display']['versionBadgeText'] ?? ('v' . ($makeOrderPayload['recipe_version_number_display'] ?? '—')) }}</span>
                            <span>{{ __('Runs') }}</span>
                            <span class="text-sm font-semibold text-gray-700">{{ $makeOrderPayload['runs_text'] ?? '—' }}</span>
                        </div>

                        <div class="flex flex-wrap items-baseline gap-x-2 gap-y-1 text-[0.65rem] font-medium uppercase tracking-wide text-gray-500">
                            <span>{{ __('Expected') }}</span>
                            <span class="font-medium text-gray-700">
                                @php
                                    $expectedOutputQtyText = trim((string) ($makeOrderPayload['expected_output_qty_text'] ?? ''));
                                    $actualOutputQtyText = trim((string) ($makeOrderPayload['actual_output_qty_text'] ?? ''));
                                    $outputUomSymbol = trim((string) ($makeOrderPayload['output_uom_symbol'] ?? ''));
                                    $formatQuantity = static function (string $value): string {
                                        $normalized = trim($value);

                                        if ($normalized === '' || !is_numeric($normalized)) {
                                            return '';
                                        }

                                        $formatted = number_format((float) $normalized, 6, '.', ',');

                                        return rtrim(rtrim($formatted, '0'), '.');
                                    };
                                @endphp
                                {{ $expectedOutputQtyText !== '' ? $formatQuantity($expectedOutputQtyText) . ($outputUomSymbol !== '' ? ' ' . $outputUomSymbol : '') : '' }}
                            </span>
                            <span class="px-2">{{ __('Actual') }}</span>
                            <span class="font-medium text-gray-700">
                                {{ $actualOutputQtyText !== '' ? $formatQuantity($actualOutputQtyText) . ($outputUomSymbol !== '' ? ' ' . $outputUomSymbol : '') : '' }}
                            </span>
                        </div>
                    </div>
                </x-slot>

                <x-slot name="actions">
                    <x-workflow-action-button
                        :workflow="$payload['workflow']"
                        mode="dispatch"
                        action-event-name="make-order-header-action"
                        sync-event-name="make-order-header-action-updated"
                        sync-state-key="workflow"
                    />
                </x-slot>
            </x-resource-detail-header-breadcrumb>

        </div>
    </x-slot>

    <script type="application/json" id="manufacturing-make-orders-show-payload">@json($payload)</script>

    <x-ui.toast visible="toast.visible" type="toast.type" message="toast.message" />

    <x-ui.workflow-progress
        :steps="$payload['workflowProgressSteps'] ?? []"
        :neutral_draft="true"
        class="-mx-1 sm:mx-0"
        data-workflow-progress-panel
    />

    <div class="mx-auto max-w-5xl space-y-0 px-1 pt-0 pb-8 sm:space-y-6 sm:px-6 sm:pt-6 sm:pb-12 lg:px-8" data-make-order-detail-content>
        <x-detail-section-card
            title="Details"
            :description="__('Runs, expected output, actual output, due date, and assignment live here. Workflow movement stays in the header action.')"
            :default-open="$payload['workflow']['default_open'] ?? false"
        >
            <div class="space-y-3" data-make-order-workflow-metadata-row>
                <div class="grid grid-cols-3 gap-2">
                    <div class="space-y-1">
                        <p class="text-[10px] font-semibold uppercase tracking-wide text-gray-500 sm:text-xs">{{ __('Runs') }}</p>
                        <x-ui.smart-number-input
                            name="runs"
                            type="decimal"
                            precision="6"
                            value="{{ $makeOrderPayload['runs_text'] ?? '' }}"
                            x-model="makeOrder.runs_text"
                            after-input="recalculateExpectedOutputQtyFromRuns()"
                            after-change="saveMakeOrderDetailQuantity('runs')"
                        />
                    </div>

                    <div class="space-y-1">
                        <p class="text-[10px] font-semibold uppercase tracking-wide text-gray-500 sm:text-xs">{{ __('Expected Output') }}</p>
                        <x-ui.smart-number-input
                            name="expected_output_qty"
                            type="decimal"
                            precision="6"
                            value="{{ $makeOrderPayload['expected_output_qty_text'] ?? '' }}"
                            :readonly="true"
                            x-model="makeOrder.expected_output_qty_text"
                        />
                    </div>

                    <div class="space-y-1">
                        <p class="text-[10px] font-semibold uppercase tracking-wide text-gray-500 sm:text-xs">{{ __('Actual Output') }}</p>
                        <x-ui.smart-number-input
                            name="actual_output_qty"
                            type="decimal"
                            precision="6"
                            value="{{ $makeOrderPayload['actual_output_qty_text'] ?? '' }}"
                            x-model="makeOrder.actual_output_qty_text"
                            after-change="saveMakeOrderDetailQuantity('actual_output_qty')"
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
                            x-on:click="if (!$el.disabled) { $el.showPicker?.() }"
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

        <div
            x-data="{ showTaskCreate: false }"
            x-on:task-created.window="workflow.current_stage_tasks = [...workflow.current_stage_tasks, $event.detail.task]"
        >
            <x-detail-section-card
                title="Tasks"
                :description="__('Complete current stage tasks separately from workflow metadata editing.')"
                :default-open="false"
            >
                <x-slot name="actions">
                    <button
                        type="button"
                        class="inline-flex h-7 w-7 items-center justify-center rounded-lg border border-gray-300 text-gray-600 transition hover:bg-gray-50 hover:text-gray-900 sm:h-10 sm:w-10"
                        x-on:click="showTaskCreate = true"
                        aria-label="{{ __('Create task') }}"
                    >
                        <svg class="h-3.5 w-3.5 sm:h-5 sm:w-5" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15" />
                        </svg>
                    </button>
                </x-slot>

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
                                <p class="mt-1 text-xs text-gray-500" x-show="task.due_date">
                                    <span>{{ __('Due') }}:</span>
                                    <span x-text="task.due_date"></span>
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

            @include('tasks.partials.create-task-slide-over', [
                'users' => collect(data_get($payload, 'taskCreate.users', [])),
                'workflowDomainId' => data_get($payload, 'workflow.current_stage.workflow_domain_id'),
                'domainRecordId' => $makeOrder->id,
                'workflowStageId' => data_get($payload, 'workflow.current_stage.id'),
            ])
        </div>

        <x-ingredients-detail-section
            title="Ingredients"
            :description="__('Make Order ingredient lines are editable snapshot rows and do not mutate the source recipe version.')"
            :default-open="true"
            item-header="Ingredient"
            context-text-expression="''"
            :show-on-hand="true"
            :show-actions="true"
            :show-row-actions-menu="false"
        />

        <x-notes-feed :config="$payload['notesFeed']" />
    </div>
</x-resource-detail-layout>
