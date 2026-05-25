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
        <x-resource-detail-header-breadcrumb
            :items="$payload['breadcrumbs'] ?? []"
            :title="$makeOrderPayload['title'] ?? ('Make Order ' . $makeOrder->id)"
        >
            <x-slot name="metadata">
                <div
                    class="flex flex-wrap items-center gap-x-3 gap-y-2 text-sm text-gray-500"
                    data-resource-detail-header-metadata-group="primary"
                >
                    <span class="rounded-full bg-gray-100 px-2.5 py-1 text-xs font-medium text-gray-700">
                        {{ $makeOrderPayload['recipe_name'] ?? '—' }}
                    </span>
                    <span class="rounded-full bg-gray-100 px-2.5 py-1 text-xs font-medium text-gray-700">
                        {{ __('Runs') }} {{ $makeOrderPayload['runs_text'] ?? '—' }}
                    </span>
                    <span class="rounded-full bg-gray-100 px-2.5 py-1 text-xs font-medium text-gray-700">
                        {{ $makeOrderPayload['workflow_state'] ?? 'DRAFT' }}
                    </span>
                </div>

                <div
                    class="flex flex-wrap items-center gap-x-3 gap-y-2 text-sm text-gray-500"
                    data-resource-detail-header-metadata-group="secondary"
                >
                    <span class="rounded-full bg-gray-100 px-2.5 py-1 text-xs font-medium text-gray-700">
                        {{ $makeOrderPayload['output_item_name'] ?? '—' }}
                    </span>
                    <span class="rounded-full bg-gray-100 px-2.5 py-1 text-xs font-medium text-gray-700">
                        {{ __('Expected Output') }} {{ $makeOrderPayload['produced_quantity_text'] ?? '—' }}
                    </span>
                </div>
            </x-slot>

            @if (($payload['workflow']['next_stage_action']['label'] ?? null) !== null)
                <x-slot name="actions">
                    <div class="flex items-center justify-end">
                        <button
                            type="button"
                            class="inline-flex items-center justify-center rounded-md border border-transparent bg-slate-900 px-4 py-2 text-xs font-semibold uppercase tracking-widest text-white transition hover:bg-slate-800 focus:outline-none focus:ring-2 focus:ring-slate-500 focus:ring-offset-2"
                            x-data="{}"
                            x-on:click.prevent="window.dispatchEvent(new CustomEvent('make-order-next-stage', { detail: { workflowStageId: {{ (int) ($payload['workflow']['next_stage_action']['id'] ?? 0) }} } }))"
                        >
                            {{ $payload['workflow']['next_stage_action']['label'] ?? '' }}
                        </button>
                    </div>
                </x-slot>
            @endif
        </x-resource-detail-header-breadcrumb>
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
            title="Workflow"
            :description="__('Move this Make Order through configured operational workflow stages without changing its lifecycle status.')"
            :default-open="$payload['workflow']['default_open'] ?? true"
        >
            <div class="space-y-4">
                <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                    <div>
                        <p class="text-xs font-semibold uppercase tracking-wide text-gray-500">{{ __('Due Date') }}</p>
                        <p class="mt-1 text-sm text-gray-900" x-text="workflow.due_date || 'No due date'"></p>
                    </div>
                    <div>
                        <p class="text-xs font-semibold uppercase tracking-wide text-gray-500">{{ __('Assigned To') }}</p>
                        <div class="mt-1">
                            <x-dropdown-select
                                name="workflow_made_by_user_id"
                                options-expression="workflow.assignee_options"
                                placeholder="Unassigned"
                                disabled-expression="!workflow.can_edit_assignment || workflowAssignmentSaving"
                                x-model="workflow.made_by_user_id"
                                class="max-w-xs"
                            />
                        </div>
                    </div>
                    <div>
                        <p class="text-xs font-semibold uppercase tracking-wide text-gray-500">{{ __('Current Stage') }}</p>
                        <p class="mt-1 text-sm text-gray-900" x-text="workflow.current_stage_label || 'DRAFT'"></p>
                    </div>
                </div>

                <div class="rounded-xl border border-gray-200 bg-gray-50 p-4">
                    <div class="flex flex-col gap-3 sm:flex-row sm:items-end sm:justify-between">
                        <div class="min-w-0 flex-1">
                            <p class="text-xs font-semibold uppercase tracking-wide text-gray-500">{{ __('Move Stage') }}</p>
                            <p class="mt-1 text-sm text-gray-600">{{ __('Available workflow stages come from configured manufacturing workflow stages for this tenant.') }}</p>
                        </div>

                        <div class="grid gap-2 sm:w-auto sm:grid-cols-[minmax(0,14rem)_auto]">
                            <select
                                class="block w-full rounded-lg border-gray-300 text-sm shadow-sm focus:border-blue-500 focus:ring-blue-500"
                                x-model="selectedWorkflowStageId"
                                x-bind:disabled="!workflow.can_move_stage || workflowTransitionSaving"
                            >
                                <option value="">{{ __('Select stage') }}</option>
                                <template x-for="stage in workflow.available_stages" :key="stage.id">
                                    <option x-bind:value="String(stage.id)" x-text="stage.name"></option>
                                </template>
                            </select>

                            <button
                                type="button"
                                class="inline-flex h-10 items-center justify-center rounded-lg border border-gray-300 px-4 text-sm font-medium text-gray-700 transition hover:bg-gray-50 disabled:cursor-not-allowed disabled:opacity-50"
                                x-on:click="moveWorkflowStage()"
                                x-bind:disabled="!workflow.can_move_stage || !selectedWorkflowStageId || workflowTransitionSaving"
                            >
                                {{ __('Move') }}
                            </button>
                        </div>
                    </div>
                </div>

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
        />
    </div>
</x-resource-detail-layout>
