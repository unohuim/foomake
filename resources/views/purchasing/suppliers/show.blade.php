<x-resource-detail-layout>
    @php
        $breadcrumbItems = [
            [
                'label' => 'Suppliers',
                'url' => route('purchasing.suppliers.index'),
                'current' => false,
            ],
            [
                'label' => $supplier->company_name,
                'url' => null,
                'current' => true,
            ],
        ];
    @endphp

    <x-slot name="header">
        <x-resource-detail-header-breadcrumb
            :items="$breadcrumbItems"
            :title="$supplier->company_name"
            title-class="font-semibold text-xl text-gray-800 leading-tight"
        />
    </x-slot>

    @php
        $payloadId = 'purchasing-suppliers-show-payload';
    @endphp

    <script type="application/json" id="{{ $payloadId }}">
        @json($payload)
    </script>

    <div
        data-page="purchasing-suppliers-show"
        data-payload="{{ $payloadId }}"
        x-data="purchasingSuppliersShow"
    >
        <div class="mx-auto max-w-5xl space-y-4 px-1 py-8 sm:space-y-6 sm:px-6 sm:py-12 lg:px-8">
            @if (! empty($payload['purchaseOrderCreate']))
                <div data-purchase-order-create-root></div>
            @endif

            <x-detail-section-card
                title="Details"
                :description="__('Update supplier contact and purchasing defaults.')"
                :default-open="false"
            >
                <div class="space-y-3">
                    <div class="grid gap-x-8 gap-y-3 sm:grid-cols-2">
                        <div class="space-y-1">
                            <p class="text-[10px] font-semibold uppercase tracking-wide text-gray-500 sm:text-xs">{{ __('Supplier name') }}</p>
                            <input
                                type="text"
                                class="block w-full max-w-sm rounded-xl border bg-white px-3 py-2 text-sm text-gray-900 shadow-sm transition focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-500/20 disabled:cursor-not-allowed disabled:bg-gray-100"
                                x-bind:class="detailsFieldClass('company_name')"
                                x-model="details.company_name"
                                x-bind:disabled="!supplier.can_manage || detailsSaving"
                                x-on:change="saveDetails('company_name')"
                                x-on:blur="saveDetails('company_name')"
                            />
                        </div>

                        <div class="space-y-1">
                            <p class="text-[10px] font-semibold uppercase tracking-wide text-gray-500 sm:text-xs">{{ __('Website') }}</p>
                            <input
                                type="url"
                                class="block w-full max-w-sm rounded-xl border bg-white px-3 py-2 text-sm text-gray-900 shadow-sm transition focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-500/20 disabled:cursor-not-allowed disabled:bg-gray-100"
                                x-bind:class="detailsFieldClass('url')"
                                x-model="details.url"
                                x-bind:disabled="!supplier.can_manage || detailsSaving"
                                x-on:change="saveDetails('url')"
                                x-on:blur="saveDetails('url')"
                            />
                        </div>

                        <div class="space-y-1">
                            <p class="text-[10px] font-semibold uppercase tracking-wide text-gray-500 sm:text-xs">{{ __('Phone') }}</p>
                            <input
                                type="text"
                                class="block w-full max-w-sm rounded-xl border bg-white px-3 py-2 text-sm text-gray-900 shadow-sm transition focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-500/20 disabled:cursor-not-allowed disabled:bg-gray-100"
                                x-bind:class="detailsFieldClass('phone')"
                                x-model="details.phone"
                                x-bind:disabled="!supplier.can_manage || detailsSaving"
                                x-on:change="saveDetails('phone')"
                                x-on:blur="saveDetails('phone')"
                            />
                        </div>

                        <div class="space-y-1">
                            <p class="text-[10px] font-semibold uppercase tracking-wide text-gray-500 sm:text-xs">{{ __('Email') }}</p>
                            <input
                                type="email"
                                class="block w-full max-w-sm rounded-xl border bg-white px-3 py-2 text-sm text-gray-900 shadow-sm transition focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-500/20 disabled:cursor-not-allowed disabled:bg-gray-100"
                                x-bind:class="detailsFieldClass('email')"
                                x-model="details.email"
                                x-bind:disabled="!supplier.can_manage || detailsSaving"
                                x-on:change="saveDetails('email')"
                                x-on:blur="saveDetails('email')"
                            />
                        </div>

                        <div class="space-y-1">
                            <p class="text-[10px] font-semibold uppercase tracking-wide text-gray-500 sm:text-xs">{{ __('Currency') }}</p>
                            <input
                                type="text"
                                maxlength="3"
                                class="block w-28 rounded-xl border bg-white px-3 py-2 text-sm uppercase text-gray-900 shadow-sm transition focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-500/20 disabled:cursor-not-allowed disabled:bg-gray-100"
                                x-bind:class="detailsFieldClass('currency_code')"
                                x-model="details.currency_code"
                                x-bind:disabled="!supplier.can_manage || detailsSaving"
                                x-on:change="saveDetails('currency_code')"
                                x-on:blur="saveDetails('currency_code')"
                            />
                        </div>
                    </div>

                    <p class="text-xs text-red-600" x-show="detailsError" x-text="detailsError"></p>
                </div>
            </x-detail-section-card>

            <div data-js-crud-section-root data-section-key="supplierPackages"></div>
            <div data-js-crud-section-root data-section-key="purchaseOrders"></div>
        </div>
    </div>
</x-resource-detail-layout>
