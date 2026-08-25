<?php

namespace App\Http\Controllers;

use App\Models\Uom;
use App\Support\Inertia\AuthShellPayloadBuilder;
use App\Support\Inventory\InventoryAvailabilityIndexReadModel;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response as InertiaResponse;

/**
 * Handle the materials availability index and shared CRUD list contract.
 */
class MaterialController extends Controller
{
    /**
     * Display the materials index shell.
     */
    public function index(Request $request, AuthShellPayloadBuilder $authShellPayloadBuilder): InertiaResponse
    {
        $this->authorizeMaterialAvailabilityView();

        /** @var \App\Models\User $user */
        $user = $request->user();
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

        return Inertia::render('Materials/Index', [
            'shell' => $authShellPayloadBuilder->build($request),
            'crudConfig' => $crudConfig,
            'payload' => $payload,
        ]);
    }


    /**
     * Return the materials availability list read model for the shared CRUD page module.
     */
    public function list(Request $request, InventoryAvailabilityIndexReadModel $readModel): JsonResponse
    {
        $this->authorizeMaterialAvailabilityView();

        $crudConfig = $this->materialsCrudConfig();
        $validated = $request->validate([
            'search' => ['nullable', 'string'],
            'sort' => ['nullable', 'string'],
            'direction' => ['nullable', 'in:asc,desc'],
        ]);

        $search = trim((string) ($validated['search'] ?? ''));
        $allowedSortColumns = $crudConfig['sortable'];
        $requestedSortColumn = (string) ($validated['sort'] ?? 'item');
        $sortColumn = in_array($requestedSortColumn, $allowedSortColumns, true) ? $requestedSortColumn : 'item';
        $direction = (string) ($validated['direction'] ?? 'asc');
        $rows = $readModel->rows((int) $request->user()->tenant_id, $search, $sortColumn, $direction);

        return response()->json([
            'data' => $rows->values()->all(),
            'meta' => [
                'search' => $search,
                'sort' => [
                    'column' => $sortColumn,
                    'direction' => $direction,
                ],
                'allowed_sort_columns' => $allowedSortColumns,
                'total' => $rows->count(),
            ],
        ]);
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
                'delete' => '',
            ],
            'detailUrlTemplate' => url('/materials/{id}'),
            'columns' => ['item', 'on_hand', 'sell', 'buy', 'make', 'net'],
            'headers' => [
                'item' => 'Item',
                'on_hand' => 'On-Hand',
                'sell' => 'Sell',
                'buy' => 'Buy',
                'make' => 'Make',
                'net' => 'Net',
            ],
            'sortable' => ['item'],
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
                'canManageMaterials' => $canManageMaterials,
            ],
            'rowDisplay' => [
                'columns' => [
                    'item' => [
                        'kind' => 'stacked-text',
                        'urlExpression' => "record.show_url || ''",
                        'subtitleExpression' => "record.item_uom_name || '—'",
                    ],
                    'on_hand' => ['kind' => 'text'],
                    'sell' => ['kind' => 'text'],
                    'buy' => ['kind' => 'text'],
                    'make' => ['kind' => 'text'],
                    'net' => ['kind' => 'text'],
                ],
            ],
            'mobileCard' => [
                'titleExpression' => "record.item || '—'",
                'titleAsideStatsExpression' => 'materialMobileQuantityStats(record)',
                'subtitleExpression' => '',
                'bodyExpression' => '',
                'layout' => 'flush-stacked',
                'badgesExpression' => '',
                'iconBadgesExpression' => 'materialFlagIcons(record)',
                'urlExpression' => 'record.show_url',
                'showActions' => false,
                'showToggle' => false,
                'toggle' => [
                    'name' => 'is_active',
                    'checkedExpression' => 'Boolean(record.is_active)',
                    'disabledExpression' => '!canManageMaterials()',
                    'eventName' => 'inventory-material-active-toggle',
                    'handler' => 'toggleMaterialActive(toggleDetail)',
                    'ariaLabelExpression' => '`Toggle ${record.item || "material"} active state`',
                ],
            ],
            'desktopCard' => [
                'titleExpression' => "record.item || '—'",
                'titleAsideExpression' => 'materialCardUomLabel(record)',
                'subtitleExpression' => '',
                'bodyExpression' => '',
                'badgesExpression' => '[]',
                'iconBadgesExpression' => 'materialFlagIcons(record)',
                'statsExpression' => 'materialAvailabilityStats(record)',
                'urlExpression' => 'record.show_url',
            ],
            'actions' => [],
        ];
    }

    /**
     * Authorize read-only materials availability access.
     */
    private function authorizeMaterialAvailabilityView(): void
    {
        abort_unless(
            Gate::allows('inventory-materials-view')
                || Gate::allows('inventory-stock-view')
                || Gate::allows('inventory-adjustments-view'),
            403
        );
    }
}
