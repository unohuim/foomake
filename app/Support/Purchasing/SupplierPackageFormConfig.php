<?php

namespace App\Support\Purchasing;

use App\Models\Item;
use App\Models\Supplier;
use App\Models\Uom;
use Illuminate\Http\Request;

/**
 * Build the shared supplier-package drawer form contract.
 */
class SupplierPackageFormConfig
{
    /**
     * Build the shared create slide-over copy and defaults.
     *
     * @param array<string, mixed> $prefill
     * @return array<string, mixed>
     */
    public function createAction(array $prefill = []): array
    {
        $action = [
            'type' => 'slide-over',
            'label' => 'Add Supplier Package',
            'title' => 'Supplier Package',
            'description' => 'Create a supplier pack to purchase.',
            'submitLabel' => 'Create',
        ];

        if ($prefill !== []) {
            $action['prefill'] = $prefill;
        }

        return $action;
    }

    /**
     * Build the material-detail form fields where the material is fixed.
     *
     * @return array<int, array<string, mixed>>
     */
    public function fieldsForMaterial(Request $request, bool $canCreateSupplier): array
    {
        return array_merge([
            $this->supplierField($request, $canCreateSupplier),
        ], $this->packageFields($request, [
            'currencyFromField' => 'supplier_id',
            'currencyOptionField' => 'currency_code',
        ]));
    }

    /**
     * Build the supplier-detail form fields where the supplier is fixed.
     *
     * @return array<int, array<string, mixed>>
     */
    public function fieldsForSupplier(Request $request, Supplier $supplier): array
    {
        return array_merge([
            $this->materialField($request),
        ], $this->packageFields($request, [
            'currency' => strtoupper((string) ($supplier->currency_code ?: $this->tenantCurrency($request))),
        ]));
    }

    /**
     * Build the supplier selector for material detail.
     *
     * @return array<string, mixed>
     */
    private function supplierField(Request $request, bool $canCreateSupplier): array
    {
        return [
            'name' => 'supplier_id',
            'label' => 'Supplier',
            'type' => 'combobox',
            'required' => true,
            'options' => Supplier::query()
                ->where('tenant_id', $request->user()->tenant_id)
                ->orderBy('company_name')
                ->get(['id', 'company_name', 'currency_code'])
                ->map(fn (Supplier $supplier): array => [
                    'value' => (string) $supplier->id,
                    'label' => $supplier->company_name,
                    'currency_code' => strtoupper(
                        (string) ($supplier->currency_code ?: $this->tenantCurrency($request))
                    ),
                ])
                ->values()
                ->all(),
            'inlineCreate' => $canCreateSupplier
                ? [
                    'label' => '+ New Supplier',
                    'storeUrl' => route('purchasing.suppliers.store'),
                    'fields' => [
                        [
                            'name' => 'company_name',
                            'label' => 'Company name',
                            'type' => 'text',
                            'required' => true,
                        ],
                        [
                            'name' => 'email',
                            'label' => 'Email',
                            'type' => 'email',
                            'required' => false,
                        ],
                        [
                            'name' => 'phone',
                            'label' => 'Phone',
                            'type' => 'text',
                            'required' => false,
                        ],
                        [
                            'name' => 'url',
                            'label' => 'URL',
                            'type' => 'url',
                            'required' => false,
                        ],
                    ],
                ]
                : null,
        ];
    }

    /**
     * Build the material selector for supplier detail.
     *
     * @return array<string, mixed>
     */
    private function materialField(Request $request): array
    {
        return [
            'name' => 'item_id',
            'label' => 'Material',
            'type' => 'combobox',
            'required' => true,
            'options' => Item::query()
                ->where('tenant_id', $request->user()->tenant_id)
                ->where('is_purchasable', true)
                ->orderBy('name')
                ->get(['id', 'name'])
                ->map(fn (Item $item): array => [
                    'value' => (string) $item->id,
                    'label' => $item->name,
                ])
                ->values()
                ->all(),
        ];
    }

    /**
     * Build the shared package quantity, UoM, SKU, and price fields.
     *
     * @param array<string, mixed> $priceCurrencyConfig
     * @return array<int, array<string, mixed>>
     */
    private function packageFields(Request $request, array $priceCurrencyConfig): array
    {
        return [
            [
                'name' => 'pack_quantity',
                'label' => 'Qty',
                'type' => 'smart-number',
                'numberType' => 'decimal',
                'precision' => 6,
                'required' => true,
                'rowGroup' => 'package-quantity-uom',
                'width' => 'short',
            ],
            [
                'name' => 'pack_uom_id',
                'label' => 'UoM',
                'type' => 'select',
                'required' => true,
                'rowGroup' => 'package-quantity-uom',
                'options' => Uom::query()
                    ->where('tenant_id', $request->user()->tenant_id)
                    ->orderBy('symbol')
                    ->get(['id', 'symbol', 'name'])
                    ->map(fn (Uom $uom): array => [
                        'value' => (string) $uom->id,
                        'label' => sprintf('%s (%s)', $uom->name, $uom->symbol),
                    ])
                    ->values()
                    ->all(),
            ],
            [
                'name' => 'supplier_sku',
                'label' => 'SKU',
                'type' => 'text',
                'required' => false,
                'rowGroup' => 'supplier-sku-price',
                'width' => 'short',
            ],
            array_merge([
                'name' => 'price_amount',
                'label' => 'Price',
                'type' => 'smart-number',
                'numberType' => 'money',
                'precision' => 2,
                'required' => true,
                'rowGroup' => 'supplier-sku-price',
                'width' => 'right',
            ], $priceCurrencyConfig),
        ];
    }

    /**
     * Resolve the tenant currency for supplier-package form defaults.
     */
    private function tenantCurrency(Request $request): string
    {
        return strtoupper((string) ($request->user()?->tenant?->currency_code ?: config('app.currency_code', 'USD')));
    }
}
