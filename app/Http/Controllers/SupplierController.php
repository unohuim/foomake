<?php

namespace App\Http\Controllers;

use App\Http\Requests\Purchasing\SupplierUpdateRequest;
use App\Models\Item;
use App\Models\Supplier;
use App\Models\Uom;
use App\Services\Purchasing\SupplierDeleteGuard;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Route;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * Handle supplier index and creation.
 */
class SupplierController extends Controller
{
    /**
     * Display the suppliers index.
     */
    public function index(Request $request): View
    {
        Gate::authorize('purchasing-suppliers-view');

        $tenantCurrency = $request->user()?->tenant?->currency_code;
        $defaultCurrency = $tenantCurrency ?: (string) config('app.currency_code', 'USD');
        $crudConfig = $this->suppliersCrudConfig();

        return view('purchasing.suppliers.index', [
            'crudConfig' => $crudConfig,
            'payload' => [
                'suppliers' => [],
                'storeUrl' => $crudConfig['endpoints']['create'],
                'updateUrlBase' => url('/purchasing/suppliers'),
                'navigationStateUrl' => route('navigation.state'),
                'csrfToken' => csrf_token(),
                'defaultCurrency' => $defaultCurrency,
                'canManageSuppliers' => Gate::allows('purchasing-suppliers-manage'),
            ],
        ]);
    }

    /**
     * Return the suppliers list read model for the page module.
     */
    public function list(Request $request): JsonResponse
    {
        Gate::authorize('purchasing-suppliers-view');

        $crudConfig = $this->suppliersCrudConfig();
        $validated = $request->validate([
            'search' => ['nullable', 'string', 'max:255'],
            'sort' => ['nullable', 'string', Rule::in($crudConfig['sortable'])],
            'direction' => ['nullable', 'string', Rule::in(['asc', 'desc'])],
        ]);

        $search = trim((string) ($validated['search'] ?? ''));
        $sortColumn = (string) ($validated['sort'] ?? 'company_name');
        $direction = (string) ($validated['direction'] ?? 'asc');
        $suppliers = $this->suppliersQuery($search, $sortColumn, $direction)->get();

        return response()->json([
            'data' => $suppliers
                ->map(fn (Supplier $supplier): array => $this->supplierIndexData($supplier))
                ->values()
                ->all(),
            'meta' => [
                'search' => $search,
                'sort' => [
                    'column' => $sortColumn,
                    'direction' => $direction,
                ],
                'allowed_sort_columns' => $crudConfig['sortable'],
                'total' => $suppliers->count(),
            ],
        ]);
    }

    /**
     * Display a supplier detail page with pricing.
     */
    public function show(Request $request, Supplier $supplier): View
    {
        Gate::authorize('purchasing-suppliers-view');
        $this->abortIfWrongTenant($request, $supplier);

        $tenantCurrency = strtoupper((string) ($request->user()?->tenant?->currency_code ?: config('app.currency_code', 'USD')));
        $canManageSuppliers = Gate::allows('purchasing-suppliers-manage');

        $payload = [
            'supplier' => [
                'id' => $supplier->id,
                'company_name' => $supplier->company_name,
                'url' => $supplier->url,
                'phone' => $supplier->phone,
                'email' => $supplier->email,
                'currency_code' => $supplier->currency_code,
                'update_url' => route('purchasing.suppliers.update', $supplier),
                'can_manage' => $canManageSuppliers,
            ],
            'csrfToken' => csrf_token(),
            'tenantCurrencyCode' => $tenantCurrency,
            'supplierCurrencyCode' => $supplier->currency_code ? strtoupper($supplier->currency_code) : null,
            'sections' => [
                'supplierPackages' => $this->supplierPackagesSectionConfig($request, $supplier, $canManageSuppliers),
                'purchaseOrders' => $this->purchaseOrdersSectionConfig($supplier),
            ],
            'purchaseOrderCreate' => Gate::allows('purchasing-purchase-orders-create')
                ? $this->purchaseOrderCreatePayload($supplier)
                : null,
        ];

        return view('purchasing.suppliers.show', [
            'supplier' => $supplier,
            'payload' => $payload,
        ]);
    }

    /**
     * Build the supplier-scoped package section config.
     *
     * @return array<string, mixed>
     */
    private function supplierPackagesSectionConfig(Request $request, Supplier $supplier, bool $canManageSuppliers): array
    {
        return [
            'resource' => 'supplier-packages',
            'title' => 'Supplier Packages',
            'description' => 'Purchasing options supplied by this supplier.',
            'emptyState' => 'No supplier packages have been added for this supplier.',
            'csrfToken' => csrf_token(),
            'defaultOpen' => true,
            'context' => [
                'supplier_id' => $supplier->id,
            ],
            'permissions' => [
                'canCreate' => $canManageSuppliers,
            ],
            'createAction' => [
                'type' => 'slide-over',
                'label' => 'Add Supplier Package',
                'title' => 'Add Supplier Package',
                'submitLabel' => 'Save package',
                'prefill' => [
                    'supplier_id' => $supplier->id,
                ],
            ],
            'endpoints' => [
                'list' => route('purchasing.suppliers.purchase-options.index', $supplier),
                'create' => route('purchasing.suppliers.purchase-options.store', $supplier),
                'update' => url("/purchasing/suppliers/{$supplier->id}/purchase-options/{id}"),
                'remove' => url("/purchasing/suppliers/{$supplier->id}/purchase-options/{id}"),
            ],
            'fields' => [
                [
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
                ],
                [
                    'name' => 'pack_quantity',
                    'label' => 'Package quantity',
                    'type' => 'text',
                    'required' => true,
                ],
                [
                    'name' => 'pack_uom_id',
                    'label' => 'Package UoM',
                    'type' => 'select',
                    'required' => true,
                    'rowGroup' => 'package-uom-price',
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
                    'label' => 'Supplier SKU',
                    'type' => 'text',
                    'required' => false,
                ],
                [
                    'name' => 'price_amount',
                    'label' => 'Price',
                    'type' => 'text',
                    'required' => false,
                    'rowGroup' => 'package-uom-price',
                ],
            ],
            'rowLayout' => [
                'primaryText' => [
                    'field' => 'display.primaryText',
                    'fallback' => 'Unknown material',
                ],
                'secondaryFields' => [
                    [
                        'label' => 'Package',
                        'field' => 'display.packageText',
                        'fallback' => '—',
                    ],
                    [
                        'label' => 'SKU',
                        'field' => 'display.skuText',
                        'fallback' => '—',
                    ],
                ],
                'badges' => [
                    [
                        'field' => 'display.statusText',
                        'toneField' => 'display.statusTone',
                        'fallback' => '',
                    ],
                ],
                'rightMeta' => [
                    [
                        'label' => 'Price',
                        'field' => 'display.priceText',
                        'fallback' => 'No price',
                        'strong' => true,
                    ],
                ],
            ],
            'actions' => [
                [
                    'id' => 'edit',
                    'label' => 'Edit',
                    'type' => 'edit',
                    'tone' => 'default',
                ],
                [
                    'id' => 'purchase',
                    'label' => 'Purchase',
                    'type' => 'custom',
                    'tone' => 'default',
                    'handlerKey' => 'purchase',
                ],
                [
                    'id' => 'delete',
                    'label' => 'Delete',
                    'type' => 'remove',
                    'tone' => 'warning',
                    'endpointKey' => 'remove',
                    'method' => 'DELETE',
                ],
            ],
        ];
    }

    /**
     * Build the supplier-scoped purchase orders section config.
     *
     * @return array<string, mixed>
     */
    private function purchaseOrdersSectionConfig(Supplier $supplier): array
    {
        return [
            'resource' => 'supplier-purchase-orders',
            'title' => 'Purchase Orders',
            'description' => 'Purchase orders for this supplier.',
            'emptyState' => 'No purchase orders have been created for this supplier.',
            'csrfToken' => csrf_token(),
            'defaultOpen' => false,
            'permissions' => [
                'canCreate' => Gate::allows('purchasing-purchase-orders-create'),
            ],
            'createAction' => [
                'type' => 'custom',
                'label' => 'Create Purchase Order',
                'handlerKey' => 'createSupplierPurchaseOrder',
                'url' => route('purchasing.orders.store'),
                'prefill' => [
                    'supplier_id' => $supplier->id,
                ],
            ],
            'endpoints' => [
                'list' => route('purchasing.suppliers.purchase-orders.index', $supplier),
            ],
            'fields' => [],
            'rowLayout' => [
                'primaryText' => [
                    'field' => 'display.poNumberText',
                    'fallback' => 'Draft PO',
                ],
                'secondaryFields' => [
                    [
                        'label' => 'Order date',
                        'field' => 'display.orderDateText',
                        'fallback' => 'No order date',
                    ],
                    [
                        'label' => 'Supplier',
                        'field' => 'display.supplierText',
                        'fallback' => 'Supplier not set',
                    ],
                ],
                'badges' => [
                    [
                        'field' => 'display.statusText',
                        'toneField' => 'display.statusTone',
                        'fallback' => '',
                    ],
                ],
                'rightMeta' => [
                    [
                        'label' => 'Total',
                        'field' => 'display.totalText',
                        'fallback' => '—',
                        'strong' => true,
                    ],
                ],
            ],
            'actions' => [
                [
                    'id' => 'view',
                    'label' => 'View',
                    'type' => 'view',
                    'tone' => 'default',
                    'urlField' => 'display.showUrl',
                ],
            ],
        ];
    }

    /**
     * Build the purchase slide-over payload for supplier package rows.
     *
     * @return array<string, mixed>
     */
    private function purchaseOrderCreatePayload(Supplier $supplier): array
    {
        return [
            'storeUrl' => '',
            'csrfToken' => csrf_token(),
            'suppliers' => [
                [
                    'id' => $supplier->id,
                    'name' => $supplier->company_name,
                ],
            ],
            'packages' => $supplier->purchaseOptions()
                ->where('is_active', true)
                ->with(['item', 'packUom', 'currentPrice'])
                ->orderBy('id')
                ->get()
                ->map(function ($option): array {
                    $quantity = bcadd((string) $option->pack_quantity, '0', 6);
                    $symbol = $option->packUom?->symbol;
                    $label = $symbol
                        ? sprintf('%s (%s %s)', $option->item?->name, $quantity, $symbol)
                        : sprintf('%s (%s)', $option->item?->name, $quantity);

                    return [
                        'id' => $option->id,
                        'supplier_id' => $option->supplier_id,
                        'item_id' => $option->item_id,
                        'item_name' => $option->item?->name,
                        'label' => $label,
                        'pack_quantity' => $quantity,
                        'pack_uom_symbol' => $option->packUom?->symbol,
                        'supplier_sku' => $option->supplier_sku,
                        'current_price_cents' => $option->currentPrice?->converted_price_cents ?? 0,
                    ];
                })
                ->values()
                ->all(),
        ];
    }

    /**
     * Store a new supplier.
     */
    public function store(Request $request): JsonResponse
    {
        Gate::authorize('purchasing-suppliers-manage');

        $validated = $request->validate([
            'company_name' => ['required', 'string', 'max:255'],
            'url' => ['nullable', 'string', 'max:255'],
            'phone' => ['nullable', 'string', 'max:50'],
            'email' => ['nullable', 'email', 'max:255'],
            'currency_code' => ['nullable', 'string', 'size:3'],
        ]);
        $currencyCode = $validated['currency_code'] ?? null;
        $currencyCode = $currencyCode === null || $currencyCode === ''
            ? null
            : strtoupper($currencyCode);

        $supplier = Supplier::query()->create([
            'tenant_id' => $request->user()->tenant_id,
            'company_name' => $validated['company_name'],
            'url' => $validated['url'] ?? null,
            'phone' => $validated['phone'] ?? null,
            'email' => $validated['email'] ?? null,
            'currency_code' => $currencyCode,
        ]);

        return response()->json([
            'data' => $this->supplierIndexData($supplier),
        ], 201);
    }

    /**
     * Update an existing supplier.
     */
    public function update(SupplierUpdateRequest $request, Supplier $supplier): JsonResponse
    {
        Gate::authorize('purchasing-suppliers-manage');

        $this->abortIfWrongTenant($request, $supplier);

        $validated = $request->validated();

        $updateData = [
            'company_name' => $validated['company_name'],
        ];

        $payload = $request->all();

        foreach (['url', 'phone', 'email', 'currency_code'] as $field) {
            if (! array_key_exists($field, $payload)) {
                continue;
            }

            $value = $validated[$field] ?? null;

            if ($field === 'currency_code' && $value !== null && $value !== '') {
                $value = strtoupper($value);
            }

            $updateData[$field] = $value;
        }

        $supplier->update($updateData);

        return response()->json([
            'data' => $this->supplierIndexData($supplier),
        ]);
    }

    /**
     * Delete a supplier.
     */
    public function destroy(Request $request, Supplier $supplier, SupplierDeleteGuard $deleteGuard): JsonResponse
    {
        Gate::authorize('purchasing-suppliers-manage');

        $this->abortIfWrongTenant($request, $supplier);

        if ($deleteGuard->isLinkedToMaterials($supplier)) {
            return response()->json([
                'message' => 'Supplier cannot be deleted because it is linked to materials.',
            ], 422);
        }

        $supplier->delete();

        return response()->json([
            'message' => 'Deleted.',
        ]);
    }

    /**
     * Abort with 404 if the supplier does not belong to the authenticated tenant.
     */
    private function abortIfWrongTenant(Request $request, Supplier $supplier): void
    {
        if ($request->user() && $supplier->tenant_id !== $request->user()->tenant_id) {
            abort(404);
        }
    }

    /**
     * Return the server-owned Suppliers CRUD page configuration.
     *
     * @return array<string, mixed>
     */
    private function suppliersCrudConfig(): array
    {
        $endpoints = [
            'list' => route('purchasing.suppliers.list'),
            'create' => route('purchasing.suppliers.store'),
            'update' => url('/purchasing/suppliers/{id}'),
            'delete' => url('/purchasing/suppliers/{id}'),
        ];

        if (Route::has('purchasing.suppliers.export')) {
            $endpoints['export'] = route('purchasing.suppliers.export');
        }

        if (
            Route::has('purchasing.suppliers.import.preview')
            && Route::has('purchasing.suppliers.import.store')
        ) {
            $endpoints['importPreview'] = route('purchasing.suppliers.import.preview');
            $endpoints['importStore'] = route('purchasing.suppliers.import.store');
        }

        $canManage = Gate::allows('purchasing-suppliers-manage');

        return [
            'resource' => 'suppliers',
            'endpoints' => $endpoints,
            'detailUrlTemplate' => url('/purchasing/suppliers/{id}'),
            'columns' => ['company_name', 'phone', 'email', 'currency_code'],
            'headers' => [
                'company_name' => 'Supplier name',
                'phone' => 'Phone',
                'email' => 'Email',
                'currency_code' => 'Currency',
            ],
            'sortable' => ['company_name', 'phone', 'email', 'currency_code'],
            'labels' => [
                'searchPlaceholder' => 'Search suppliers',
                'exportTitle' => 'Export Suppliers',
                'exportAriaLabel' => 'Export Suppliers',
                'importTitle' => 'Import Suppliers',
                'importAriaLabel' => 'Import Suppliers',
                'createTitle' => 'Add New Supplier',
                'createAriaLabel' => 'Add New Supplier',
                'emptyState' => 'No suppliers found.',
                'actionsAriaLabel' => 'Supplier actions',
            ],
            'permissions' => [
                'showExport' => $canManage && Route::has('purchasing.suppliers.export'),
                'showImport' => $canManage
                    && Route::has('purchasing.suppliers.import.preview')
                    && Route::has('purchasing.suppliers.import.store'),
                'showCreate' => $canManage,
            ],
            'rowDisplay' => [
                'columns' => [
                    'company_name' => [
                        'kind' => 'linked-text',
                        'urlExpression' => 'record.show_url',
                    ],
                    'phone' => ['kind' => 'text'],
                    'email' => ['kind' => 'text'],
                    'currency_code' => ['kind' => 'text'],
                ],
            ],
            'mobileCard' => [
                'titleExpression' => "record.company_name || '-'",
                'subtitleExpression' => "record.email || '-'",
                'bodyExpression' => "record.phone || record.currency_code || '-'",
            ],
            'actions' => $canManage ? [
                [
                    'id' => 'edit',
                    'label' => 'Edit',
                    'tone' => 'default',
                ],
                [
                    'id' => 'archive',
                    'label' => 'Archive',
                    'tone' => 'warning',
                ],
            ] : [],
        ];
    }

    /**
     * Build the suppliers index query for the configured CRUD module.
     */
    private function suppliersQuery(string $search, string $sortColumn, string $direction): Builder
    {
        return Supplier::query()
            ->when($search !== '', function ($query) use ($search): void {
                $query->where(function ($nested) use ($search): void {
                    $nested->where('company_name', 'like', '%' . $search . '%')
                        ->orWhere('phone', 'like', '%' . $search . '%')
                        ->orWhere('email', 'like', '%' . $search . '%')
                        ->orWhere('currency_code', 'like', '%' . $search . '%');
                });
            })
            ->orderBy($sortColumn, $direction)
            ->orderBy('id');
    }

    /**
     * Return a supplier row payload for configured CRUD consumers.
     *
     * @return array<string, mixed>
     */
    private function supplierIndexData(Supplier $supplier): array
    {
        return [
            'id' => $supplier->id,
            'company_name' => $supplier->company_name,
            'url' => $supplier->url,
            'phone' => $supplier->phone,
            'email' => $supplier->email,
            'currency_code' => $supplier->currency_code,
            'show_url' => route('purchasing.suppliers.show', $supplier),
        ];
    }
}
