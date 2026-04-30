<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            {{ __('Purchase Orders') }}
        </h2>
    </x-slot>

    @php
        $ordersPayload = $purchaseOrders->map(function ($purchaseOrder) use ($tenantCurrency, $lineTotalsByOrder) {
            $lineTotals = $lineTotalsByOrder[$purchaseOrder->id] ?? [];

            $linesPayload = $purchaseOrder->lines->map(function ($line) use ($lineTotals) {
                $packCount = bcadd((string) $line->pack_count, '0', 6);
                $totals = $lineTotals[$line->id] ?? [];
                $packPrecision = (int) ($line->purchaseOption?->packUom?->display_precision ?? 1);

                return [
                    'id' => $line->id,
                    'item_name' => $line->item?->name,
                    'pack_count' => $packCount,
                    'pack_precision' => $packPrecision,
                    'pack_count_display' => \App\Support\QuantityFormatter::format($packCount, $packPrecision),
                    'received_sum' => $totals['received_sum'] ?? '0.000000',
                    'received_sum_display' => \App\Support\QuantityFormatter::format($totals['received_sum'] ?? '0.000000', $packPrecision),
                    'short_closed_sum' => $totals['short_closed_sum'] ?? '0.000000',
                    'short_closed_sum_display' => \App\Support\QuantityFormatter::format($totals['short_closed_sum'] ?? '0.000000', $packPrecision),
                    'remaining_balance' => $totals['balance'] ?? $packCount,
                    'remaining_balance_display' => \App\Support\QuantityFormatter::format($totals['balance'] ?? $packCount, $packPrecision),
                ];
            })->values()->all();

            return [
                'id' => $purchaseOrder->id,
                'supplier_name' => $purchaseOrder->supplier?->company_name,
                'status' => $purchaseOrder->status,
                'order_date' => $purchaseOrder->order_date?->format('Y-m-d'),
                'po_number' => $purchaseOrder->po_number,
                'po_subtotal_cents' => $purchaseOrder->po_subtotal_cents,
                'po_grand_total_cents' => $purchaseOrder->po_grand_total_cents,
                'lines_count' => $purchaseOrder->lines_count ?? 0,
                'lines' => $linesPayload,
                'show_url' => route('purchasing.orders.show', $purchaseOrder),
                'receipt_url' => route('purchasing.orders.receipts.store', $purchaseOrder),
                'status_url' => route('purchasing.orders.status.update', $purchaseOrder),
            ];
        });

        $payload = [
            'orders' => $ordersPayload,
            'storeUrl' => route('purchasing.orders.store'),
            'csrfToken' => csrf_token(),
            'tenantCurrency' => $tenantCurrency,
            'canReceive' => $canReceive,
        ];
    @endphp

    <script type="application/json" id="purchasing-orders-index-payload">@json($payload)</script>

    <div
        class="py-12"
        data-page="purchasing-orders-index"
        data-payload="purchasing-orders-index-payload"
        x-data="purchasingOrdersIndex"
    >
        <div class="fixed top-6 right-6 z-50" x-show="toast.visible">
            <div
                class="rounded-md px-4 py-3 text-sm shadow-md"
                :class="toast.type === 'success' ? 'bg-green-50 text-green-700' : 'bg-red-50 text-red-700'"
                x-text="toast.message"
            ></div>
        </div>

        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                <div>
                    <h3 class="text-lg font-medium text-gray-900">Purchase orders</h3>
                    <p class="mt-1 text-sm text-gray-600">Draft new orders, then track receipts and status changes.</p>
                </div>
                <button
                    type="button"
                    class="inline-flex items-center justify-center px-4 py-2 bg-blue-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2 transition ease-in-out duration-150"
                    x-on:click="createOrder()"
                    :disabled="isCreating"
                    :class="isCreating ? 'opacity-50 cursor-not-allowed' : ''"
                >
                    Create Purchase Order
                </button>
            </div>

            <div class="mt-8" x-cloak x-show="orders.length === 0">
                <div class="bg-white border border-gray-100 shadow-sm sm:rounded-lg">
                    <div class="p-6">
                        <h3 class="text-lg font-medium text-gray-900">No purchase orders yet</h3>
                        <p class="mt-2 text-sm text-gray-600">Create your first draft purchase order to capture pricing snapshots.</p>
                        <div class="mt-4">
                            <button
                                type="button"
                                class="inline-flex items-center px-4 py-2 bg-blue-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2 transition ease-in-out duration-150"
                                x-on:click="createOrder()"
                                :disabled="isCreating"
                                :class="isCreating ? 'opacity-50 cursor-not-allowed' : ''"
                            >
                                Create Purchase Order
                            </button>
                        </div>
                    </div>
                </div>
            </div>

            <div class="mt-6" x-show="orders.length > 0">
                <div class="bg-white border border-gray-100 shadow-sm sm:rounded-lg">
                    <div class="p-6">
                        <div class="overflow-x-auto">
                            <table class="min-w-full divide-y divide-gray-100">
                                <thead>
                                    <tr>
                                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                            Order
                                        </th>
                                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                            Supplier
                                        </th>
                                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                            Status
                                        </th>
                                        <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">
                                            Total
                                        </th>
                                        <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">
                                            Lines
                                        </th>
                                        <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">
                                            Actions
                                        </th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-gray-100">
                                    <template x-for="order in orders" :key="order.id">
                                        <tr>
                                            <td class="px-4 py-4 text-sm text-gray-900">
                                                <a
                                                    class="text-blue-600 hover:text-blue-500"
                                                    :href="order.show_url"
                                                >
                                                    <span x-text="order.po_number ? 'PO #' + order.po_number : 'Draft PO'">
                                                    </span>
                                                </a>
                                                <p class="mt-1 text-xs text-gray-500" x-text="order.order_date || 'No order date'"></p>
                                            </td>
                                            <td class="px-4 py-4 text-sm text-gray-700">
                                                <span x-text="order.supplier_name || 'Supplier not set'"></span>
                                            </td>
                                            <td class="px-4 py-4 text-sm">
                                                <span class="rounded-full bg-gray-100 px-3 py-1 text-xs font-semibold uppercase text-gray-600" x-text="order.status"></span>
                                            </td>
                                            <td class="px-4 py-4 text-right text-sm text-gray-700" x-text="formatMoney(order.po_grand_total_cents)"></td>
                                            <td class="px-4 py-4 text-right text-sm text-gray-700">
                                                <span x-text="order.lines_count"></span>
                                            </td>
                                            <td class="px-4 py-4 text-right text-sm">
                                                <div
                                                    class="relative inline-block text-left"
                                                    x-on:keydown.escape.window="closeActionMenu()"
                                                >
                                                    <button
                                                        type="button"
                                                        class="inline-flex items-center justify-center w-8 h-8 text-gray-500 hover:text-gray-700"
                                                        aria-label="Order actions"
                                                        x-on:click="toggleActionMenu($event, order.id)"
                                                    >
                                                        ⋮
                                                    </button>
                                                </div>
                                            </td>
                                        </tr>
                                    </template>
                                </tbody>
                            </table>
                        </div>
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
                            <p class="mt-1 text-sm text-gray-600" x-text="receiveOrderLabel"></p>
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
                                        <span class="text-xs text-gray-500">Remaining: <span x-text="line.remaining_balance_display"></span></span>
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

        <template x-teleport="body">
            <div
                x-show="actionMenuOpen"
                x-cloak
                x-on:click.outside="closeActionMenu()"
                x-transition
                class="fixed z-50 mt-2 w-44 rounded-md bg-white shadow-lg ring-1 ring-black ring-opacity-5"
                x-bind:style="'top:' + actionMenuTop + 'px; left:' + (actionMenuLeft - 176) + 'px;'"
            >
                <div class="py-1">
                    <button
                        type="button"
                        class="block w-full px-4 py-2 text-left text-sm text-gray-700 hover:bg-gray-100"
                        x-show="canReceive && canReceiveOrder(actionMenuOrder)"
                        x-on:click="openReceiveFromActionMenu()"
                    >
                        Receive
                    </button>
                    <button
                        type="button"
                        class="block w-full px-4 py-2 text-left text-sm text-gray-700 hover:bg-gray-100"
                        x-show="canOpenOrder(actionMenuOrder)"
                        x-on:click="submitStatusFromActionMenu('OPEN')"
                    >
                        Open
                    </button>
                    <button
                        type="button"
                        class="block w-full px-4 py-2 text-left text-sm text-gray-700 hover:bg-gray-100"
                        x-show="canBackOrder(actionMenuOrder)"
                        x-on:click="submitStatusFromActionMenu('BACK-ORDERED')"
                    >
                        Back-Order
                    </button>
                    <button
                        type="button"
                        class="block w-full px-4 py-2 text-left text-sm text-red-600 hover:bg-red-50"
                        x-show="canCancelOrder(actionMenuOrder)"
                        x-on:click="submitStatusFromActionMenu('CANCELLED')"
                    >
                        Cancel
                    </button>
                </div>
            </div>
        </template>
    </div>
</x-app-layout>
