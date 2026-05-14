<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            {{ __('Sales Order #:id', ['id' => $salesOrder->id]) }}
        </h2>
    </x-slot>

    <script type="application/json" id="sales-orders-show-payload">@json($payload)</script>

    <div
        class="py-12"
        data-page="sales-orders-show"
        data-payload="sales-orders-show-payload"
        x-data="salesOrdersShow"
    >
        <div class="fixed top-6 right-6 z-50" x-show="toast.visible">
            <div
                class="rounded-md px-4 py-3 text-sm shadow-md"
                :class="toast.type === 'success' ? 'bg-green-50 text-green-700' : 'bg-red-50 text-red-700'"
                x-text="toast.message"
            ></div>
        </div>

        <div class="max-w-6xl mx-auto space-y-6 sm:px-6 lg:px-8">
            <div class="bg-white border border-gray-100 shadow-sm sm:rounded-lg">
                <div class="p-6">
                    <div class="flex items-start justify-between gap-4">
                        <div>
                            <p class="text-sm text-gray-500">Status</p>
                            <p class="mt-1 text-lg font-semibold text-gray-900" x-text="order.status"></p>
                        </div>

                        <a class="text-sm font-medium text-blue-600 hover:text-blue-500" :href="indexUrl">
                            Back to orders
                        </a>
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

                    <div class="mt-6 flex flex-wrap gap-2" x-show="canChangeStatus(order)">
                        <template x-for="status in order.available_status_transitions" :key="`${order.id}-${status}`">
                            <button
                                type="button"
                                class="inline-flex items-center rounded-md border border-gray-300 px-3 py-1.5 text-xs font-semibold uppercase tracking-widest text-gray-700 hover:bg-gray-50"
                                x-on:click="submitStatus(status)"
                                x-text="status"
                            ></button>
                        </template>
                    </div>

                    <div class="mt-6 rounded-xl border border-gray-200 bg-gray-50 p-4" x-show="(order.current_stage_tasks || []).length > 0">
                        <p class="text-[11px] font-semibold uppercase tracking-[0.2em] text-gray-500">Checklist</p>
                        <div class="mt-3 space-y-2">
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
                        <p class="text-sm text-gray-500" x-text="`${order.line_count || 0} line(s)`"></p>
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

                    <div class="mt-6 rounded-lg border border-gray-200 p-4" x-show="sellableItems.length > 0 && canManageOrderLines()">
                        <div class="grid gap-3 sm:grid-cols-[minmax(0,1fr)_140px_auto]">
                            <div>
                                <select
                                    class="block w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-blue-500 focus:ring-blue-500"
                                    x-model="lineForm.item_id"
                                >
                                    <option value="">Select item</option>
                                    <template x-for="item in sellableItems" :key="item.id">
                                        <option :value="String(item.id)" x-text="item.name"></option>
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

                    <div class="mt-6 rounded-lg border border-dashed border-gray-300 p-4" x-show="!canManageOrderLines()">
                        <p class="text-xs text-gray-500">Line editing is unavailable once an order is completed or cancelled.</p>
                    </div>
                </div>
            </section>
        </div>
    </div>
</x-app-layout>
