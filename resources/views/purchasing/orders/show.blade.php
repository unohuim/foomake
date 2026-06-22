<x-resource-detail-layout
    data-page="purchasing-orders-show"
    data-payload="purchasing-orders-show-payload"
    x-data="purchasingOrdersShow"
    x-on:purchase-order-status-action.window="performStatusMenuAction($event.detail)"
    x-on:workflow-updated.document="handleWorkflowUpdated($event.detail)"
>
    @php
        $purchaseOrderTitle = filled($purchaseOrder->po_number)
            ? $purchaseOrder->po_number
            : 'PO #' . $purchaseOrder->id;
        $breadcrumbItems = [
            [
                'label' => 'Purchase Orders',
                'url' => route('purchasing.orders.index'),
                'current' => false,
            ],
            [
                'label' => $purchaseOrderTitle,
                'url' => null,
                'current' => true,
            ],
        ];
        $detailsDefaultOpen = blank($purchaseOrder->supplier_id)
            || blank($purchaseOrder->order_date)
            || blank($purchaseOrder->po_number);
    @endphp

    <x-slot name="header">
        <x-resource-detail-header-breadcrumb
            :items="$breadcrumbItems"
            :title="$purchaseOrderTitle"
            class="pb-4"
        >
            @can('purchasing-purchase-orders-receive')
                @if (($payload['workflow']['actions'] ?? []) !== [])
                <x-slot name="actions">
                    <div data-purchase-order-action-button>
                        <x-workflow-action-button
                            :workflow="$payload['workflow']"
                            mode="dispatch"
                            action-event-name="purchase-order-status-action"
                            sync-event-name="workflow-updated"
                            sync-state-key="workflow"
                        />
                    </div>
                </x-slot>
                @endif
            @endcan
        </x-resource-detail-header-breadcrumb>
    </x-slot>

    <script type="application/json" id="purchasing-orders-show-payload">@json($payload)</script>

    <div class="pt-0 pb-8 sm:pt-6 sm:pb-12">
        <x-ui.toast visible="toast.visible" type="toast.type" message="toast.message" />

        <div class="mx-auto w-full min-w-0 max-w-7xl space-y-0 px-1 sm:space-y-6 sm:px-6 lg:px-8" data-purchase-order-detail-content>
            <div data-workflow-progress-panel x-html="workflowProgressHtml()"></div>

            <div class="grid w-full min-w-0 gap-0 sm:gap-6 lg:grid-cols-4 lg:items-start" data-purchase-order-detail-grid>
                <div class="min-w-0 space-y-0 sm:space-y-6 lg:col-span-3" data-purchase-order-main-column>
                    <x-detail-section-card title="Details" :default-open="$detailsDefaultOpen">
                        <div class="grid gap-6 sm:grid-cols-2">
                            <div class="max-w-sm">
                                <label class="block text-xs font-semibold uppercase text-gray-500">
                                    Supplier
                                    <span class="mt-2 flex items-center gap-2">
                                        <select class="w-full rounded border-gray-300 text-sm shadow-sm focus:border-blue-500 focus:ring-blue-500" x-model="form.supplier_id" :disabled="!isEditable" x-init="$nextTick(() => { $el.value = form.supplier_id })" x-effect="$nextTick(() => { $el.value = form.supplier_id })" x-on:change="autosaveField('supplier_id')">
                                            <option value="">Select supplier</option>
                                            <template x-for="supplier in suppliers" :key="supplier.id">
                                                <option x-bind:value="supplier.id" x-text="supplier.company_name"></option>
                                            </template>
                                        </select>
                                        <svg data-autosave-success-icon class="h-5 w-5 shrink-0 text-lime-400" x-show="savedFields.supplier_id" x-cloak xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" aria-hidden="true">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75 11.25 15 15 9.75" />
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z" />
                                        </svg>
                                    </span>
                                </label>
                                <p class="mt-1 text-xs text-red-600" x-text="headerErrors.supplier_id[0]"></p>
                            </div>
                            <div class="max-w-sm">
                                <label class="block text-xs font-semibold uppercase text-gray-500">
                                    Assigned To
                                    <span class="mt-2 flex items-center gap-2">
                                        <select class="w-full rounded border-gray-300 text-sm shadow-sm focus:border-blue-500 focus:ring-blue-500 disabled:bg-gray-50 disabled:text-gray-700" x-model="form.assigned_to_user_id" :disabled="!isEditable" x-init="$nextTick(() => { $el.value = form.assigned_to_user_id })" x-effect="$nextTick(() => { $el.value = form.assigned_to_user_id })" x-on:change="autosaveField('assigned_to_user_id')">
                                            <template x-for="assignee in purchaseOrder.assignee_options || []" :key="assignee.value">
                                                <option x-bind:value="assignee.value" x-text="assignee.label"></option>
                                            </template>
                                        </select>
                                        <svg data-autosave-success-icon class="h-5 w-5 shrink-0 text-lime-400" x-show="savedFields.assigned_to_user_id" x-cloak xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" aria-hidden="true">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75 11.25 15 15 9.75" />
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M21 12a9 9 0 1 1-18 0Z" />
                                        </svg>
                                    </span>
                                </label>
                                <p class="mt-1 text-xs text-red-600" x-text="headerErrors.assigned_to_user_id[0]"></p>
                            </div>
                            <div class="max-w-xs">
                                <label class="block text-xs font-semibold uppercase text-gray-500">
                                    Order date
                                    <span class="mt-2 flex items-center gap-2">
                                        <input type="date" class="w-full rounded border-gray-300 text-sm shadow-sm focus:border-blue-500 focus:ring-blue-500" x-model="form.order_date" :disabled="!isEditable" x-on:change="autosaveField('order_date')" x-on:blur="autosaveField('order_date')" />
                                        <svg data-autosave-success-icon class="h-5 w-5 shrink-0 text-lime-400" x-show="savedFields.order_date" x-cloak xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" aria-hidden="true">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75 11.25 15 15 9.75" />
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z" />
                                        </svg>
                                    </span>
                                </label>
                                <p class="mt-1 text-xs text-red-600" x-text="headerErrors.order_date[0]"></p>
                            </div>
                            <div class="max-w-sm">
                                <label class="block text-xs font-semibold uppercase text-gray-500">
                                    PO number
                                    <span class="mt-2 flex items-center gap-2">
                                        <input type="text" class="w-full rounded border-gray-300 text-sm shadow-sm focus:border-blue-500 focus:ring-blue-500" x-model="form.po_number" :disabled="!isEditable" x-on:blur="autosaveField('po_number')" />
                                        <svg data-autosave-success-icon class="h-5 w-5 shrink-0 text-lime-400" x-show="savedFields.po_number" x-cloak xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" aria-hidden="true">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75 11.25 15 15 9.75" />
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z" />
                                        </svg>
                                    </span>
                                </label>
                                <p class="mt-1 text-xs text-red-600" x-text="headerErrors.po_number[0]"></p>
                            </div>
                        </div>
                        <p class="mt-3 text-xs text-red-600" x-text="headerError"></p>
                    </x-detail-section-card>

            <x-detail-section-card title="Items" :default-open="true">
                <div class="space-y-4">
                    <div class="space-y-2" x-show="isEditable">
                        <div class="flex items-start gap-2 sm:gap-3">
                            <div class="min-w-0 flex-1">
                                <x-combobox
                                    name="item_purchase_option_id"
                                    class="[&_input]:rounded-lg [&_input]:px-3 [&_input]:py-2 [&_input]:pr-9 [&_input]:text-xs sm:[&_input]:text-sm"
                                    options-expression="supplierPackageComboboxOptions"
                                    selected-value=""
                                    placeholder="Search supplier packages"
                                    no-results-text="No supplier packages found."
                                    error-expression="lineErrors.item_purchase_option_id[0]"
                                    disabled-expression="!form.supplier_id"
                                    x-model="lineForm.item_purchase_option_id"
                                    x-on:change="handleOptionChange()"
                                />
                                <p class="mt-2 text-xs text-gray-500" x-show="!form.supplier_id">
                                    Select a supplier before adding supplier packages.
                                </p>
                                <span class="mt-1 block text-xs text-red-600" x-text="lineErrors.supplier_id[0]"></span>
                            </div>
                            <div class="shrink-0 pt-1">
                                <button
                                    type="button"
                                    class="inline-flex h-9 w-9 items-center justify-center rounded-lg border border-gray-300 text-gray-700 transition hover:bg-gray-50 disabled:cursor-not-allowed disabled:opacity-50"
                                    x-on:click="submitLine()"
                                    :disabled="isLineSubmitting || !form.supplier_id || !lineForm.item_purchase_option_id"
                                    :class="isLineSubmitting || !form.supplier_id || !lineForm.item_purchase_option_id ? 'cursor-not-allowed opacity-50' : ''"
                                    aria-label="Add supplier package"
                                >
                                    <svg class="h-4 w-4" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" aria-hidden="true">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15" />
                                    </svg>
                                </button>
                            </div>
                        </div>
                        <p class="mt-1 text-xs text-red-600" x-text="lineError"></p>
                    </div>

                    <div class="rounded-lg border border-gray-100 p-4 text-sm text-gray-600" x-show="!isEditable">
                        This purchase order is locked and can no longer be edited.
                    </div>

                    <div class="space-y-2 md:hidden" x-show="lines.length > 0">
                        <template x-for="line in lines" :key="'mobile-line-' + line.id">
                            <div class="relative rounded-lg border border-gray-200 bg-white p-3 shadow-sm">
                                <div class="flex items-start gap-3">
                                    <div class="min-w-0 flex-1 truncate text-sm font-medium text-gray-900" x-text="line.item_name || 'Item'"></div>
                                    <button
                                        type="button"
                                        class="inline-flex h-7 w-7 shrink-0 items-center justify-center text-gray-400 transition hover:text-red-600 disabled:cursor-not-allowed disabled:opacity-50"
                                        aria-label="Remove purchase order line"
                                        x-on:click="deleteLine(line)"
                                        :disabled="isDeleteLineSubmitting"
                                        x-show="isEditable"
                                    >
                                        <svg class="h-4 w-4" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" aria-hidden="true">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12" />
                                        </svg>
                                    </button>
                                </div>

                                <div class="mt-2 grid grid-cols-[minmax(0,1fr)_5rem_5.5rem] items-end gap-2">
                                    <div class="min-w-0">
                                        <div class="text-[0.65rem] font-semibold uppercase tracking-wide text-gray-400">Pack</div>
                                        <div class="mt-1 truncate text-xs font-medium text-gray-600" x-text="lineLabel(line)"></div>
                                    </div>

                                    <div>
                                        <div class="text-[0.65rem] font-semibold uppercase tracking-wide text-gray-400">Qty</div>
                                        <div class="mt-1 flex items-center gap-1">
                                            <span class="inline-flex h-4 w-4 shrink-0 items-center justify-center">
                                                <svg data-line-autosave-success-icon class="h-4 w-4 text-lime-400 transition-opacity" x-bind:class="lineFieldSaved(line, 'pack_count') ? 'opacity-100' : 'opacity-0'" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" aria-hidden="true">
                                                    <path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75 11.25 15 15 9.75" />
                                                    <path stroke-linecap="round" stroke-linejoin="round" d="M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z" />
                                                </svg>
                                            </span>
                                            <x-ui.smart-number-input
                                                name="pack_count"
                                                type="integer"
                                                inputmode="numeric"
                                                class="min-w-0 flex-1 [&_input]:px-2 [&_input]:py-1 [&_input]:text-xs"
                                                x-model="line.pack_count"
                                                disabled-expression="!isEditable"
                                                after-focus="$el.setSelectionRange($el.value.length, $el.value.length)"
                                                x-on:smart-number-input:changed="autosaveLineField(line, 'pack_count', $event.detail)"
                                            />
                                        </div>
                                    </div>

                                    <div>
                                        <div class="text-[0.65rem] font-semibold uppercase tracking-wide text-gray-400">Tax</div>
                                        <div class="mt-1 flex items-center gap-1">
                                            <span class="inline-flex h-4 w-4 shrink-0 items-center justify-center">
                                                <svg data-line-autosave-success-icon class="h-4 w-4 text-lime-400 transition-opacity" x-bind:class="lineFieldSaved(line, 'tax_percent') ? 'opacity-100' : 'opacity-0'" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" aria-hidden="true">
                                                    <path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75 11.25 15 15 9.75" />
                                                    <path stroke-linecap="round" stroke-linejoin="round" d="M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z" />
                                                </svg>
                                            </span>
                                            <x-ui.smart-number-input
                                                name="tax_percent"
                                                type="percent"
                                                precision="1"
                                                class="min-w-0 flex-1 [&_input]:px-2 [&_input]:py-1 [&_input]:text-xs [&_span]:pr-2 [&_span]:text-xs"
                                                x-model="line.tax_percent"
                                                disabled-expression="!isEditable"
                                                x-on:smart-number-input:changed="autosaveLineField(line, 'tax_percent', $event.detail)"
                                            />
                                        </div>
                                    </div>
                                </div>

                                <div class="mt-2 truncate text-[0.68rem] text-gray-500">
                                    Received <span x-text="line.received_sum_display"></span>
                                    <span class="px-1">·</span>
                                    Short-closed <span x-text="line.short_closed_sum_display"></span>
                                    <span class="px-1">·</span>
                                    Remaining <span x-text="line.remaining_balance_display"></span>
                                </div>

                            </div>
                        </template>
                    </div>

                    <div class="hidden overflow-x-auto md:block" x-show="lines.length > 0">
                        <table class="min-w-full divide-y divide-gray-100">
                            <thead>
                                <tr>
                                    <th class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">Item</th>
                                    <th class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">Pack</th>
                                    <th class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">Qty</th>
                                    <th class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">Tax</th>
                                    <th class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">Subtotal</th>
                                    <th class="px-4 py-3 text-right text-xs font-medium uppercase tracking-wider text-gray-500">
                                        <span class="sr-only">Remove</span>
                                    </th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-100">
                                <template x-for="line in lines" :key="line.id">
                                    <tr>
                                        <td class="px-4 py-3 text-sm text-gray-900">
                                            <div class="font-medium" x-text="line.item_name || 'Item'"></div>
                                            <div class="mt-1 text-xs text-gray-500">
                                                Received <span x-text="line.received_sum_display"></span> · Short-closed <span x-text="line.short_closed_sum_display"></span> · Remaining <span x-text="line.remaining_balance_display"></span>
                                            </div>
                                        </td>
                                        <td class="px-4 py-3 text-sm text-gray-700" x-text="lineLabel(line)"></td>
                                        <td class="px-4 py-3 text-sm text-gray-700">
                                            <div class="flex items-center gap-2">
                                                <x-ui.smart-number-input
                                                    name="pack_count"
                                                    type="integer"
                                                    inputmode="numeric"
                                                    class="w-20"
                                                    x-model="line.pack_count"
                                                    disabled-expression="!isEditable"
                                                    after-focus="$el.setSelectionRange($el.value.length, $el.value.length)"
                                                    x-on:smart-number-input:changed="autosaveLineField(line, 'pack_count', $event.detail)"
                                                />
                                                <span class="inline-flex h-5 w-5 shrink-0 items-center justify-center">
                                                    <svg data-line-autosave-success-icon class="h-5 w-5 text-lime-400 transition-opacity" x-bind:class="lineFieldSaved(line, 'pack_count') ? 'opacity-100' : 'opacity-0'" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" aria-hidden="true">
                                                        <path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75 11.25 15 15 9.75" />
                                                        <path stroke-linecap="round" stroke-linejoin="round" d="M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z" />
                                                    </svg>
                                                </span>
                                            </div>
                                        </td>
                                        <td class="px-4 py-3 text-sm text-gray-700">
                                            <div class="flex items-center gap-2">
                                                <x-ui.smart-number-input
                                                    name="tax_percent"
                                                    type="percent"
                                                    precision="1"
                                                    class="w-20"
                                                    x-model="line.tax_percent"
                                                    disabled-expression="!isEditable"
                                                    x-on:smart-number-input:changed="autosaveLineField(line, 'tax_percent', $event.detail)"
                                                />
                                                <span class="inline-flex h-5 w-5 shrink-0 items-center justify-center">
                                                    <svg data-line-autosave-success-icon class="h-5 w-5 text-lime-400 transition-opacity" x-bind:class="lineFieldSaved(line, 'tax_percent') ? 'opacity-100' : 'opacity-0'" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" aria-hidden="true">
                                                        <path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75 11.25 15 15 9.75" />
                                                        <path stroke-linecap="round" stroke-linejoin="round" d="M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z" />
                                                    </svg>
                                                </span>
                                            </div>
                                        </td>
                                        <td class="px-4 py-3 text-sm text-gray-700" x-text="formatMoney(line.line_subtotal_cents)"></td>
                                        <td class="px-4 py-3 text-right text-sm">
                                            <div class="flex justify-end" x-show="isEditable">
                                                <button
                                                    type="button"
                                                    class="inline-flex h-8 w-8 items-center justify-center rounded-full border border-gray-300 text-gray-500 transition hover:bg-red-50 hover:text-red-600 disabled:cursor-not-allowed disabled:opacity-50"
                                                    aria-label="Remove purchase order line"
                                                    x-on:click="deleteLine(line)"
                                                    :disabled="isDeleteLineSubmitting"
                                                >
                                                    <svg class="h-4 w-4" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" aria-hidden="true">
                                                        <path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12" />
                                                    </svg>
                                                </button>
                                            </div>
                                            <div class="flex justify-end gap-3" x-show="!isEditable && canReceive">
                                                <button type="button" class="text-yellow-600 hover:text-yellow-500" x-on:click="openShortCloseLine(line)" x-show="canShortCloseLine(line)">Short-Close</button>
                                            </div>
                                        </td>
                                    </tr>
                                </template>
                            </tbody>
                        </table>
                    </div>

                    <div class="rounded-lg border border-gray-200 bg-white p-4" x-show="editingLineId !== null" x-cloak>
                        <div class="grid gap-4 sm:grid-cols-4">
                            <label class="block text-xs font-semibold uppercase text-gray-500">
                                Unit price (cents)
                                <input type="number" min="0" class="mt-1 w-full rounded border-gray-300 text-sm shadow-sm focus:border-blue-500 focus:ring-blue-500" x-model="editForm.unit_price_cents" />
                                <span class="mt-1 block text-xs text-red-600" x-text="editErrors.unit_price_cents[0]"></span>
                            </label>
                            <label class="block text-xs font-semibold uppercase text-gray-500">
                                Pack count
                                <x-ui.smart-number-input
                                    name="pack_count"
                                    type="integer"
                                    inputmode="numeric"
                                    x-model="editForm.pack_count"
                                />
                                <span class="mt-1 block text-xs text-red-600" x-text="editErrors.pack_count[0]"></span>
                            </label>
                            <label class="block text-xs font-semibold uppercase text-gray-500">
                                Tax %
                                <x-ui.smart-number-input
                                    name="tax_percent"
                                    type="percent"
                                    precision="1"
                                    x-model="editForm.tax_percent"
                                />
                                <span class="mt-1 block text-xs text-red-600" x-text="editErrors.tax_percent[0]"></span>
                            </label>
                            <div class="flex items-end justify-end gap-3">
                                <button type="button" class="inline-flex items-center rounded-md border border-gray-300 px-3 py-2 text-xs font-semibold uppercase tracking-widest text-gray-700 hover:bg-gray-50" x-on:click="closeEditLine()">
                                    Cancel
                                </button>
                                <button type="button" class="inline-flex items-center rounded-md border border-transparent bg-blue-600 px-4 py-2 text-xs font-semibold uppercase tracking-widest text-white hover:bg-blue-500" x-on:click="submitEditLine(lines.find((line) => line.id === editingLineId))" :disabled="isEditSubmitting" :class="isEditSubmitting ? 'cursor-not-allowed opacity-50' : ''">
                                    Save
                                </button>
                            </div>
                        </div>
                    </div>

                    <div class="rounded-lg border border-dashed border-gray-200 bg-gray-50 p-4 text-sm text-gray-600" x-show="lines.length === 0">
                        No lines yet. Add a purchase option pack to start pricing this order.
                    </div>
                </div>
            </x-detail-section-card>

                </div>

                <div class="min-w-0 space-y-0 sm:space-y-6 lg:col-span-1" data-purchase-order-side-column>
                    <x-detail-section-card title="Totals" :default-open="true">
                <dl class="divide-y divide-gray-100 text-sm">
                    <div class="flex items-center justify-between py-3">
                        <dt class="text-gray-600">Subtotal</dt>
                        <dd class="font-medium text-gray-900" x-text="formatMoney(purchaseOrder.po_subtotal_cents)"></dd>
                    </div>
                    <div class="flex items-center justify-between py-3">
                        <dt class="text-gray-600">Shipping</dt>
                        <dd class="flex items-center gap-2 font-medium text-gray-900">
                            <span class="inline-flex h-5 w-5 shrink-0 items-center justify-center">
                                <svg data-autosave-success-icon class="h-5 w-5 text-lime-400 transition-opacity" x-bind:class="savedFields.shipping_amount ? 'opacity-100' : 'opacity-0'" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" aria-hidden="true">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75 11.25 15 15 9.75" />
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z" />
                                </svg>
                            </span>
                            <x-ui.smart-number-input
                                name="shipping_amount"
                                type="money"
                                inputmode="decimal"
                                class="w-28"
                                x-model="form.shipping_amount"
                                disabled-expression="!isEditable"
                                x-on:smart-number-input:changed="autosaveField('shipping_amount', $event.detail)"
                            />
                        </dd>
                    </div>
                    <div class="flex items-center justify-between py-3">
                        <dt class="text-gray-600">Tax</dt>
                        <dd class="font-medium text-gray-900" x-text="formatMoney(purchaseOrder.tax_cents)"></dd>
                    </div>
                    <div class="flex items-center justify-between py-4 text-base">
                        <dt class="font-semibold text-gray-900">Grand total</dt>
                        <dd class="font-semibold text-gray-900" x-text="formatMoney(purchaseOrder.po_grand_total_cents)"></dd>
                    </div>
                </dl>
            </x-detail-section-card>
                </div>

                <div class="min-w-0 space-y-0 sm:space-y-6 lg:col-span-3" data-purchase-order-main-lower-column>
                    <x-notes-feed :config="$payload['notesFeed']" />

                    <div
                        x-data="{ showTaskCreate: false }"
                        x-on:task-created.window="workflow.currentStageTasks = [...(workflow.currentStageTasks || []), $event.detail.task]"
                    >
                        <x-detail-section-card
                            title="Tasks"
                            :description="__('Complete current stage tasks before moving the purchase order forward.')"
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

                            <div class="divide-y divide-gray-100 rounded-xl border border-gray-200 bg-white">
                            <template x-if="(workflow.currentStageTasks || []).length === 0">
                                <div class="px-4 py-4 text-sm text-gray-500">{{ __('No tasks for the current workflow stage.') }}</div>
                            </template>

                            <template x-for="task in workflow.currentStageTasks || []" :key="task.id">
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
                        </x-detail-section-card>

                        @include('tasks.partials.create-task-slide-over', [
                            'users' => collect(data_get($payload, 'taskCreate.users', [])),
                            'workflowDomainId' => data_get($payload, 'workflow.currentStage.workflow_domain_id'),
                            'domainRecordId' => $purchaseOrder->id,
                            'workflowStageId' => data_get($payload, 'workflow.currentStage.id'),
                        ])
                    </div>

                    <x-detail-section-card title="Receipt History" :default-open="false">
                        <div class="overflow-x-auto" x-show="receipts.length > 0">
                            <table class="min-w-full divide-y divide-gray-100">
                                <thead>
                                    <tr>
                                        <th class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">Received At</th>
                                        <th class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">Received By</th>
                                        <th class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">Reference</th>
                                        <th class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">Notes</th>
                                        <th class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">Lines</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-gray-100">
                                    <template x-for="receipt in receipts" :key="receipt.id">
                                        <tr>
                                            <td class="px-4 py-3 text-sm text-gray-700" x-text="receipt.received_at || '—'"></td>
                                            <td class="px-4 py-3 text-sm text-gray-700" x-text="receipt.received_by || '—'"></td>
                                            <td class="px-4 py-3 text-sm text-gray-700" x-text="receipt.reference || '—'"></td>
                                            <td class="px-4 py-3 text-sm text-gray-700" x-text="receipt.notes || '—'"></td>
                                            <td class="px-4 py-3 text-sm text-gray-700" x-text="receiptLineSummary(receipt)"></td>
                                        </tr>
                                    </template>
                                </tbody>
                            </table>
                        </div>
                        <div class="rounded-lg border border-dashed border-gray-200 bg-gray-50 p-4 text-sm text-gray-600" x-show="receipts.length === 0">
                            No receipts yet.
                        </div>
                    </x-detail-section-card>

                    <x-detail-section-card title="Short-Close History" :default-open="false">
                        <div class="overflow-x-auto" x-show="shortClosures.length > 0">
                            <table class="min-w-full divide-y divide-gray-100">
                                <thead>
                                    <tr>
                                        <th class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">Short-Closed At</th>
                                        <th class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">Short-Closed By</th>
                                        <th class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">Reference</th>
                                        <th class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">Notes</th>
                                        <th class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">Lines</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-gray-100">
                                    <template x-for="shortClose in shortClosures" :key="shortClose.id">
                                        <tr>
                                            <td class="px-4 py-3 text-sm text-gray-700" x-text="shortClose.short_closed_at || '—'"></td>
                                            <td class="px-4 py-3 text-sm text-gray-700" x-text="shortClose.short_closed_by || '—'"></td>
                                            <td class="px-4 py-3 text-sm text-gray-700" x-text="shortClose.reference || '—'"></td>
                                            <td class="px-4 py-3 text-sm text-gray-700" x-text="shortClose.notes || '—'"></td>
                                            <td class="px-4 py-3 text-sm text-gray-700" x-text="shortCloseLineSummary(shortClose)"></td>
                                        </tr>
                                    </template>
                                </tbody>
                            </table>
                        </div>
                        <div class="rounded-lg border border-dashed border-gray-200 bg-gray-50 p-4 text-sm text-gray-600" x-show="shortClosures.length === 0">
                            No short-closes yet.
                        </div>
                    </x-detail-section-card>
                </div>
            </div>

        </div>
    </div>

    <x-slot name="overlays">
        <div
            class="fixed inset-0 z-50 flex items-center justify-center"
            x-show="isDeleteLineOpen"
            x-cloak
        >
            <div class="fixed inset-0 bg-gray-900/30" x-on:click="closeDeleteLine()"></div>
            <div class="relative z-50 w-full max-w-md mx-4 bg-white rounded-lg shadow-xl">
                <div class="p-6">
                    <h3 class="text-lg font-medium text-gray-900">Remove line</h3>
                    <p class="mt-2 text-sm text-gray-600">This will delete the line from the draft order.</p>
                    <p class="mt-3 text-sm text-gray-800" x-text="deleteLineLabel"></p>
                    <p class="mt-2 text-sm text-red-600" x-text="deleteLineError"></p>
                    <div class="mt-6 flex justify-end gap-3">
                        <button
                            type="button"
                            class="inline-flex items-center px-3 py-2 border border-gray-300 rounded-md text-xs font-semibold text-gray-700 uppercase tracking-widest hover:bg-gray-50"
                            x-on:click="closeDeleteLine()"
                        >
                            Cancel
                        </button>
                        <button
                            type="button"
                            class="inline-flex items-center px-3 py-2 bg-red-600 border border-transparent rounded-md text-xs font-semibold text-white uppercase tracking-widest hover:bg-red-500"
                            x-on:click="confirmDeleteLine()"
                            :disabled="isDeleteLineSubmitting"
                            :class="isDeleteLineSubmitting ? 'opacity-50 cursor-not-allowed' : ''"
                        >
                            Delete
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <div
            class="fixed inset-0 z-50 flex items-center justify-center"
            x-show="isDeleteOrderOpen"
            x-cloak
        >
            <div class="fixed inset-0 bg-gray-900/30" x-on:click="closeDeleteOrder()"></div>
            <div class="relative z-50 w-full max-w-md mx-4 bg-white rounded-lg shadow-xl">
                <div class="p-6">
                    <h3 class="text-lg font-medium text-gray-900">Delete draft purchase order</h3>
                    <p class="mt-2 text-sm text-gray-600">This action cannot be undone.</p>
                    <p class="mt-2 text-sm text-red-600" x-text="deleteOrderError"></p>
                    <div class="mt-6 flex justify-end gap-3">
                        <button
                            type="button"
                            class="inline-flex items-center px-3 py-2 border border-gray-300 rounded-md text-xs font-semibold text-gray-700 uppercase tracking-widest hover:bg-gray-50"
                            x-on:click="closeDeleteOrder()"
                        >
                            Cancel
                        </button>
                        <button
                            type="button"
                            class="inline-flex items-center px-3 py-2 bg-red-600 border border-transparent rounded-md text-xs font-semibold text-white uppercase tracking-widest hover:bg-red-500"
                            x-on:click="confirmDeleteOrder()"
                            :disabled="isDeleteOrderSubmitting"
                            :class="isDeleteOrderSubmitting ? 'opacity-50 cursor-not-allowed' : ''"
                        >
                            Delete
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <div
            class="fixed inset-0 z-50 flex justify-end"
            x-show="isReceiveOpen"
            x-cloak
            x-on:keydown.escape.window="closeReceive()"
        >
            <div class="fixed inset-0 bg-gray-900/30" x-on:click="closeReceive()"></div>
            <form class="relative z-50 flex h-full w-full max-w-3xl flex-col bg-white shadow-xl" x-on:submit.prevent="submitReceive()">
                <div class="border-b border-gray-100 p-6">
                    <div class="flex items-start justify-between">
                        <div>
                            <h3 class="text-lg font-medium text-gray-900">Receive Purchase Order</h3>
                            <p class="mt-1 text-sm text-gray-600">Record one receipt with one or more received lines.</p>
                        </div>
                        <button
                            type="button"
                            class="inline-flex h-9 w-9 items-center justify-center rounded-lg text-gray-400 hover:bg-gray-50 hover:text-gray-600"
                            x-on:click="closeReceive()"
                            aria-label="Close"
                        >
                            ×
                        </button>
                    </div>
                </div>

                <div class="min-h-0 flex-1 overflow-y-auto p-6">
                    <div class="space-y-4">
                        <div class="grid gap-4 sm:grid-cols-2">
                            <div>
                                <label class="block text-xs font-semibold uppercase text-gray-500">
                                    Received at
                                    <input
                                        type="datetime-local"
                                        class="mt-1 w-full rounded border-gray-300 text-sm shadow-sm focus:border-blue-500 focus:ring-blue-500"
                                        x-model="receiveForm.received_at"
                                        x-on:input="collapseReceiveDatePicker($event)"
                                        x-on:change="collapseReceiveDatePicker($event)"
                                    />
                                </label>
                                <p class="mt-1 text-xs text-red-600" x-text="receiveErrors.received_at[0]"></p>
                            </div>
                            <div>
                                <label class="block text-xs font-semibold uppercase text-gray-500">
                                    Reference
                                    <input
                                        type="text"
                                        class="mt-1 w-full rounded border-gray-300 text-sm shadow-sm focus:border-blue-500 focus:ring-blue-500"
                                        x-model="receiveForm.reference"
                                    />
                                </label>
                                <p class="mt-1 text-xs text-red-600" x-text="receiveErrors.reference[0]"></p>
                            </div>
                        </div>
                        <div>
                            <label class="block text-xs font-semibold uppercase text-gray-500">
                                Notes
                                <textarea
                                    rows="2"
                                    class="mt-1 w-full rounded border-gray-300 text-sm shadow-sm focus:border-blue-500 focus:ring-blue-500"
                                    x-model="receiveForm.notes"
                                ></textarea>
                            </label>
                            <p class="mt-1 text-xs text-red-600" x-text="receiveErrors.notes[0]"></p>
                        </div>

                        <div class="space-y-3">
                            <template x-for="(line, index) in receiveForm.lines" :key="line.id">
                                <div class="rounded-md border border-gray-100 bg-white p-4 shadow-sm">
                                    <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
                                        <div class="min-w-0">
                                            <div class="font-medium text-gray-900" x-text="line.item_name || 'Item'"></div>
                                            <div class="mt-1 text-xs text-gray-500" x-text="line.unit_context"></div>
                                        </div>
                                        <div class="grid grid-cols-3 gap-3 text-right text-xs text-gray-500">
                                            <div>
                                                <div class="font-semibold uppercase text-gray-400">Ordered</div>
                                                <div class="mt-1 text-gray-700" x-text="line.ordered_quantity_display"></div>
                                            </div>
                                            <div>
                                                <div class="font-semibold uppercase text-gray-400">Received</div>
                                                <div class="mt-1 text-gray-700" x-text="line.received_quantity_display"></div>
                                            </div>
                                            <div>
                                                <div class="font-semibold uppercase text-gray-400">Outstanding</div>
                                                <div class="mt-1 text-gray-700" x-text="line.remaining_balance_display"></div>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="mt-2">
                                        <label class="block text-xs font-semibold uppercase text-gray-500">
                                            Receive quantity
                                            <x-ui.smart-number-input
                                                name="received_quantity"
                                                type="integer"
                                                inputmode="numeric"
                                                min="0"
                                                step="1"
                                                x-model="line.received_quantity"
                                            />
                                        </label>
                                        <p class="mt-1 text-xs text-red-600" x-text="receiveLineError(index)"></p>
                                    </div>
                                </div>
                            </template>
                        </div>

                        <p class="text-xs text-red-600" x-text="receiveError"></p>
                    </div>
                </div>

                <div class="border-t border-gray-100 bg-white p-4">
                    <div class="flex justify-end gap-3">
                        <button
                            type="button"
                            class="inline-flex items-center px-4 py-2 border border-gray-300 rounded-md text-xs font-semibold text-gray-700 uppercase tracking-widest hover:bg-gray-50"
                            x-on:click="closeReceive()"
                        >
                            Cancel
                        </button>
                        <button
                            type="submit"
                            class="inline-flex items-center px-4 py-2 bg-blue-600 border border-transparent rounded-md text-xs font-semibold text-white uppercase tracking-widest hover:bg-blue-500"
                            :disabled="isReceiveSubmitting"
                            :class="isReceiveSubmitting ? 'opacity-50 cursor-not-allowed' : ''"
                        >
                            Receive
                        </button>
                    </div>
                </div>
            </form>
        </div>

        <div
            class="fixed inset-0 z-50 flex items-center justify-center"
            x-show="isShortCloseOpen"
            x-cloak
            x-on:keydown.escape.window="closeShortClose()"
        >
            <div class="fixed inset-0 bg-gray-900/30" x-on:click="closeShortClose()"></div>
            <div class="relative z-50 w-full max-w-lg mx-4 bg-white rounded-lg shadow-xl">
                <div class="p-6">
                    <div class="flex items-start justify-between">
                        <div>
                            <h3 class="text-lg font-medium text-gray-900">Short-Close</h3>
                            <p class="mt-1 text-sm text-gray-600" x-text="shortCloseLineLabel"></p>
                        </div>
                        <button
                            type="button"
                            class="text-gray-400 hover:text-gray-600"
                            x-on:click="closeShortClose()"
                            aria-label="Close"
                        >
                            ×
                        </button>
                    </div>

                    <div class="mt-6 space-y-4">
                        <div class="grid gap-4 sm:grid-cols-2">
                            <div>
                                <label class="block text-xs font-semibold uppercase text-gray-500">
                                    Short-closed at
                                    <input
                                        type="datetime-local"
                                        class="mt-1 w-full rounded border-gray-300 text-sm shadow-sm focus:border-blue-500 focus:ring-blue-500"
                                        x-model="shortCloseForm.short_closed_at"
                                    />
                                </label>
                                <p class="mt-1 text-xs text-red-600" x-text="shortCloseErrors.short_closed_at[0]"></p>
                            </div>
                            <div>
                                <label class="block text-xs font-semibold uppercase text-gray-500">
                                    Reference
                                    <input
                                        type="text"
                                        class="mt-1 w-full rounded border-gray-300 text-sm shadow-sm focus:border-blue-500 focus:ring-blue-500"
                                        x-model="shortCloseForm.reference"
                                    />
                                </label>
                                <p class="mt-1 text-xs text-red-600" x-text="shortCloseErrors.reference[0]"></p>
                            </div>
                        </div>
                        <div>
                            <label class="block text-xs font-semibold uppercase text-gray-500">
                                Notes
                                <textarea
                                    rows="2"
                                    class="mt-1 w-full rounded border-gray-300 text-sm shadow-sm focus:border-blue-500 focus:ring-blue-500"
                                    x-model="shortCloseForm.notes"
                                ></textarea>
                            </label>
                            <p class="mt-1 text-xs text-red-600" x-text="shortCloseErrors.notes[0]"></p>
                        </div>
                        <div>
                            <label class="block text-xs font-semibold uppercase text-gray-500">
                                Short-close quantity
                                <x-ui.smart-number-input
                                    name="short_closed_quantity"
                                    type="decimal"
                                    precision="6"
                                    min="0"
                                    step="0.000001"
                                    x-model="shortCloseForm.short_closed_quantity"
                                />
                            </label>
                            <p class="mt-1 text-xs text-red-600" x-text="shortCloseErrors.short_closed_quantity[0]"></p>
                        </div>

                        <p class="text-xs text-red-600" x-text="shortCloseError"></p>

                        <div class="mt-6 flex justify-end gap-3">
                            <button
                                type="button"
                                class="inline-flex items-center px-4 py-2 border border-gray-300 rounded-md text-xs font-semibold text-gray-700 uppercase tracking-widest hover:bg-gray-50"
                                x-on:click="closeShortClose()"
                            >
                                Cancel
                            </button>
                            <button
                                type="button"
                                class="inline-flex items-center px-4 py-2 bg-yellow-600 border border-transparent rounded-md text-xs font-semibold text-white uppercase tracking-widest hover:bg-yellow-500"
                                x-on:click="submitShortClose()"
                                :disabled="isShortCloseSubmitting"
                                :class="isShortCloseSubmitting ? 'opacity-50 cursor-not-allowed' : ''"
                            >
                                Short-Close
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </x-slot>
</x-resource-detail-layout>
