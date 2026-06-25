<x-resource-detail-layout>
    @php
        $breadcrumbItems = [
            [
                'label' => 'Sales Orders',
                'url' => route('sales.orders.index'),
                'current' => false,
            ],
            [
                'label' => 'ID #' . $salesOrder->id,
                'url' => null,
                'current' => true,
            ],
        ];
    @endphp

    <x-slot name="header">
        <x-resource-detail-header-breadcrumb
            :items="$breadcrumbItems"
            :title="__('Sales Order #:id', ['id' => $salesOrder->id])"
            title-class="font-semibold text-xl text-gray-800 leading-tight"
        >
            <x-slot name="titleSuffix">
                <span
                    class="inline-flex items-center rounded-full bg-gray-100 px-2.5 py-1 text-xs font-medium text-gray-700"
                    x-text="order.display_label || order.currentLabel || 'DRAFT'"
                >
                    {{ data_get($payload, 'workflow.display_label', data_get($payload, 'order.currentLabel', 'DRAFT')) }}
                </span>
            </x-slot>

            <x-slot name="actions">
                <x-workflow-action-button
                    :workflow="$payload['workflow'] ?? []"
                    mode="dispatch"
                    action-event-name="sales-order-status-action"
                    sync-event-name="sales-order-status-action-updated"
                    sync-state-key="workflow"
                />
            </x-slot>
        </x-resource-detail-header-breadcrumb>
    </x-slot>

    <script type="application/json" id="sales-orders-show-payload">@json($payload)</script>

    <div
        class="pt-0 pb-12 sm:pt-6"
        data-page="sales-orders-show"
        data-payload="sales-orders-show-payload"
        x-data="salesOrdersShow"
        x-on:sales-order-status-action.window="performHeaderWorkflowAction($event.detail)"
    >
        <x-ui.toast visible="toast.visible" type="toast.type" message="toast.message" />

        <div class="max-w-6xl mx-auto space-y-6 sm:px-6 lg:px-8">
            <div data-workflow-progress-panel x-html="workflowProgressHtml()"></div>

            <div class="bg-white border border-gray-100 shadow-sm sm:rounded-lg">
                <div class="p-6">
                    <div class="flex items-start justify-between gap-4">
                        <div>
                            <p class="text-sm text-gray-500">Status</p>
                            <p class="mt-1 text-lg font-semibold text-gray-900" x-text="order.display_label || order.currentLabel || 'DRAFT'"></p>
                        </div>
                    </div>

                    <div class="mt-6 grid gap-6 sm:grid-cols-2 lg:grid-cols-4">
                        <div>
                            <p class="text-sm text-gray-500">Order ID</p>
                            <p class="mt-1 text-base text-gray-900" x-text="order.id"></p>
                        </div>
                        <div>
                            <p class="text-sm text-gray-500">Order date</p>
                            <p class="mt-1 text-base text-gray-900" x-text="order.date || '—'"></p>
                        </div>
                        <div>
                            <p class="text-sm text-gray-500">Customer</p>
                            <p class="mt-1 text-base text-gray-900" x-text="order.customer_name || '—'"></p>
                        </div>
                        <div>
                            <p class="text-sm text-gray-500">Contact</p>
                            <p class="mt-1 text-base text-gray-900" x-text="order.contact_name || '—'"></p>
                        </div>
                        <div>
                            <p class="text-sm text-gray-500">City</p>
                            <p class="mt-1 text-base text-gray-900" x-text="order.city || '—'"></p>
                        </div>
                        <div>
                            <p class="text-sm text-gray-500">External source</p>
                            <p class="mt-1 text-base text-gray-900" x-text="order.external_source || '—'"></p>
                        </div>
                        <div>
                            <p class="text-sm text-gray-500">External ID</p>
                            <p class="mt-1 text-base text-gray-900" x-text="order.external_id || '—'"></p>
                        </div>
                        <div>
                            <p class="text-sm text-gray-500">External status</p>
                            <p class="mt-1 text-base text-gray-900" x-text="order.external_status || '—'"></p>
                        </div>
                    </div>

                    <div
                        class="mt-6 rounded-xl border border-gray-200 bg-gray-50 p-4"
                        x-data="{ showTaskCreate: false }"
                        x-on:task-created.window="order.current_stage_tasks = [...(order.current_stage_tasks || []), $event.detail.task]"
                    >
                        <div class="flex items-center justify-between gap-3">
                            <p class="text-[11px] font-semibold uppercase tracking-[0.2em] text-gray-500">Checklist</p>
                            <button
                                type="button"
                                class="inline-flex h-7 w-7 items-center justify-center rounded-lg border border-gray-300 bg-white text-gray-600 transition hover:bg-gray-50 hover:text-gray-900 sm:h-9 sm:w-9"
                                x-on:click="showTaskCreate = true"
                                aria-label="{{ __('Create task') }}"
                            >
                                <svg class="h-3.5 w-3.5 sm:h-5 sm:w-5" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" aria-hidden="true">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15" />
                                </svg>
                            </button>
                        </div>
                        <div class="mt-3 space-y-2">
                            <template x-if="(order.current_stage_tasks || []).length === 0">
                                <div class="rounded-lg border border-dashed border-gray-300 bg-white p-4 text-sm text-gray-500">No tasks for the current workflow stage.</div>
                            </template>

                            <template x-for="task in order.current_stage_tasks" :key="task.id">
                                <div class="rounded-lg border border-gray-200 bg-white p-3">
                                    <div class="flex items-start justify-between gap-3">
                                        <div>
                                            <div class="flex items-center gap-2">
                                                <p class="text-sm font-medium text-gray-900" x-text="task.title"></p>
                                                <span
                                                    class="rounded-full px-2 py-0.5 text-[10px] font-semibold uppercase tracking-wide"
                                                    :class="task.is_completed ? 'bg-emerald-100 text-emerald-700' : 'bg-amber-100 text-amber-700'"
                                                    x-text="task.status"
                                                ></span>
                                            </div>
                                            <p class="mt-1 text-xs text-gray-500" x-show="task.description" x-text="task.description"></p>
                                            <p class="mt-1 text-xs text-gray-500" x-text="task.assigned_to_user_name ? `Assigned to ${task.assigned_to_user_name}` : 'Assigned user unavailable'"></p>
                                            <p class="mt-1 text-xs text-gray-500" x-show="task.due_date" x-text="`Due ${task.due_date}`"></p>
                                        </div>

                                        <button
                                            type="button"
                                            class="inline-flex items-center rounded-md border border-emerald-300 px-2.5 py-1 text-[11px] font-semibold uppercase tracking-widest text-emerald-700 hover:bg-emerald-50"
                                            x-show="task.can_complete"
                                            x-on:click="completeTask(task)"
                                        >
                                            Complete
                                        </button>
                                    </div>
                                </div>
                            </template>
                        </div>

                        @include('tasks.partials.create-task-slide-over', [
                            'users' => collect(data_get($payload, 'taskCreate.users', [])),
                            'workflowDomainId' => data_get($payload, 'order.current_stage.workflow_domain_id'),
                            'domainRecordId' => $salesOrder->id,
                            'workflowStageId' => data_get($payload, 'order.current_stage.id'),
                        ])
                    </div>
                </div>
            </div>

            <section class="bg-white border border-gray-100 shadow-sm sm:rounded-lg">
                <div class="p-6">
                    <div class="flex items-start justify-between gap-4">
                        <div>
                            <h3 class="text-lg font-medium text-gray-900">Order lines</h3>
                            <p class="mt-1 text-sm text-gray-600">Manage order quantities and sellable items from the detail view.</p>
                        </div>
                        <div class="text-right text-sm text-gray-500">
                            <p x-text="`${order.line_count || 0} line(s)`"></p>
                            <p class="mt-1" x-text="`Currency: ${order.currency_code || '—'}`"></p>
                        </div>
                    </div>

                    <div class="mt-6 space-y-4" x-show="(order.lines || []).length > 0">
                        <template x-for="line in order.lines" :key="line.id">
                            <div class="rounded-2xl border border-gray-200 bg-gray-50/70 p-4">
                                <div class="flex items-start justify-between gap-3">
                                    <div>
                                        <p class="font-medium text-gray-900" x-text="line.item_name"></p>
                                        <p class="mt-1 text-xs text-gray-500" x-text="formatLineMoney(line.unit_price_amount, line.unit_price_currency_code)"></p>
                                        <p class="mt-1 text-xs text-gray-500" x-text="`Total: ${formatLineMoney(line.line_total_amount, line.unit_price_currency_code)}`"></p>
                                    </div>
                                    <button type="button" class="text-red-600 hover:text-red-500" x-show="canManageOrderLines()" x-on:click="deleteLine(line)">Remove</button>
                                </div>

                                <div class="mt-3 flex items-start gap-2" x-show="canManageOrderLines()">
                                    <div class="flex-1">
                                        <input
                                            type="text"
                                            class="block w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-blue-500 focus:ring-blue-500"
                                            x-model="lineEditQuantities[line.id]"
                                        />
                                        <p class="mt-1 text-xs text-red-600" x-text="(lineEditErrorsByLine[line.id] || {}).quantity?.[0]"></p>
                                    </div>
                                    <button type="button" class="inline-flex items-center rounded-md border border-gray-300 px-3 py-2 text-xs font-semibold uppercase tracking-widest text-gray-700 hover:bg-gray-50" x-on:click="saveLineQuantity(line)">
                                        Save
                                    </button>
                                </div>
                            </div>
                        </template>
                    </div>

                    <div class="mt-6 rounded-2xl border border-dashed border-gray-300 p-6 text-center text-sm text-gray-500" x-show="(order.lines || []).length === 0">
                        <p>No lines yet.</p>
                    </div>

                    <div class="mt-6 rounded-lg border border-gray-200 p-4" x-show="sellableItemsForOrder(order).length > 0 && canManageOrderLines()">
                        <div class="grid gap-3 sm:grid-cols-[minmax(0,1fr)_140px_auto]">
                            <div>
                                <select
                                    class="block w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-blue-500 focus:ring-blue-500"
                                    x-model="lineForm.item_id"
                                >
                                    <option value="">Select item</option>
                                    <template x-for="item in sellableItemsForOrder(order)" :key="item.id">
                                        <option :value="String(item.id)" x-text="`${item.name} (${item.default_price_currency_code})`"></option>
                                    </template>
                                </select>
                                <p class="mt-1 text-xs text-red-600" x-text="lineErrors.item_id[0]"></p>
                            </div>
                            <div>
                                <input
                                    type="text"
                                    class="block w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-blue-500 focus:ring-blue-500"
                                    x-model="lineForm.quantity"
                                    placeholder="1.000000"
                                />
                                <p class="mt-1 text-xs text-red-600" x-text="lineErrors.quantity[0]"></p>
                            </div>
                            <button type="button" class="inline-flex items-center justify-center rounded-md border border-transparent bg-blue-600 px-3 py-2 text-xs font-semibold uppercase tracking-widest text-white hover:bg-blue-500" x-on:click="submitLine()">
                                Add Line
                            </button>
                        </div>
                        <p class="mt-2 text-xs text-red-600" x-show="lineGeneralError" x-text="lineGeneralError"></p>
                    </div>

                    <div class="mt-6 rounded-lg border border-dashed border-gray-300 p-4" x-show="sellableItems.length > 0 && sellableItemsForOrder(order).length === 0 && canManageOrderLines()">
                        <p class="text-xs text-gray-500">No sellable items are priced in this order currency.</p>
                    </div>

                    <div class="mt-6 rounded-lg border border-dashed border-gray-300 p-4" x-show="!canManageOrderLines()">
                        <p class="text-xs text-gray-500">Line editing is unavailable once an order is completed or cancelled.</p>
                    </div>
                </div>
            </section>

            <x-notes-feed :config="$payload['notesFeed']" />
        </div>
    </div>
</x-resource-detail-layout>
