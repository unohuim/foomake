<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            {{ __('Purchase Orders') }}
        </h2>
    </x-slot>

    @php
        $ordersPayload = $purchaseOrders->map(function ($purchaseOrder) {
            return [
                'id' => $purchaseOrder->id,
                'supplier_name' => $purchaseOrder->supplier?->company_name,
                'status' => $purchaseOrder->status,
                'order_date' => $purchaseOrder->order_date?->format('Y-m-d'),
                'po_number' => $purchaseOrder->po_number,
                'po_subtotal_cents' => $purchaseOrder->po_subtotal_cents,
                'po_grand_total_cents' => $purchaseOrder->po_grand_total_cents,
                'lines_count' => $purchaseOrder->lines_count ?? 0,
                'show_url' => route('purchasing.orders.show', $purchaseOrder),
            ];
        });

        $payload = [
            'orders' => $ordersPayload,
            'storeUrl' => route('purchasing.orders.store'),
            'csrfToken' => csrf_token(),
            'tenantCurrency' => $tenantCurrency,
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
                    <p class="mt-1 text-sm text-gray-600">Draft new orders, then track totals and supplier pricing snapshots.</p>
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

            <div class="mt-6 space-y-4" x-show="orders.length > 0">
                <template x-for="order in orders" :key="order.id">
                    <a
                        class="block rounded-lg border border-gray-100 bg-white px-5 py-4 shadow-sm transition hover:border-gray-200"
                        :href="order.show_url"
                    >
                        <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                            <div>
                                <p class="text-sm text-gray-500" x-text="order.po_number ? 'PO #' + order.po_number : 'Draft purchase order'"></p>
                                <h4 class="mt-1 text-lg font-medium text-gray-900" x-text="order.supplier_name || 'Supplier not set'"></h4>
                                <p class="mt-1 text-sm text-gray-500" x-text="order.order_date || 'No order date'"></p>
                            </div>
                            <div class="flex flex-wrap items-center gap-3 text-sm text-gray-600">
                                <span class="rounded-full bg-gray-100 px-3 py-1 text-xs font-semibold uppercase text-gray-600" x-text="order.status"></span>
                                <span class="text-base font-semibold text-gray-900" x-text="formatMoney(order.po_grand_total_cents)"></span>
                            </div>
                        </div>
                        <p class="mt-3 text-xs text-gray-500">
                            <span x-text="order.lines_count"></span>
                            <span x-text="order.lines_count === 1 ? 'line' : 'lines'"></span>
                        </p>
                    </a>
                </template>
            </div>
        </div>
    </div>
</x-app-layout>
