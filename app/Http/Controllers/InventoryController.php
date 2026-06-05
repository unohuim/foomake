<?php

namespace App\Http\Controllers;

use App\Support\Inventory\InventoryAvailabilityIndexReadModel;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class InventoryController extends Controller
{
    /**
     * Display the inventory index shell.
     */
    public function index(): View
    {
        $this->authorizeStockView();

        return view('inventory.index', [
            'crudConfig' => $this->inventoryCrudConfig(),
            'payload' => [
                'csrfToken' => csrf_token(),
            ],
        ]);
    }

    /**
     * Return the inventory availability list read model for the shared CRUD page module.
     */
    public function list(Request $request, InventoryAvailabilityIndexReadModel $readModel): JsonResponse
    {
        $this->authorizeStockView();

        $crudConfig = $this->inventoryCrudConfig();
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
     * Return the shared CRUD config for the inventory page module.
     *
     * @return array<string, mixed>
     */
    private function inventoryCrudConfig(): array
    {
        $canManageMaterials = Gate::allows('inventory-materials-manage');

        return [
            'resource' => 'inventory',
            'endpoints' => [
                'list' => route('inventory.list'),
                'create' => '',
                'update' => url('/materials/{id}'),
                'delete' => '',
            ],
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
                'searchPlaceholder' => 'Search inventory',
                'emptyState' => 'No inventory items found.',
                'actionsAriaLabel' => 'Inventory actions',
            ],
            'permissions' => [
                'showExport' => false,
                'showImport' => false,
                'showCreate' => false,
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
                'titleAsideExpression' => "record.item_uom_name || record.item_uom_symbol || '—'",
                'subtitleExpression' => '',
                'bodyExpression' => 'inventoryAvailabilitySummary(record)',
                'badgesExpression' => 'inventoryMaterialFlagBadges(record)',
                'urlExpression' => 'record.show_url',
                'showActions' => false,
                'toggle' => [
                    'name' => 'is_active',
                    'checkedExpression' => 'Boolean(record.is_active)',
                    'disabledExpression' => '!canManageMaterials()',
                    'eventName' => 'inventory-material-active-toggle',
                    'handler' => 'toggleInventoryMaterialActive(toggleDetail)',
                    'ariaLabelExpression' => '`Toggle ${record.item || "material"} active state`',
                ],
            ],
            'actions' => [],
        ];
    }

    /**
     * Authorize read-only inventory availability access.
     */
    private function authorizeStockView(): void
    {
        abort_unless(
            Gate::allows('inventory-stock-view') || Gate::allows('inventory-adjustments-view'),
            403
        );
    }
}
