<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
            <div>
                <h2 class="font-semibold text-xl text-gray-800 leading-tight">
                    {{ __('Purchase Order') }}
                </h2>
                <p class="text-sm text-gray-500">ID #{{ $purchaseOrder->id }}</p>
            </div>
            <span class="inline-flex items-center rounded-full bg-gray-100 px-3 py-1 text-xs font-semibold uppercase text-gray-600">
                {{ $purchaseOrder->status }}
            </span>
        </div>
    </x-slot>

    <script type="application/json" id="purchasing-orders-show-payload">@json($payload)</script>

    <div
        class="py-10"
        data-page="purchasing-orders-show"
        data-payload="purchasing-orders-show-payload"
        x-data="purchasingOrdersShow"
    >
        <div class="fixed top-6 right-6 z-50" x-show="toast.visible">
            <div
                class="rounded-md px-4 py-3 text-sm shadow-md"
                :class="toast.type === 'success' ? 'bg-green-50 text-green-700' : 'bg-red-50 text-red-700'"
                x-text="toast.message"
            ></div>
        </div>

        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            <div class="grid gap-6 lg:grid-cols-3">
                <div class="lg:col-span-2 space-y-6">
                    <div class="rounded-lg border border-gray-100 bg-white shadow-sm">
                        <div class="border-b border-gray-100 px-6 py-4 flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                            <div>
                                <h3 class="text-lg font-medium text-gray-900">Lines</h3>
                                <p class="mt-1 text-sm text-gray-600">Add purchase option packs and confirm pricing snapshots.</p>
                            </div>
                            <button
                                type="button"
                                class="inline-flex items-center px-4 py-2 bg-blue-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-blue-500"
                                x-on:click="openReceive()"
                                x-show="canReceive && canReceiveOrder"
                            >
                                Receive
                            </button>
                        </div>
                        <div class="p-6 space-y-6">
                            <div class="space-y-4 rounded-lg border border-dashed border-gray-200 bg-gray-50 p-4" x-show="isEditable">
                                <div class="grid gap-4 sm:grid-cols-2">
                                    <div>
                                        <label class="block text-xs font-semibold uppercase text-gray-500">
                                            Item
                                            <select
                                                class="mt-1 w-full rounded border-gray-300 text-sm shadow-sm focus:border-blue-500 focus:ring-blue-500"
                                                x-model="lineForm.item_id"
                                                x-on:change="handleItemChange()"
                                                :disabled="!form.supplier_id"
                                            >
                                                <option value="">Select item</option>
                                                <template x-for="item in availableItems" :key="item.id">
                                                    <option :value="item.id" x-text="item.name"></option>
                                                </template>
                                            </select>
                                        </label>
                                        <p class="mt-1 text-xs text-red-600" x-text="lineErrors.item_id[0]"></p>
                                    </div>
                                    <div>
                                        <label class="block text-xs font-semibold uppercase text-gray-500">
                                            Pack option
                                            <select
                                                class="mt-1 w-full rounded border-gray-300 text-sm shadow-sm focus:border-blue-500 focus:ring-blue-500"
                                                x-model="lineForm.item_purchase_option_id"
                                                x-on:change="handleOptionChange()"
                                                :disabled="!form.supplier_id"
                                            >
                                                <option value="">Select pack</option>
                                                <template x-for="option in availableOptions" :key="option.id">
                                                    <option :value="option.id" x-text="option.label"></option>
                                                </template>
                                            </select>
                                        </label>
                                        <p class="mt-1 text-xs text-red-600" x-text="lineErrors.item_purchase_option_id[0]"></p>
                                    </div>
                                </div>
                                <div class="grid gap-4 sm:grid-cols-2">
                                    <div>
                                        <label class="block text-xs font-semibold uppercase text-gray-500">
                                            Unit price (cents)
                                            <input
                                                type="number"
                                                min="0"
                                                class="mt-1 w-full rounded border-gray-300 text-sm shadow-sm focus:border-blue-500 focus:ring-blue-500"
                                                x-model="lineForm.unit_price_cents"
                                                :disabled="!form.supplier_id"
                                            />
                                        </label>
                                        <p class="mt-1 text-xs text-red-600" x-text="lineErrors.unit_price_cents[0]"></p>
                                    </div>
                                    <div>
                                        <label class="block text-xs font-semibold uppercase text-gray-500">
                                            Pack count
                                            <input
                                                type="number"
                                                min="1"
                                                class="mt-1 w-full rounded border-gray-300 text-sm shadow-sm focus:border-blue-500 focus:ring-blue-500"
                                                x-model="lineForm.pack_count"
                                                :disabled="!form.supplier_id"
                                            />
                                        </label>
                                        <p class="mt-1 text-xs text-red-600" x-text="lineErrors.pack_count[0]"></p>
                                    </div>
                                </div>
                                <p class="text-xs text-red-600" x-text="lineErrors.supplier_id[0]"></p>
                                <p class="text-xs text-red-600" x-text="lineError"></p>
                                <div class="flex justify-end">
                                    <button
                                        type="button"
                                        class="inline-flex items-center px-4 py-2 bg-blue-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-blue-500"
                                        x-on:click="submitLine()"
                                        :disabled="isLineSubmitting || !form.supplier_id"
                                        :class="isLineSubmitting || !form.supplier_id ? 'opacity-50 cursor-not-allowed' : ''"
                                    >
                                        Add line
                                    </button>
                                </div>
                            </div>

                            <div class="rounded-lg border border-gray-100 p-4 text-sm text-gray-600" x-show="!isEditable">
                                This purchase order is locked and can no longer be edited.
                            </div>

                            <div class="space-y-4" x-show="lines.length > 0">
                                <template x-for="line in lines" :key="line.id">
                                    <div class="rounded-lg border border-gray-100 bg-white p-4 shadow-sm">
                                        <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
                                            <div>
                                                <p class="text-sm text-gray-500" x-text="lineLabel(line)"></p>
                                                <h4 class="mt-1 text-base font-semibold text-gray-900" x-text="line.item_name || 'Item'"></h4>
                                                <p class="mt-1 text-sm text-gray-600" x-text="lineSummary(line)"></p>
                                                <p class="mt-1 text-xs text-gray-500">
                                                    Received: <span x-text="formatQuantity(line.received_sum)"></span>
                                                    · Short-closed: <span x-text="formatQuantity(line.short_closed_sum)"></span>
                                                    · Remaining: <span x-text="formatQuantity(line.remaining_balance)"></span>
                                                </p>
                                            </div>
                                            <div class="flex items-center gap-3 text-sm text-gray-600" x-show="isEditable">
                                                <button
                                                    type="button"
                                                    class="text-blue-600 hover:text-blue-500"
                                                    x-on:click="openEditLine(line)"
                                                >
                                                    Edit
                                                </button>
                                                <button
                                                    type="button"
                                                    class="text-red-600 hover:text-red-500"
                                                    x-on:click="openDeleteLine(line)"
                                                >
                                                    Remove
                                                </button>
                                            </div>
                                            <div class="flex items-center gap-3 text-sm text-gray-600" x-show="!isEditable && canReceive">
                                                <button
                                                    type="button"
                                                    class="text-blue-600 hover:text-blue-500"
                                                    x-on:click="openReceiveLine(line)"
                                                    x-show="canReceiveLine(line)"
                                                >
                                                    Receive
                                                </button>
                                                <button
                                                    type="button"
                                                    class="text-yellow-600 hover:text-yellow-500"
                                                    x-on:click="openShortCloseLine(line)"
                                                    x-show="canShortCloseLine(line)"
                                                >
                                                    Short-Close
                                                </button>
                                            </div>
                                        </div>

                                        <div class="mt-4" x-show="editingLineId === line.id">
                                            <div class="grid gap-4 sm:grid-cols-2">
                                                <div>
                                                    <label class="block text-xs font-semibold uppercase text-gray-500">
                                                        Unit price (cents)
                                                        <input
                                                            type="number"
                                                            min="0"
                                                            class="mt-1 w-full rounded border-gray-300 text-sm shadow-sm focus:border-blue-500 focus:ring-blue-500"
                                                            x-model="editForm.unit_price_cents"
                                                        />
                                                    </label>
                                                    <p class="mt-1 text-xs text-red-600" x-text="editErrors.unit_price_cents[0]"></p>
                                                </div>
                                                <div>
                                                    <label class="block text-xs font-semibold uppercase text-gray-500">
                                                        Pack count
                                                        <input
                                                            type="number"
                                                            min="1"
                                                            class="mt-1 w-full rounded border-gray-300 text-sm shadow-sm focus:border-blue-500 focus:ring-blue-500"
                                                            x-model="editForm.pack_count"
                                                        />
                                                    </label>
                                                    <p class="mt-1 text-xs text-red-600" x-text="editErrors.pack_count[0]"></p>
                                                </div>
                                            </div>
                                            <div class="mt-3 flex justify-end gap-3">
                                                <button
                                                    type="button"
                                                    class="inline-flex items-center px-3 py-2 border border-gray-300 rounded-md text-xs font-semibold text-gray-700 uppercase tracking-widest hover:bg-gray-50"
                                                    x-on:click="closeEditLine()"
                                                >
                                                    Cancel
                                                </button>
                                                <button
                                                    type="button"
                                                    class="inline-flex items-center px-3 py-2 bg-blue-600 border border-transparent rounded-md text-xs font-semibold text-white uppercase tracking-widest hover:bg-blue-500"
                                                    x-on:click="submitEditLine(line)"
                                                    :disabled="isEditSubmitting"
                                                    :class="isEditSubmitting ? 'opacity-50 cursor-not-allowed' : ''"
                                                >
                                                    Save
                                                </button>
                                            </div>
                                        </div>
                                    </div>
                                </template>
                            </div>

                            <div class="rounded-lg border border-dashed border-gray-200 bg-gray-50 p-4 text-sm text-gray-600" x-show="lines.length === 0">
                                No lines yet. Add a purchase option pack to start pricing this order.
                            </div>
                        </div>
                    </div>

                    <div class="rounded-lg border border-gray-100 bg-white shadow-sm">
                        <div class="border-b border-gray-100 px-6 py-4">
                            <h3 class="text-lg font-medium text-gray-900">Receipt History</h3>
                        </div>
                        <div class="p-6">
                            <div class="overflow-x-auto" x-show="receipts.length > 0">
                                <table class="min-w-full divide-y divide-gray-100">
                                    <thead>
                                        <tr>
                                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Received At</th>
                                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Received By</th>
                                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Reference</th>
                                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Notes</th>
                                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Lines</th>
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
                        </div>
                    </div>

                    <div class="rounded-lg border border-gray-100 bg-white shadow-sm">
                        <div class="border-b border-gray-100 px-6 py-4">
                            <h3 class="text-lg font-medium text-gray-900">Short-Close History</h3>
                        </div>
                        <div class="p-6">
                            <div class="overflow-x-auto" x-show="shortClosures.length > 0">
                                <table class="min-w-full divide-y divide-gray-100">
                                    <thead>
                                        <tr>
                                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Short-Closed At</th>
                                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Short-Closed By</th>
                                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Reference</th>
                                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Notes</th>
                                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Lines</th>
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
                        </div>
                    </div>
                </div>

                <div class="space-y-6">
                    <div class="rounded-lg border border-gray-100 bg-white shadow-sm">
                        <div class="border-b border-gray-100 px-6 py-4">
                            <h3 class="text-lg font-medium text-gray-900">Header</h3>
                            <p class="mt-1 text-sm text-gray-600">Manage supplier, dates, and costs.</p>
                        </div>
                        <div class="p-6 space-y-4">
                            <div>
                                <label class="block text-xs font-semibold uppercase text-gray-500">
                                    Supplier
                                    <select
                                        class="mt-1 w-full rounded border-gray-300 text-sm shadow-sm focus:border-blue-500 focus:ring-blue-500"
                                        x-model="form.supplier_id"
                                        :disabled="!isEditable"
                                        x-on:change="handleSupplierChange()"
                                    >
                                        <option value="">Select supplier</option>
                                        <template x-for="supplier in suppliers" :key="supplier.id">
                                            <option :value="supplier.id" x-text="supplier.company_name"></option>
                                        </template>
                                    </select>
                                </label>
                                <p class="mt-1 text-xs text-red-600" x-text="headerErrors.supplier_id[0]"></p>
                            </div>
                            <div>
                                <label class="block text-xs font-semibold uppercase text-gray-500">
                                    Order date
                                    <input
                                        type="date"
                                        class="mt-1 w-full rounded border-gray-300 text-sm shadow-sm focus:border-blue-500 focus:ring-blue-500"
                                        x-model="form.order_date"
                                        :disabled="!isEditable"
                                    />
                                </label>
                                <p class="mt-1 text-xs text-red-600" x-text="headerErrors.order_date[0]"></p>
                            </div>
                            <div>
                                <label class="block text-xs font-semibold uppercase text-gray-500">
                                    PO number
                                    <input
                                        type="text"
                                        class="mt-1 w-full rounded border-gray-300 text-sm shadow-sm focus:border-blue-500 focus:ring-blue-500"
                                        x-model="form.po_number"
                                        :disabled="!isEditable"
                                    />
                                </label>
                                <p class="mt-1 text-xs text-red-600" x-text="headerErrors.po_number[0]"></p>
                            </div>
                            <div class="grid gap-4 sm:grid-cols-2">
                                <div>
                                    <label class="block text-xs font-semibold uppercase text-gray-500">
                                        Shipping (cents)
                                        <input
                                            type="number"
                                            min="0"
                                            class="mt-1 w-full rounded border-gray-300 text-sm shadow-sm focus:border-blue-500 focus:ring-blue-500"
                                            x-model="form.shipping_cents"
                                            :disabled="!isEditable"
                                        />
                                    </label>
                                    <p class="mt-1 text-xs text-red-600" x-text="headerErrors.shipping_cents[0]"></p>
                                </div>
                                <div>
                                    <label class="block text-xs font-semibold uppercase text-gray-500">
                                        Tax (cents)
                                        <input
                                            type="number"
                                            min="0"
                                            class="mt-1 w-full rounded border-gray-300 text-sm shadow-sm focus:border-blue-500 focus:ring-blue-500"
                                            x-model="form.tax_cents"
                                            :disabled="!isEditable"
                                        />
                                    </label>
                                    <p class="mt-1 text-xs text-red-600" x-text="headerErrors.tax_cents[0]"></p>
                                </div>
                            </div>
                            <div>
                                <label class="block text-xs font-semibold uppercase text-gray-500">
                                    Notes
                                    <textarea
                                        rows="3"
                                        class="mt-1 w-full rounded border-gray-300 text-sm shadow-sm focus:border-blue-500 focus:ring-blue-500"
                                        x-model="form.notes"
                                        :disabled="!isEditable"
                                    ></textarea>
                                </label>
                                <p class="mt-1 text-xs text-red-600" x-text="headerErrors.notes[0]"></p>
                            </div>
                            <p class="text-xs text-red-600" x-text="headerError"></p>
                            <div class="flex justify-end" x-show="isEditable">
                                <button
                                    type="button"
                                    class="inline-flex items-center px-4 py-2 bg-blue-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-blue-500"
                                    x-on:click="submitHeader()"
                                    :disabled="isHeaderSubmitting"
                                    :class="isHeaderSubmitting ? 'opacity-50 cursor-not-allowed' : ''"
                                >
                                    Save header
                                </button>
                            </div>
                        </div>
                    </div>

                    <div class="rounded-lg border border-gray-100 bg-white shadow-sm">
                        <div class="border-b border-gray-100 px-6 py-4">
                            <h3 class="text-lg font-medium text-gray-900">Status</h3>
                            <p class="mt-1 text-sm text-gray-600">Manage manual status transitions.</p>
                        </div>
                        <div class="p-6 space-y-3">
                            <p class="text-sm text-gray-600">Current: <span class="font-semibold" x-text="purchaseOrder.status"></span></p>
                            <div class="flex flex-wrap gap-2" x-show="canReceive">
                                <button
                                    type="button"
                                    class="inline-flex items-center px-3 py-2 border border-gray-300 rounded-md text-xs font-semibold text-gray-700 uppercase tracking-widest hover:bg-gray-50"
                                    x-on:click="submitStatus('OPEN')"
                                    x-show="canOpenOrder"
                                >
                                    Open
                                </button>
                                <button
                                    type="button"
                                    class="inline-flex items-center px-3 py-2 border border-gray-300 rounded-md text-xs font-semibold text-gray-700 uppercase tracking-widest hover:bg-gray-50"
                                    x-on:click="submitStatus('BACK-ORDERED')"
                                    x-show="canBackOrder"
                                >
                                    Back-Order
                                </button>
                                <button
                                    type="button"
                                    class="inline-flex items-center px-3 py-2 border border-red-200 rounded-md text-xs font-semibold text-red-600 uppercase tracking-widest hover:bg-red-50"
                                    x-on:click="submitStatus('CANCELLED')"
                                    x-show="canCancelOrder"
                                >
                                    Cancel
                                </button>
                            </div>
                            <p class="text-xs text-red-600" x-text="statusError"></p>
                        </div>
                    </div>

                    <div class="rounded-lg border border-gray-100 bg-white shadow-sm">
                        <div class="border-b border-gray-100 px-6 py-4">
                            <h3 class="text-lg font-medium text-gray-900">Totals</h3>
                        </div>
                        <div class="p-6 space-y-3 text-sm text-gray-700">
                            <div class="flex items-center justify-between">
                                <span>Subtotal</span>
                                <span x-text="formatMoney(purchaseOrder.po_subtotal_cents)"></span>
                            </div>
                            <div class="flex items-center justify-between">
                                <span>Shipping</span>
                                <span x-text="formatMoney(purchaseOrder.shipping_cents)"></span>
                            </div>
                            <div class="flex items-center justify-between">
                                <span>Tax</span>
                                <span x-text="formatMoney(purchaseOrder.tax_cents)"></span>
                            </div>
                            <div class="flex items-center justify-between border-t border-gray-100 pt-3 text-base font-semibold text-gray-900">
                                <span>Grand total</span>
                                <span x-text="formatMoney(purchaseOrder.po_grand_total_cents)"></span>
                            </div>
                        </div>
                    </div>

                    <div class="rounded-lg border border-gray-100 bg-white p-4 text-sm text-gray-600" x-show="isEditable">
                        Use the delete button below only when this draft is no longer needed.
                        <div class="mt-3">
                            <button
                                type="button"
                                class="inline-flex items-center px-3 py-2 border border-red-200 rounded-md text-xs font-semibold text-red-600 uppercase tracking-widest hover:bg-red-50"
                                x-on:click="openDeleteOrder()"
                            >
                                Delete draft
                            </button>
                        </div>
                    </div>
                </div>
            </div>
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
            class="fixed inset-0 z-50 flex items-center justify-center"
            x-show="isReceiveOpen"
            x-cloak
            x-on:keydown.escape.window="closeReceive()"
        >
            <div class="fixed inset-0 bg-gray-900/30" x-on:click="closeReceive()"></div>
            <div class="relative z-50 w-full max-w-2xl mx-4 bg-white rounded-lg shadow-xl">
                <div class="p-6">
                    <div class="flex items-start justify-between">
                        <div>
                            <h3 class="text-lg font-medium text-gray-900">Receive</h3>
                            <p class="mt-1 text-sm text-gray-600">Record received packs for this order.</p>
                        </div>
                        <button
                            type="button"
                            class="text-gray-400 hover:text-gray-600"
                            x-on:click="closeReceive()"
                            aria-label="Close"
                        >
                            ×
                        </button>
                    </div>

                    <div class="mt-6 space-y-4">
                        <div class="grid gap-4 sm:grid-cols-2">
                            <div>
                                <label class="block text-xs font-semibold uppercase text-gray-500">
                                    Received at
                                    <input
                                        type="datetime-local"
                                        class="mt-1 w-full rounded border-gray-300 text-sm shadow-sm focus:border-blue-500 focus:ring-blue-500"
                                        x-model="receiveForm.received_at"
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
                                <div class="rounded-md border border-gray-100 bg-gray-50 p-3">
                                    <div class="flex items-center justify-between text-sm text-gray-700">
                                        <span class="font-medium" x-text="line.item_name || 'Item'"></span>
                                        <span class="text-xs text-gray-500">Remaining: <span x-text="formatQuantity(line.remaining_balance)"></span></span>
                                    </div>
                                    <div class="mt-2">
                                        <label class="block text-xs font-semibold uppercase text-gray-500">
                                            Receive quantity
                                            <input
                                                type="number"
                                                min="0"
                                                step="0.000001"
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

                        <div class="mt-6 flex justify-end gap-3">
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
</x-app-layout>
