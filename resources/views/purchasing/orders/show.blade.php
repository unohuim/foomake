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
                'label' => 'Home',
                'url' => url('/'),
            ],
            [
                'label' => 'Purchase Orders',
                'url' => route('purchasing.orders.index'),
            ],
            [
                'label' => $purchaseOrderTitle,
                'url' => null,
            ],
        ];
    @endphp

    <x-slot name="header">
        <x-resource-detail-header-breadcrumb
            :items="$breadcrumbItems"
            :title="$purchaseOrderTitle"
        >
            @can('purchasing-purchase-orders-receive')
                @if (($payload['workflow']['actions'] ?? []) !== [])
                <x-slot name="actions">
                    <div data-purchase-order-action-button>
                        <x-workflow-action-button
                            :workflow="$payload['workflow']"
                            :csrf-token="$payload['csrfToken']"
                        />
                    </div>
                </x-slot>
                @endif
            @endcan
        </x-resource-detail-header-breadcrumb>
    </x-slot>

    <script type="application/json" id="purchasing-orders-show-payload">@json($payload)</script>

    <div class="py-8 sm:py-12">
        <div class="fixed right-6 top-6 z-50" x-show="toast.visible">
            <div
                class="rounded-md px-4 py-3 text-sm shadow-md"
                :class="toast.type === 'success' ? 'bg-green-50 text-green-700' : 'bg-red-50 text-red-700'"
                x-text="toast.message"
            ></div>
        </div>

        <div class="mx-auto max-w-5xl space-y-4 px-1 sm:space-y-6 sm:px-6 lg:px-8" data-purchase-order-detail-content>
            <x-detail-section-card title="Details" :default-open="true">
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
                    <div class="max-w-2xl sm:col-span-2">
                        <label class="block text-xs font-semibold uppercase text-gray-500">
                            Notes
                            <span class="mt-2 flex items-start gap-2">
                                <textarea rows="3" class="w-full rounded border-gray-300 text-sm shadow-sm focus:border-blue-500 focus:ring-blue-500" x-model="form.notes" :disabled="!isEditable" x-on:blur="autosaveField('notes')"></textarea>
                                <svg data-autosave-success-icon class="mt-2 h-5 w-5 shrink-0 text-lime-400" x-show="savedFields.notes" x-cloak xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" aria-hidden="true">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75 11.25 15 15 9.75" />
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z" />
                                </svg>
                            </span>
                        </label>
                        <p class="mt-1 text-xs text-red-600" x-text="headerErrors.notes[0]"></p>
                    </div>
                </div>
                <p class="mt-3 text-xs text-red-600" x-text="headerError"></p>
            </x-detail-section-card>

            <x-detail-section-card title="Items" :default-open="true">
                <div class="space-y-4">
                    <div class="space-y-2" x-show="isEditable">
                        <div class="flex flex-col gap-3 sm:flex-row sm:items-center">
                            <div class="min-w-0 flex-1">
                                <x-combobox
                                    name="item_purchase_option_id"
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
                            <div class="flex justify-end sm:shrink-0">
                                <button
                                    type="button"
                                    class="inline-flex h-10 w-10 items-center justify-center rounded-lg border border-gray-300 text-gray-700 transition hover:bg-gray-50 disabled:cursor-not-allowed disabled:opacity-50"
                                    x-on:click="submitLine()"
                                    :disabled="isLineSubmitting || !form.supplier_id || !lineForm.item_purchase_option_id"
                                    :class="isLineSubmitting || !form.supplier_id || !lineForm.item_purchase_option_id ? 'cursor-not-allowed opacity-50' : ''"
                                    aria-label="Add supplier package"
                                >
                                    <svg class="h-5 w-5" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" aria-hidden="true">
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

                    <div class="overflow-x-auto" x-show="lines.length > 0">
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
                                                <input
                                                    type="text"
                                                    inputmode="numeric"
                                                    class="w-20 rounded-lg border border-gray-300 px-2 py-1.5 text-right text-sm shadow-sm focus:border-blue-500 focus:ring-blue-500 disabled:cursor-not-allowed disabled:bg-gray-100"
                                                    x-model="line.pack_count"
                                                    :disabled="!isEditable"
                                                    x-on:focus="$el.setSelectionRange($el.value.length, $el.value.length)"
                                                    x-on:blur="autosaveLineField(line, 'pack_count')"
                                                    x-on:change="autosaveLineField(line, 'pack_count')"
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
                                                <input
                                                    type="text"
                                                    inputmode="decimal"
                                                    class="w-20 rounded-lg border border-gray-300 px-2 py-1.5 text-right text-sm shadow-sm focus:border-blue-500 focus:ring-blue-500 disabled:cursor-not-allowed disabled:bg-gray-100"
                                                    x-model="line.tax_percent"
                                                    :disabled="!isEditable"
                                                    x-on:focus="$el.setSelectionRange($el.value.length, $el.value.length)"
                                                    x-on:blur="autosaveLineField(line, 'tax_percent')"
                                                    x-on:change="autosaveLineField(line, 'tax_percent')"
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
                                <input type="text" inputmode="numeric" class="mt-1 w-full rounded border-gray-300 text-right text-sm shadow-sm focus:border-blue-500 focus:ring-blue-500" x-model="editForm.pack_count" x-on:focus="$el.setSelectionRange($el.value.length, $el.value.length)" />
                                <span class="mt-1 block text-xs text-red-600" x-text="editErrors.pack_count[0]"></span>
                            </label>
                            <label class="block text-xs font-semibold uppercase text-gray-500">
                                Tax %
                                <input type="text" inputmode="decimal" class="mt-1 w-full rounded border-gray-300 text-right text-sm shadow-sm focus:border-blue-500 focus:ring-blue-500" x-model="editForm.tax_percent" x-on:focus="$el.setSelectionRange($el.value.length, $el.value.length)" />
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

            <x-detail-section-card title="Totals" :default-open="true">
                <dl class="divide-y divide-gray-100 text-sm">
                    <div class="flex items-center justify-between py-3">
                        <dt class="text-gray-600">Subtotal</dt>
                        <dd class="font-medium text-gray-900" x-text="formatMoney(purchaseOrder.po_subtotal_cents)"></dd>
                    </div>
                    <div class="flex items-center justify-between py-3">
                        <dt class="text-gray-600">Shipping</dt>
                        <dd class="flex items-center gap-2 font-medium text-gray-900">
                            <input
                                type="text"
                                inputmode="decimal"
                                class="w-28 rounded border-gray-300 text-right text-sm shadow-sm focus:border-blue-500 focus:ring-blue-500"
                                x-model="form.shipping_amount"
                                :disabled="!isEditable"
                                x-on:blur="autosaveField('shipping_amount')"
                                x-on:change="autosaveField('shipping_amount')"
                            />
                            <svg data-autosave-success-icon class="h-5 w-5 shrink-0 text-lime-400" x-show="savedFields.shipping_amount" x-cloak xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" aria-hidden="true">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75 11.25 15 15 9.75" />
                                <path stroke-linecap="round" stroke-linejoin="round" d="M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z" />
                            </svg>
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
            <div class="relative z-50 flex h-full w-full max-w-3xl flex-col bg-white shadow-xl">
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
                                            <input
                                                type="number"
                                                min="0"
                                                step="1"
                                                inputmode="numeric"
                                                class="mt-1 w-full rounded border-gray-300 text-sm shadow-sm focus:border-blue-500 focus:ring-blue-500"
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
                            type="button"
                            class="inline-flex items-center px-4 py-2 bg-blue-600 border border-transparent rounded-md text-xs font-semibold text-white uppercase tracking-widest hover:bg-blue-500"
                            x-on:click="submitReceive()"
                            :disabled="isReceiveSubmitting"
                            :class="isReceiveSubmitting ? 'opacity-50 cursor-not-allowed' : ''"
                        >
                            Receive
                        </button>
                    </div>
                </div>
            </div>
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
                                <input
                                    type="number"
                                    min="0"
                                    step="0.000001"
                                    class="mt-1 w-full rounded border-gray-300 text-sm shadow-sm focus:border-blue-500 focus:ring-blue-500"
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
    </div>
</x-resource-detail-layout>
