<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            {{ __('Customer: :name', ['name' => $customer->name]) }}
        </h2>
    </x-slot>

    <script type="application/json" id="sales-customers-show-payload">@json($payload)</script>

    <div
        class="py-12"
        data-page="sales-customers-show"
        data-payload="sales-customers-show-payload"
        x-data="salesCustomersShow"
    >
        <div class="fixed top-6 right-6 z-50" x-show="toast.visible">
            <div
                class="rounded-md px-4 py-3 text-sm shadow-md"
                :class="toast.type === 'success' ? 'bg-green-50 text-green-700' : 'bg-red-50 text-red-700'"
                x-text="toast.message"
            ></div>
        </div>

        <div class="max-w-4xl mx-auto space-y-6 sm:px-6 lg:px-8">
            <div class="bg-white border border-gray-100 shadow-sm sm:rounded-lg">
                <div class="p-6">
                    <div class="flex items-start justify-between">
                        <div>
                            <p class="text-sm text-gray-500">Status</p>
                            <p class="mt-1 text-lg font-semibold text-gray-900" x-text="customer.status"></p>
                        </div>

                        @if ($payload['canManage'])
                            <div class="inline-flex items-center gap-3">
                                <button type="button" class="text-blue-600 hover:text-blue-500" x-on:click="openEdit()">Edit</button>
                                <button type="button" class="text-yellow-600 hover:text-yellow-500" x-on:click="archive()">Archive</button>
                            </div>
                        @endif
                    </div>

                    <div class="mt-6 grid gap-6 sm:grid-cols-2">
                        <div>
                            <p class="text-sm text-gray-500">Name</p>
                            <p class="mt-1 text-base text-gray-900" x-text="customer.name"></p>
                        </div>
                        <div>
                            <p class="text-sm text-gray-500">Notes</p>
                            <p class="mt-1 text-base text-gray-900 whitespace-pre-line" x-text="customer.notes || '—'"></p>
                        </div>
                    </div>

                    <div class="mt-8 rounded-2xl border border-gray-200 bg-gray-50/70 p-6">
                        <div class="flex items-start justify-between gap-4">
                            <div>
                                <h3 class="text-sm font-semibold uppercase tracking-[0.2em] text-gray-500">Address</h3>
                                <p class="mt-1 text-sm text-gray-600">Stored customer address details.</p>
                            </div>
                            <p class="text-sm text-gray-500" x-show="customer.address_summary" x-text="customer.address_summary"></p>
                        </div>

                        <div class="mt-6 grid gap-6 sm:grid-cols-2">
                            <div>
                                <p class="text-sm text-gray-500">Address line 1</p>
                                <p class="mt-1 text-base text-gray-900" x-text="customer.address_line_1 || '—'"></p>
                            </div>
                            <div>
                                <p class="text-sm text-gray-500">Address line 2</p>
                                <p class="mt-1 text-base text-gray-900" x-text="customer.address_line_2 || '—'"></p>
                            </div>
                            <div>
                                <p class="text-sm text-gray-500">City</p>
                                <p class="mt-1 text-base text-gray-900" x-text="customer.city || '—'"></p>
                            </div>
                            <div>
                                <p class="text-sm text-gray-500">Region</p>
                                <p class="mt-1 text-base text-gray-900" x-text="customer.region || '—'"></p>
                            </div>
                            <div>
                                <p class="text-sm text-gray-500">Postal code</p>
                                <p class="mt-1 text-base text-gray-900" x-text="customer.postal_code || '—'"></p>
                            </div>
                            <div>
                                <p class="text-sm text-gray-500">Country code</p>
                                <p class="mt-1 text-base text-gray-900" x-text="customer.country_code || '—'"></p>
                            </div>
                            <div class="sm:col-span-2">
                                <p class="text-sm text-gray-500">Formatted address</p>
                                <p class="mt-1 whitespace-pre-line text-base text-gray-900" x-text="customer.formatted_address || '—'"></p>
                            </div>
                            <div>
                                <p class="text-sm text-gray-500">Latitude</p>
                                <p class="mt-1 text-base text-gray-900" x-text="customer.latitude || '—'"></p>
                            </div>
                            <div>
                                <p class="text-sm text-gray-500">Longitude</p>
                                <p class="mt-1 text-base text-gray-900" x-text="customer.longitude || '—'"></p>
                            </div>
                            <div>
                                <p class="text-sm text-gray-500">Address provider</p>
                                <p class="mt-1 text-base text-gray-900" x-text="customer.address_provider || '—'"></p>
                            </div>
                            <div>
                                <p class="text-sm text-gray-500">Provider ID</p>
                                <p class="mt-1 text-base text-gray-900" x-text="customer.address_provider_id || '—'"></p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <section class="bg-white border border-gray-100 shadow-sm sm:rounded-lg" data-section="customer-contacts">
                <div class="p-6">
                    <div class="flex items-start justify-between gap-4">
                        <div>
                            <h3 class="text-lg font-medium text-gray-900">Contacts</h3>
                            <p class="mt-1 text-sm text-gray-600">Manage contact people for this customer from the detail view.</p>
                        </div>

                        @if ($payload['canManage'])
                            <button
                                type="button"
                                class="inline-flex items-center rounded-md border border-transparent bg-blue-600 px-4 py-2 text-xs font-semibold uppercase tracking-widest text-white hover:bg-blue-500"
                                data-contact-action="create"
                                x-on:click="openContactCreate()"
                            >
                                Add Contact
                            </button>
                        @endif
                    </div>

                    <div class="mt-6 space-y-4" x-show="contacts.length > 0">
                        <template x-for="contact in contacts" :key="contact.id">
                            <div class="rounded-2xl border border-gray-200 bg-gray-50/70 p-4">
                                <div class="flex items-start justify-between gap-4">
                                    <div>
                                        <div class="flex items-center gap-3">
                                            <p class="text-base font-semibold text-gray-900" x-text="contact.full_name"></p>
                                            <span
                                                class="inline-flex rounded-full bg-blue-100 px-2.5 py-1 text-xs font-semibold uppercase tracking-wide text-blue-700"
                                                x-show="contact.is_primary"
                                            >
                                                Primary
                                            </span>
                                        </div>
                                        <p class="mt-2 text-sm text-gray-700" x-text="contact.email || '—'"></p>
                                        <p class="mt-1 text-sm text-gray-700" x-text="contact.phone || '—'"></p>
                                        <p class="mt-1 text-sm text-gray-700" x-text="contact.role || '—'"></p>
                                    </div>

                                    @if ($payload['canManage'])
                                        <div class="flex items-center gap-3">
                                            <button
                                                type="button"
                                                class="text-blue-600 hover:text-blue-500"
                                                data-contact-action="edit"
                                                x-on:click="openContactEdit(contact)"
                                            >
                                                Edit
                                            </button>
                                            <button
                                                type="button"
                                                class="text-red-600 hover:text-red-500"
                                                data-contact-action="delete"
                                                x-on:click="deleteContact(contact)"
                                            >
                                                Delete
                                            </button>
                                            <button
                                                type="button"
                                                class="text-gray-600 hover:text-gray-500"
                                                data-contact-action="set-primary"
                                                x-show="!contact.is_primary"
                                                x-on:click="setPrimary(contact)"
                                            >
                                                Set Primary
                                            </button>
                                        </div>
                                    @endif
                                </div>
                            </div>
                        </template>
                    </div>

                    <div class="mt-6 rounded-2xl border border-dashed border-gray-300 p-6 text-center text-sm text-gray-500" x-show="contacts.length === 0">
                        <p>No contacts yet.</p>
                    </div>
                </div>
            </section>

            @if ($payload['canManageOrders'])
                <section class="bg-white border border-gray-100 shadow-sm sm:rounded-lg" data-section="customer-orders">
                    <div class="p-6">
                        <div class="flex items-start justify-between gap-4">
                            <div>
                                <h3 class="text-lg font-medium text-gray-900">Orders</h3>
                                <p class="mt-1 text-sm text-gray-600">Manage sales orders for this customer without leaving the detail page.</p>
                            </div>

                            <button
                                type="button"
                                class="inline-flex items-center rounded-md border border-transparent bg-blue-600 px-4 py-2 text-xs font-semibold uppercase tracking-widest text-white hover:bg-blue-500 disabled:opacity-50"
                                x-bind:disabled="orderItems.length === 0"
                                x-bind:class="orderItems.length === 0 ? 'opacity-50 cursor-not-allowed' : ''"
                                x-on:click="openOrderCreate()"
                            >
                                Add Order
                            </button>
                        </div>

                        <div class="mt-6 space-y-4" x-show="orders.length > 0">
                            <template x-for="order in orders" :key="order.id">
                                <div class="rounded-2xl border border-gray-200 bg-gray-50/70 p-4">
                                    <div class="flex items-start justify-between gap-4">
                                        <div class="flex-1">
                                            <div class="flex items-center gap-3">
                                                <p class="text-base font-semibold text-gray-900" x-text="order.customer_name || customer.name"></p>
                                                <span class="inline-flex rounded-full bg-gray-100 px-2.5 py-1 text-xs font-semibold uppercase tracking-wide text-gray-700" x-text="order.status"></span>
                                            </div>
                                            <p class="mt-2 text-sm text-gray-700" x-text="order.contact_name || '—'"></p>
                                            <p class="mt-2 text-xs text-gray-500" x-text="`${order.line_count || 0} line(s) • ${formatOrderLineMoney(order.order_total_amount || '0.000000', order.lines)}`"></p>
                                            <div class="mt-3 flex flex-wrap gap-2" x-show="canChangeOrderStatus(order)">
                                                <template x-for="status in order.available_status_transitions" :key="`${order.id}-${status}`">
                                                    <button
                                                        type="button"
                                                        class="inline-flex items-center rounded-md border border-gray-300 bg-white px-2.5 py-1 text-[11px] font-semibold uppercase tracking-widest text-gray-700 hover:bg-gray-50"
                                                        x-on:click="submitOrderStatus(order, status)"
                                                        x-text="status"
                                                    ></button>
                                                </template>
                                            </div>

                                            <div class="mt-4 space-y-3" x-init="ensureOrderLineForm(order.id)">
                                                <div class="space-y-2" x-show="(order.lines || []).length > 0">
                                                    <template x-for="line in order.lines" :key="line.id">
                                                        <div class="rounded-lg border border-gray-200 bg-white p-3">
                                                            <div class="flex items-start justify-between gap-3">
                                                                <div>
                                                                    <p class="font-medium text-gray-900" x-text="line.item_name"></p>
                                                                    <p class="mt-1 text-xs text-gray-500" x-text="formatLineMoney(line.unit_price_amount, line.unit_price_currency_code)"></p>
                                                                    <p class="mt-1 text-xs text-gray-500" x-text="`Total: ${formatLineMoney(line.line_total_amount, line.unit_price_currency_code)}`"></p>
                                                                </div>
                                                                <button type="button" class="text-red-600 hover:text-red-500" x-show="canManageOrderLines(order)" x-on:click="deleteOrderLine(order, line)">Remove</button>
                                                            </div>

                                                            <div class="mt-3 flex items-start gap-2" x-show="canManageOrderLines(order)">
                                                                <div class="flex-1">
                                                                    <input
                                                                        type="text"
                                                                        class="block w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-blue-500 focus:ring-blue-500"
                                                                        x-model="orderLineEditQuantities[line.id]"
                                                                    />
                                                                    <p class="mt-1 text-xs text-red-600" x-text="(orderLineEditErrorsByLine[line.id] || {}).quantity?.[0]"></p>
                                                                </div>
                                                                <button type="button" class="inline-flex items-center rounded-md border border-gray-300 px-3 py-2 text-xs font-semibold uppercase tracking-widest text-gray-700 hover:bg-gray-50" x-on:click="saveOrderLineQuantity(order, line)">
                                                                    Save
                                                                </button>
                                                            </div>
                                                        </div>
                                                    </template>
                                                </div>

                                                <div class="rounded-lg border border-dashed border-gray-300 p-3" x-show="(order.lines || []).length === 0">
                                                    <p class="text-xs text-gray-500">No lines yet.</p>
                                                </div>

                                                <div class="rounded-lg border border-gray-200 bg-white p-3" x-show="orderItems.length > 0 && canManageOrderLines(order)">
                                                    <div class="grid gap-2 sm:grid-cols-[minmax(0,1fr)_140px_auto]">
                                                        <div>
                                                            <select
                                                                class="block w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-blue-500 focus:ring-blue-500"
                                                                x-model="orderLineForms[order.id].item_id"
                                                            >
                                                                <option value="">Select item</option>
                                                                <template x-for="item in orderItems" :key="item.id">
                                                                    <option :value="String(item.id)" x-text="item.name"></option>
                                                                </template>
                                                            </select>
                                                            <p class="mt-1 text-xs text-red-600" x-text="(orderLineErrorsByOrder[order.id] || {}).item_id?.[0]"></p>
                                                        </div>
                                                        <div>
                                                            <input
                                                                type="text"
                                                                class="block w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-blue-500 focus:ring-blue-500"
                                                                x-model="orderLineForms[order.id].quantity"
                                                                placeholder="1.000000"
                                                            />
                                                            <p class="mt-1 text-xs text-red-600" x-text="(orderLineErrorsByOrder[order.id] || {}).quantity?.[0]"></p>
                                                        </div>
                                                        <button type="button" class="inline-flex items-center justify-center rounded-md border border-transparent bg-blue-600 px-3 py-2 text-xs font-semibold uppercase tracking-widest text-white hover:bg-blue-500" x-on:click="submitOrderLine(order)">
                                                            Add Line
                                                        </button>
                                                    </div>
                                                    <p class="mt-2 text-xs text-red-600" x-show="orderLineGeneralErrorsByOrder[order.id]" x-text="orderLineGeneralErrorsByOrder[order.id]"></p>
                                                </div>

                                                <div class="rounded-lg border border-dashed border-gray-300 p-3" x-show="!canManageOrderLines(order)">
                                                    <p class="text-xs text-gray-500">Line editing is unavailable once an order is completed or cancelled.</p>
                                                </div>

                                                <div class="rounded-lg border border-dashed border-gray-300 p-3" x-show="orderItems.length === 0">
                                                    <p class="text-xs text-gray-500">Create a sellable item before adding sales order lines.</p>
                                                </div>
                                            </div>
                                        </div>

                                        <div class="flex items-center gap-3">
                                            <button
                                                type="button"
                                                class="text-blue-600 hover:text-blue-500"
                                                x-show="canEditOrder(order)"
                                                x-on:click="openOrderEdit(order)"
                                            >
                                                Edit
                                            </button>
                                            <button
                                                type="button"
                                                class="text-red-600 hover:text-red-500"
                                                x-on:click="deleteOrder(order)"
                                            >
                                                Delete
                                            </button>
                                        </div>
                                    </div>
                                </div>
                            </template>
                        </div>

                        <div class="mt-6 rounded-2xl border border-dashed border-gray-300 p-6 text-center text-sm text-gray-500" x-show="orders.length === 0">
                            <p>No orders for this customer yet.</p>
                        </div>
                    </div>
                </section>
            @endif

            @if ($payload['canManage'])
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
                                                <h2 class="text-lg font-medium text-gray-900">Edit customer</h2>
                                                <p class="mt-1 text-sm text-gray-600">Update the customer details.</p>
                                            </div>
                                            <button type="button" class="rounded-md text-gray-400 hover:text-gray-500" x-on:click="closeForm()">
                                                <span class="sr-only">Close panel</span>
                                                ✕
                                            </button>
                                        </div>

                                        <div class="mt-6 space-y-4">
                                            <div>
                                                <label class="block text-sm font-medium text-gray-700">
                                                    Name
                                                    <input
                                                        type="text"
                                                        class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500"
                                                        x-model="form.name"
                                                    />
                                                </label>
                                                <p class="mt-1 text-sm text-red-600" x-text="errors.name[0]"></p>
                                            </div>

                                            <div>
                                                <label class="block text-sm font-medium text-gray-700">
                                                    Status
                                                    <select
                                                        class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500"
                                                        x-model="form.status"
                                                    >
                                                        <template x-for="status in statuses" :key="status">
                                                            <option :value="status" x-text="status"></option>
                                                        </template>
                                                    </select>
                                                </label>
                                                <p class="mt-1 text-sm text-red-600" x-text="errors.status[0]"></p>
                                            </div>

                                            <div>
                                                <label class="block text-sm font-medium text-gray-700">
                                                    Notes
                                                    <textarea
                                                        class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500"
                                                        rows="4"
                                                        x-model="form.notes"
                                                    ></textarea>
                                                </label>
                                                <p class="mt-1 text-sm text-red-600" x-text="errors.notes[0]"></p>
                                            </div>

                                            <section class="rounded-2xl border border-gray-200 bg-gray-50/60 p-4">
                                                <div class="mb-4">
                                                    <h3 class="text-sm font-semibold uppercase tracking-[0.2em] text-gray-500">Address</h3>
                                                    <p class="mt-1 text-sm text-gray-600">Update the customer address fields used on the detail and list views.</p>
                                                </div>

                                                <div class="grid gap-4 sm:grid-cols-2">
                                                    <div class="sm:col-span-2">
                                                        <label class="block text-sm font-medium text-gray-700">
                                                            Address line 1
                                                            <input
                                                                type="text"
                                                                class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500"
                                                                x-model="form.address_line_1"
                                                            />
                                                        </label>
                                                        <p class="mt-1 text-sm text-red-600" x-text="errors.address_line_1[0]"></p>
                                                    </div>

                                                    <div class="sm:col-span-2">
                                                        <label class="block text-sm font-medium text-gray-700">
                                                            Address line 2
                                                            <input
                                                                type="text"
                                                                class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500"
                                                                x-model="form.address_line_2"
                                                            />
                                                        </label>
                                                        <p class="mt-1 text-sm text-red-600" x-text="errors.address_line_2[0]"></p>
                                                    </div>

                                                    <div>
                                                        <label class="block text-sm font-medium text-gray-700">
                                                            City
                                                            <input
                                                                type="text"
                                                                class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500"
                                                                x-model="form.city"
                                                            />
                                                        </label>
                                                        <p class="mt-1 text-sm text-red-600" x-text="errors.city[0]"></p>
                                                    </div>

                                                    <div>
                                                        <label class="block text-sm font-medium text-gray-700">
                                                            Region
                                                            <input
                                                                type="text"
                                                                class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500"
                                                                x-model="form.region"
                                                            />
                                                        </label>
                                                        <p class="mt-1 text-sm text-red-600" x-text="errors.region[0]"></p>
                                                    </div>

                                                    <div>
                                                        <label class="block text-sm font-medium text-gray-700">
                                                            Postal code
                                                            <input
                                                                type="text"
                                                                class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500"
                                                                x-model="form.postal_code"
                                                            />
                                                        </label>
                                                        <p class="mt-1 text-sm text-red-600" x-text="errors.postal_code[0]"></p>
                                                    </div>

                                                    <div>
                                                        <label class="block text-sm font-medium text-gray-700">
                                                            Country code
                                                            <input
                                                                type="text"
                                                                maxlength="2"
                                                                class="mt-1 block w-full rounded-md border-gray-300 uppercase shadow-sm focus:border-blue-500 focus:ring-blue-500"
                                                                x-model="form.country_code"
                                                            />
                                                        </label>
                                                        <p class="mt-1 text-sm text-red-600" x-text="errors.country_code[0]"></p>
                                                    </div>

                                                    <div class="sm:col-span-2">
                                                        <label class="block text-sm font-medium text-gray-700">
                                                            Formatted address
                                                            <textarea
                                                                class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500"
                                                                rows="3"
                                                                x-model="form.formatted_address"
                                                            ></textarea>
                                                        </label>
                                                        <p class="mt-1 text-sm text-red-600" x-text="errors.formatted_address[0]"></p>
                                                    </div>
                                                </div>
                                            </section>
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

                <div
                    class="fixed inset-0 z-50 overflow-hidden"
                    x-show="isContactFormOpen"
                    x-cloak
                    role="dialog"
                    aria-modal="true"
                >
                    <div class="absolute inset-0 overflow-hidden">
                        <div
                            class="absolute inset-0 bg-gray-500 bg-opacity-25 transition-opacity"
                            x-show="isContactFormOpen"
                            x-on:click="closeContactForm()"
                        ></div>

                        <div class="pointer-events-none fixed inset-y-0 right-0 flex max-w-full pl-10">
                            <div class="pointer-events-auto w-screen max-w-md">
                                <form class="flex h-full flex-col bg-white shadow-xl" x-on:submit.prevent="submitContactForm()">
                                    <div class="flex-1 overflow-y-auto p-6">
                                        <div class="flex items-start justify-between">
                                            <div>
                                                <h2 class="text-lg font-medium text-gray-900" x-text="contactFormMode === 'create' ? 'Add contact' : 'Edit contact'"></h2>
                                                <p class="mt-1 text-sm text-gray-600">Manage the customer contact list without leaving the detail page.</p>
                                            </div>
                                            <button type="button" class="rounded-md text-gray-400 hover:text-gray-500" x-on:click="closeContactForm()">
                                                <span class="sr-only">Close panel</span>
                                                ✕
                                            </button>
                                        </div>

                                        <div class="mt-6 space-y-4">
                                            <div>
                                                <label class="block text-sm font-medium text-gray-700">
                                                    First name
                                                    <input
                                                        type="text"
                                                        class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500"
                                                        x-model="contactForm.first_name"
                                                    />
                                                </label>
                                                <p class="mt-1 text-sm text-red-600" x-text="contactErrors.first_name[0]"></p>
                                            </div>

                                            <div>
                                                <label class="block text-sm font-medium text-gray-700">
                                                    Last name
                                                    <input
                                                        type="text"
                                                        class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500"
                                                        x-model="contactForm.last_name"
                                                    />
                                                </label>
                                                <p class="mt-1 text-sm text-red-600" x-text="contactErrors.last_name[0]"></p>
                                            </div>

                                            <div>
                                                <label class="block text-sm font-medium text-gray-700">
                                                    Email
                                                    <input
                                                        type="email"
                                                        class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500"
                                                        x-model="contactForm.email"
                                                    />
                                                </label>
                                                <p class="mt-1 text-sm text-red-600" x-text="contactErrors.email[0]"></p>
                                            </div>

                                            <div>
                                                <label class="block text-sm font-medium text-gray-700">
                                                    Phone
                                                    <input
                                                        type="text"
                                                        class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500"
                                                        x-model="contactForm.phone"
                                                    />
                                                </label>
                                                <p class="mt-1 text-sm text-red-600" x-text="contactErrors.phone[0]"></p>
                                            </div>

                                            <div>
                                                <label class="block text-sm font-medium text-gray-700">
                                                    Role
                                                    <input
                                                        type="text"
                                                        class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500"
                                                        x-model="contactForm.role"
                                                    />
                                                </label>
                                                <p class="mt-1 text-sm text-red-600" x-text="contactErrors.role[0]"></p>
                                            </div>
                                        </div>

                                        <p class="mt-4 text-sm text-red-600" x-show="contactGeneralError" x-text="contactGeneralError"></p>
                                    </div>

                                    <div class="flex shrink-0 justify-end gap-3 border-t border-gray-200 px-6 py-4">
                                        <button
                                            type="button"
                                            class="inline-flex items-center rounded-md border border-gray-300 px-4 py-2 text-xs font-semibold uppercase tracking-widest text-gray-700 hover:bg-gray-50"
                                            x-on:click="closeContactForm()"
                                        >
                                            Cancel
                                        </button>
                                        <button
                                            type="submit"
                                            class="inline-flex items-center rounded-md border border-transparent bg-blue-600 px-4 py-2 text-xs font-semibold uppercase tracking-widest text-white hover:bg-blue-500"
                                            :disabled="isContactSubmitting"
                                            :class="isContactSubmitting ? 'opacity-50 cursor-not-allowed' : ''"
                                        >
                                            Save
                                        </button>
                                    </div>
                                </form>
                            </div>
                        </div>
                    </div>
                </div>
            @endif

            @if ($payload['canManageOrders'])
                <div
                    class="fixed inset-0 z-50 overflow-hidden"
                    x-show="isOrderFormOpen"
                    x-cloak
                    role="dialog"
                    aria-modal="true"
                >
                    <div class="absolute inset-0 overflow-hidden">
                        <div
                            class="absolute inset-0 bg-gray-500 bg-opacity-25 transition-opacity"
                            x-show="isOrderFormOpen"
                            x-on:click="closeOrderForm()"
                        ></div>

                        <div class="pointer-events-none fixed inset-y-0 right-0 flex max-w-full pl-10">
                            <div class="pointer-events-auto w-screen max-w-md">
                                <form class="flex h-full flex-col bg-white shadow-xl" x-on:submit.prevent="submitOrderForm()">
                                    <div class="flex-1 overflow-y-auto p-6">
                                        <div class="flex items-start justify-between">
                                            <div>
                                                <h2 class="text-lg font-medium text-gray-900" x-text="orderFormMode === 'create' ? 'Add order' : 'Edit order'"></h2>
                                                <p class="mt-1 text-sm text-gray-600">Manage sales orders from the customer detail page.</p>
                                            </div>
                                            <button type="button" class="rounded-md text-gray-400 hover:text-gray-500" x-on:click="closeOrderForm()">
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
                                                        x-model="orderForm.customer_id"
                                                        x-on:change="handleOrderCustomerChange()"
                                                    >
                                                        <option value="">Select customer</option>
                                                        <template x-for="entry in orderCustomers" :key="entry.id">
                                                            <option :value="String(entry.id)" x-text="entry.name"></option>
                                                        </template>
                                                    </select>
                                                </label>
                                                <p class="mt-1 text-sm text-red-600" x-text="orderErrors.customer_id[0]"></p>
                                            </div>

                                            <div>
                                                <label class="block text-sm font-medium text-gray-700">
                                                    Contact
                                                    <select
                                                        class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500"
                                                        x-model="orderForm.contact_id"
                                                    >
                                                        <option value="">No contact</option>
                                                        <template x-for="contact in selectedOrderCustomerContacts()" :key="contact.id">
                                                            <option :value="String(contact.id)" x-text="orderContactOptionLabel(contact)"></option>
                                                        </template>
                                                    </select>
                                                </label>
                                                <p class="mt-1 text-sm text-red-600" x-text="orderErrors.contact_id[0]"></p>
                                            </div>
                                        </div>

                                        <p class="mt-4 text-sm text-red-600" x-show="orderGeneralError" x-text="orderGeneralError"></p>
                                    </div>

                                    <div class="flex shrink-0 justify-end gap-3 border-t border-gray-200 px-6 py-4">
                                        <button
                                            type="button"
                                            class="inline-flex items-center rounded-md border border-gray-300 px-4 py-2 text-xs font-semibold uppercase tracking-widest text-gray-700 hover:bg-gray-50"
                                            x-on:click="closeOrderForm()"
                                        >
                                            Cancel
                                        </button>
                                        <button
                                            type="submit"
                                            class="inline-flex items-center rounded-md border border-transparent bg-blue-600 px-4 py-2 text-xs font-semibold uppercase tracking-widest text-white hover:bg-blue-500"
                                            :disabled="isOrderSubmitting"
                                            :class="isOrderSubmitting ? 'opacity-50 cursor-not-allowed' : ''"
                                        >
                                            Save
                                        </button>
                                    </div>
                                </form>
                            </div>
                        </div>
                    </div>
                </div>
            @endif
        </div>
    </div>
</x-app-layout>
