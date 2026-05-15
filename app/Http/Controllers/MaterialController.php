<?php

namespace App\Http\Controllers;

use App\Models\Item;
use App\Models\Uom;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;

/**
 * Handle the materials index and shared CRUD list contract.
 */
class MaterialController extends Controller
{
    /**
     * Display the materials index shell.
     */
    public function index(): View
    {
        Gate::authorize('inventory-materials-view');

        /** @var \App\Models\User $user */
        $user = auth()->user();
        $uoms = Uom::query()->orderBy('name')->get();
        $crudConfig = $this->materialsCrudConfig();
        $tenantCurrency = $user?->tenant?->currency_code ?: (string) config('app.currency_code', 'USD');

        $payload = [
            'uoms' => $uoms->map(fn (Uom $uom): array => [
                'id' => $uom->id,
                'name' => $uom->name,
                'symbol' => $uom->symbol,
            ])->values()->all(),
            'storeUrl' => $crudConfig['endpoints']['create'],
            'navigationStateUrl' => route('navigation.state'),
            'csrfToken' => csrf_token(),
            'tenantCurrency' => Str::upper((string) $tenantCurrency),
        ];

        return view('materials.index', [
            'crudConfig' => $crudConfig,
            'payload' => $payload,
            'uoms' => $uoms,
        ]);
    }

    /**
     * Return the materials list read model for the shared CRUD page module.
     */
    public function list(Request $request): JsonResponse
    {
        Gate::authorize('inventory-materials-view');

        $crudConfig = $this->materialsCrudConfig();
        $validated = $request->validate([
            'search' => ['nullable', 'string'],
            'sort' => ['nullable', 'string'],
            'direction' => ['nullable', 'in:asc,desc'],
        ]);

        $search = trim((string) ($validated['search'] ?? ''));
        $sortColumn = (string) ($validated['sort'] ?? 'name');
        $direction = (string) ($validated['direction'] ?? 'asc');
        $materials = $this->materialsQuery($search, $sortColumn, $direction)->get();

        return response()->json([
            'data' => $materials
                ->map(fn (Item $item): array => $this->materialListData($item))
                ->values()
                ->all(),
            'meta' => [
                'search' => $search,
                'sort' => [
                    'column' => $sortColumn,
                    'direction' => $direction,
                ],
                'allowed_sort_columns' => $crudConfig['sortable'],
                'total' => $materials->count(),
            ],
        ]);
    }

    /**
     * Build the filtered and sorted materials query shared by the list page.
     */
    private function materialsQuery(string $search, string $sortColumn, string $direction)
    {
        $query = Item::query()->with('baseUom');

        if ($search !== '') {
            $query->where('name', 'like', '%' . $search . '%');
        }

        if ($sortColumn === 'base_uom') {
            $query->leftJoin('uoms', 'uoms.id', '=', 'items.base_uom_id')
                ->select('items.*')
                ->orderBy('uoms.name', $direction)
                ->orderBy('items.name');

            return $query;
        }

        return $query->orderBy('items.name', $direction);
    }

    /**
     * Build the JSON list row for the shared materials CRUD renderer.
     *
     * @return array<string, mixed>
     */
    private function materialListData(Item $item): array
    {
        return [
            'id' => $item->id,
            'name' => $item->name,
            'base_uom_id' => $item->base_uom_id,
            'base_uom_name' => $item->baseUom?->name,
            'base_uom_symbol' => $item->baseUom?->symbol,
            'is_purchasable' => $item->is_purchasable,
            'is_sellable' => $item->is_sellable,
            'is_manufacturable' => $item->is_manufacturable,
            'default_price_amount' => $this->formatCentsToAmount($item->default_price_cents),
            'default_price_currency_code' => $item->default_price_currency_code,
            'has_stock_moves' => $item->stockMoves()->exists(),
            'show_url' => route('materials.show', $item),
        ];
    }

    /**
     * Return the shared CRUD config for the materials page module.
     *
     * @return array<string, mixed>
     */
    private function materialsCrudConfig(): array
    {
        $canManageMaterials = Gate::allows('inventory-materials-manage');

        return [
            'resource' => 'materials',
            'endpoints' => [
                'list' => route('materials.list'),
                'create' => route('materials.store'),
                'update' => url('/materials/{id}'),
                'delete' => url('/materials/{id}'),
            ],
            'detailUrlTemplate' => url('/materials/{id}'),
            'columns' => ['name', 'base_uom', 'flags'],
            'headers' => [
                'name' => 'Name',
                'base_uom' => 'Base UoM',
                'flags' => 'Flags',
            ],
            'sortable' => ['name', 'base_uom'],
            'labels' => [
                'searchPlaceholder' => 'Search materials',
                'createTitle' => 'Create Material',
                'createAriaLabel' => 'Create Material',
                'emptyState' => 'No materials found.',
                'actionsAriaLabel' => 'Material actions',
            ],
            'permissions' => [
                'showExport' => false,
                'showImport' => false,
                'showCreate' => $canManageMaterials,
            ],
            'rowDisplay' => [
                'columns' => [
                    'name' => [
                        'kind' => 'linked-text',
                        'urlExpression' => 'record.show_url',
                    ],
                    'base_uom' => ['kind' => 'text'],
                    'flags' => ['kind' => 'text'],
                ],
            ],
            'mobileCard' => [
                'titleExpression' => "record.name || '—'",
                'subtitleExpression' => 'materialBaseUomLabel(record)',
                'bodyExpression' => 'materialFlagsLabel(record)',
            ],
            'actions' => $canManageMaterials ? [
                [
                    'id' => 'edit',
                    'label' => 'Edit',
                    'tone' => 'default',
                ],
                [
                    'id' => 'delete',
                    'label' => 'Delete',
                    'tone' => 'warning',
                ],
            ] : [],
        ];
    }

    /**
     * Format cents into a decimal amount string.
     */
    private function formatCentsToAmount(?int $cents): ?string
    {
        if ($cents === null) {
            return null;
        }

        $whole = intdiv($cents, 100);
        $decimal = abs($cents % 100);

        return sprintf('%d.%02d', $whole, $decimal);
    }
}
