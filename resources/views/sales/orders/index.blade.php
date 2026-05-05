<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            {{ __('Sales Orders') }}
        </h2>
    </x-slot>

    <script type="application/json" id="sales-orders-index-payload">@json($payload)</script>

    <div
        class="py-12"
        data-page="sales-orders-index"
        data-payload="sales-orders-index-payload"
        x-data="salesOrdersIndex"
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
                    <h3 class="text-lg font-medium text-gray-900">Sales orders</h3>
                    <p class="mt-1 text-sm text-gray-600">Manage sales orders and lifecycle changes without leaving the page.</p>
                </div>

                <button
                    type="button"
                    class="inline-flex items-center justify-center rounded-md border border-transparent bg-blue-600 px-4 py-2 text-xs font-semibold uppercase tracking-widest text-white hover:bg-blue-500"
                    x-on:click="openCreate()"
                >
                    Create Sales Order
                </button>
            </div>

            <div class="mt-8" x-cloak x-show="orders.length === 0">
                <div class="rounded-lg border border-gray-100 bg-white shadow-sm">
                    <div class="p-6">
                        <h3 class="text-lg font-medium text-gray-900">No sales orders yet</h3>
                        <p class="mt-2 text-sm text-gray-600">Create a sales order to assign a customer and manage its lifecycle.</p>
                        <div class="mt-4">
                            <button
                                type="button"
                                class="inline-flex items-center rounded-md border border-transparent bg-blue-600 px-4 py-2 text-xs font-semibold uppercase tracking-widest text-white hover:bg-blue-500"
                                x-on:click="openCreate()"
                            >
                                Create Sales Order
                            </button>
                        </div>
                    </div>
                </div>
            </div>

            <div class="mt-6" x-show="orders.length > 0">
                <div class="rounded-lg border border-gray-100 bg-white shadow-sm">
                    <div class="p-6">
                        <div class="overflow-x-auto">
                            <table class="min-w-full divide-y divide-gray-100">
                                <thead>
                                    <tr>
                                        <th class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">Customer</th>
                                        <th class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">Contact</th>
                                        <th class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">Status</th>
                                        <th class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">Lines</th>
                                        <th class="px-4 py-3 text-right text-xs font-medium uppercase tracking-wider text-gray-500">Actions</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-gray-100">
                                    <template x-for="order in orders" :key="order.id">
                                        <tr>
                                            <td class="px-4 py-4 text-sm text-gray-900" x-text="order.customer_name || '—'"></td>
                                            <td class="px-4 py-4 text-sm text-gray-700" x-text="order.contact_name || '—'"></td>
                                            <td class="px-4 py-4 text-sm">
                                                <div class="space-y-2">
                                                    <span class="rounded-full bg-gray-100 px-3 py-1 text-xs font-semibold uppercase text-gray-600" x-text="order.status"></span>
                                                    <div class="flex flex-wrap gap-2" x-show="canChangeStatus(order)">
                                                        <template x-for="status in order.available_status_transitions" :key="`${order.id}-${status}`">
                                                            <button
                                                                type="button"
                                                                class="inline-flex items-center rounded-md border border-gray-300 px-2.5 py-1 text-[11px] font-semibold uppercase tracking-widest text-gray-700 hover:bg-gray-50"
                                                                x-on:click="submitStatus(order, status)"
                                                                x-text="status"
                                                            ></button>
                                                        </template>
                                                    </div>
                                                </div>
                                            </td>
                                            <td class="px-4 py-4 text-sm text-gray-700 align-top">
                                                <div class="space-y-3" x-init="ensureLineForm(order.id)">
                                                    <div class="text-xs text-gray-500">
                                                        <span x-text="`${order.line_count || 0} line(s)`"></span>
                                                        <span class="mx-1">•</span>
                                                        <span x-text="formatLineMoney(order.order_total_amount || '0.000000', (order.lines[0] && order.lines[0].unit_price_currency_code) || 'USD')"></span>
                                                    </div>

                                                    <div class="space-y-2" x-show="(order.lines || []).length > 0">
                                                        <template x-for="line in order.lines" :key="line.id">
                                                            <div class="rounded-lg border border-gray-200 bg-gray-50 p-3">
                                                                <div class="flex items-start justify-between gap-3">
                                                                    <div>
                                                                        <p class="font-medium text-gray-900" x-text="line.item_name"></p>
                                                                        <p class="mt-1 text-xs text-gray-500" x-text="formatLineMoney(line.unit_price_amount, line.unit_price_currency_code)"></p>
                                                                        <p class="mt-1 text-xs text-gray-500" x-text="`Total: ${formatLineMoney(line.line_total_amount, line.unit_price_currency_code)}`"></p>
                                                                    </div>
                                                                    <button type="button" class="text-red-600 hover:text-red-500" x-show="canManageOrderLines(order)" x-on:click="deleteLine(order, line)">Remove</button>
                                                                </div>

                                                                <div class="mt-3 flex items-start gap-2" x-show="canManageOrderLines(order)">
                                                                    <div class="flex-1">
                                                                        <input
                                                                            type="text"
                                                                            class="block w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-blue-500 focus:ring-blue-500"
                                                                            x-model="lineEditQuantities[line.id]"
                                                                        />
                                                                        <p class="mt-1 text-xs text-red-600" x-text="(lineEditErrorsByLine[line.id] || {}).quantity?.[0]"></p>
                                                                    </div>
                                                                    <button type="button" class="inline-flex items-center rounded-md border border-gray-300 px-3 py-2 text-xs font-semibold uppercase tracking-widest text-gray-700 hover:bg-gray-50" x-on:click="saveLineQuantity(order, line)">
                                                                        Save
                                                                    </button>
                                                                </div>
                                                            </div>
                                                        </template>
                                                    </div>

                                                    <div class="rounded-lg border border-dashed border-gray-300 p-3" x-show="(order.lines || []).length === 0">
                                                        <p class="text-xs text-gray-500">No lines yet.</p>
                                                    </div>

                                                    <div class="rounded-lg border border-gray-200 p-3" x-show="sellableItems.length > 0 && canManageOrderLines(order)">
                                                        <div class="grid gap-2 sm:grid-cols-[minmax(0,1fr)_140px_auto]">
                                                            <div>
                                                                <select
                                                                    class="block w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-blue-500 focus:ring-blue-500"
                                                                    x-model="lineForms[order.id].item_id"
                                                                >
                                                                    <option value="">Select item</option>
                                                                    <template x-for="item in sellableItems" :key="item.id">
                                                                        <option :value="String(item.id)" x-text="item.name"></option>
                                                                    </template>
                                                                </select>
                                                                <p class="mt-1 text-xs text-red-600" x-text="(lineErrorsByOrder[order.id] || {}).item_id?.[0]"></p>
                                                            </div>
                                                            <div>
                                                                <input
                                                                    type="text"
                                                                    class="block w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-blue-500 focus:ring-blue-500"
                                                                    x-model="lineForms[order.id].quantity"
                                                                    placeholder="1.000000"
                                                                />
                                                                <p class="mt-1 text-xs text-red-600" x-text="(lineErrorsByOrder[order.id] || {}).quantity?.[0]"></p>
                                                            </div>
                                                            <button type="button" class="inline-flex items-center justify-center rounded-md border border-transparent bg-blue-600 px-3 py-2 text-xs font-semibold uppercase tracking-widest text-white hover:bg-blue-500" x-on:click="submitLine(order)">
                                                                Add Line
                                                            </button>
                                                        </div>
                                                        <p class="mt-2 text-xs text-red-600" x-show="lineGeneralErrorsByOrder[order.id]" x-text="lineGeneralErrorsByOrder[order.id]"></p>
                                                    </div>

                                                    <div class="rounded-lg border border-dashed border-gray-300 p-3" x-show="!canManageOrderLines(order)">
                                                        <p class="text-xs text-gray-500">Line editing is unavailable once an order is completed or cancelled.</p>
                                                    </div>

                                                    <div class="rounded-lg border border-dashed border-gray-300 p-3" x-show="sellableItems.length === 0">
                                                        <p class="text-xs text-gray-500">Create a sellable item before adding sales order lines.</p>
                                                    </div>
                                                </div>
                                            </td>
                                            <td class="px-4 py-4 text-right text-sm">
                                                <div class="inline-flex items-center gap-3">
                                                    <button type="button" class="text-blue-600 hover:text-blue-500" x-show="canEditOrder(order)" x-on:click="openEdit(order)">Edit</button>
                                                    <button type="button" class="text-red-600 hover:text-red-500" x-on:click="deleteOrder(order)">Delete</button>
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

            <div
                class="fixed inset-0 z-50 overflow-hidden"
                x-show="isFormOpen"
                x-cloak
                role="dialog"
                aria-modal="true"
            >
                <div class="absolute inset-0 overflow-hidden">
                    <div
                        class="absolute inset-0 bg-gray-500 bg-opacity-25 transition-opacity"
                        x-show="isFormOpen"
                        x-on:click="closeForm()"
                    ></div>

                    <div class="pointer-events-none fixed inset-y-0 right-0 flex max-w-full pl-10">
                        <div class="pointer-events-auto w-screen max-w-md">
                            <form class="flex h-full flex-col bg-white shadow-xl" x-on:submit.prevent="submitForm()">
                                <div class="flex-1 overflow-y-auto p-6">
                                    <div class="flex items-start justify-between">
                                        <div>
                                            <h2 class="text-lg font-medium text-gray-900" x-text="formMode === 'create' ? 'Create sales order' : 'Edit sales order'"></h2>
                                            <p class="mt-1 text-sm text-gray-600">Assign the customer and contact for this sales order.</p>
                                        </div>
                                        <button type="button" class="rounded-md text-gray-400 hover:text-gray-500" x-on:click="closeForm()">
                                            <span class="sr-only">Close panel</span>
                                            ✕
                                        </button>
                                    </div>

                                    <div class="mt-6 space-y-4">
                                        <div>
                                            <label class="block text-sm font-medium text-gray-700">
                                                Customer
                                                <select
                                                    class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500"
                                                    x-model="form.customer_id"
                                                    x-on:change="handleCustomerChange()"
                                                >
                                                    <option value="">Select customer</option>
                                                    <template x-for="customer in customers" :key="customer.id">
                                                        <option :value="String(customer.id)" x-text="customer.name"></option>
                                                    </template>
                                                </select>
                                            </label>
                                            <p class="mt-1 text-sm text-red-600" x-text="errors.customer_id[0]"></p>
                                        </div>

                                        <div>
                                            <label class="block text-sm font-medium text-gray-700">
                                                Contact
                                                <select
                                                    class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500"
                                                    x-model="form.contact_id"
                                                >
                                                    <option value="">No contact</option>
                                                    <template x-for="contact in selectedCustomerContacts()" :key="contact.id">
                                                        <option :value="String(contact.id)" x-text="contactOptionLabel(contact)"></option>
                                                    </template>
                                                </select>
                                            </label>
                                            <p class="mt-1 text-sm text-red-600" x-text="errors.contact_id[0]"></p>
                                        </div>
                                    </div>

                                    <p class="mt-4 text-sm text-red-600" x-show="generalError" x-text="generalError"></p>
                                </div>

                                <div class="flex shrink-0 justify-end gap-3 border-t border-gray-200 px-6 py-4">
                                    <button
                                        type="button"
                                        class="inline-flex items-center rounded-md border border-gray-300 px-4 py-2 text-xs font-semibold uppercase tracking-widest text-gray-700 hover:bg-gray-50"
                                        x-on:click="closeForm()"
                                    >
                                        Cancel
                                    </button>
                                    <button
                                        type="submit"
                                        class="inline-flex items-center rounded-md border border-transparent bg-blue-600 px-4 py-2 text-xs font-semibold uppercase tracking-widest text-white hover:bg-blue-500"
                                        :disabled="isSubmitting"
                                        :class="isSubmitting ? 'opacity-50 cursor-not-allowed' : ''"
                                    >
                                        Save
                                    </button>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
