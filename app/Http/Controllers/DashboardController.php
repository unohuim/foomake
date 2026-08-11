<?php

namespace App\Http\Controllers;

use App\Actions\Workflows\CanViewAssignedWorkflowResourceAction;
use App\Models\InventoryCount;
use App\Models\MakeOrder;
use App\Models\PurchaseOrder;
use App\Models\SalesOrder;
use App\Models\Task;
use App\Models\User;
use App\Navigation\NavigationEligibility;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Render the authenticated app dashboard.
 */
class DashboardController extends Controller
{
    /**
     * Display the dashboard with the current user's assigned work.
     */
    public function __invoke(Request $request, NavigationEligibility $navigationEligibility): Response
    {
        $user = $request->user();
        $tenantId = (int) $user->tenant_id;
        $userId = (int) $user->id;

        $responsibilities = $this->workflowResponsibilities($user, $tenantId, $userId);
        $stageTasks = $this->stageTasks($user, $tenantId, $userId);

        return Inertia::render('Dashboard', [
            'shell' => [
                'logo' => [
                    'src' => null,
                    'alt' => config('app.name', 'Factory Manager'),
                ],
                'user' => [
                    'name' => $user->name,
                    'email' => $user->email,
                ],
                'navigation' => [
                    'dashboardUrl' => route('dashboard', absolute: false),
                    'profileUrl' => route('profile.edit', absolute: false),
                    'logoutUrl' => route('logout', absolute: false),
                    'groups' => $this->navigationGroups($request, $user, $navigationEligibility->forUser($user)),
                    'accountItems' => $this->accountNavigationItems($request, $user),
                ],
            ],
            'todo' => [
                'heading' => 'Todo',
                'description' => 'Assigned workflow responsibilities and stage tasks.',
                'emptyState' => 'No assigned work.',
                'hasTodo' => count($responsibilities) > 0 || count($stageTasks) > 0,
                'responsibilities' => $responsibilities,
                'stageTasks' => $stageTasks,
            ],
        ]);
    }

    /**
     * Build the authenticated desktop navigation groups.
     *
     * @param array<string, bool> $navigationEligibility
     * @return array<int, array<string, mixed>>
     */
    private function navigationGroups(Request $request, User $user, array $navigationEligibility): array
    {
        $groups = [
            [
                'key' => 'dashboard',
                'label' => 'Dashboard',
                'active' => $request->routeIs('dashboard'),
                'items' => [
                    [
                        'label' => 'Dashboard',
                        'url' => route('dashboard', absolute: false),
                        'active' => $request->routeIs('dashboard'),
                        'enabled' => true,
                    ],
                ],
            ],
        ];

        $salesItems = array_values(array_filter([
            $user->can('sales-customers-manage') ? [
                'label' => 'Customers',
                'url' => route('sales.customers.index', absolute: false),
                'active' => $request->routeIs('sales.customers.*'),
                'enabled' => true,
            ] : null,
            ($user->can('inventory-products-view') || $user->can('inventory-products-manage')) ? [
                'label' => 'Products',
                'url' => route('sales.products.index', absolute: false),
                'active' => $request->routeIs('sales.products.*'),
                'enabled' => true,
            ] : null,
            $user->can('sales-sales-orders-manage') ? [
                'label' => 'Orders',
                'url' => route('sales.orders.index', absolute: false),
                'active' => $request->routeIs('sales.orders.*'),
                'enabled' => $navigationEligibility['salesOrdersEnabled'] ?? false,
                'disabledReason' => 'Add a customer and sellable product first.',
            ] : null,
        ]));

        if ($salesItems !== []) {
            $groups[] = [
                'key' => 'sales',
                'label' => 'Sales',
                'active' => $request->routeIs('sales.*'),
                'items' => $salesItems,
            ];
        }

        $purchasingItems = array_values(array_filter([
            $user->can('purchasing-purchase-orders-create') ? [
                'label' => 'Orders',
                'url' => route('purchasing.orders.index', absolute: false),
                'active' => $request->routeIs('purchasing.orders.*'),
                'enabled' => $navigationEligibility['purchaseOrdersEnabled'] ?? false,
                'disabledReason' => 'Add a supplier and purchasable material first.',
            ] : null,
            $user->can('purchasing-suppliers-view') ? [
                'label' => 'Suppliers',
                'url' => route('purchasing.suppliers.index', absolute: false),
                'active' => $request->routeIs('purchasing.suppliers.*'),
                'enabled' => true,
            ] : null,
        ]));

        if ($purchasingItems !== []) {
            $groups[] = [
                'key' => 'purchasing',
                'label' => 'Purchasing',
                'active' => $request->routeIs('purchasing.*'),
                'items' => $purchasingItems,
            ];
        }

        $manufacturingItems = array_values(array_filter([
            $user->can('inventory-make-orders-view') ? [
                'label' => 'Make Orders',
                'url' => route('manufacturing.make-orders.index', absolute: false),
                'active' => $request->routeIs('manufacturing.make-orders.*'),
                'enabled' => $navigationEligibility['makeOrdersEnabled'] ?? false,
                'disabledReason' => 'Add a manufacturable item and active recipe first.',
            ] : null,
            $user->can('inventory-recipes-view') ? [
                'label' => 'Recipes',
                'url' => route('manufacturing.recipes.index', absolute: false),
                'active' => $request->routeIs('manufacturing.recipes.*'),
                'enabled' => true,
            ] : null,
        ]));

        if ($manufacturingItems !== []) {
            $groups[] = [
                'key' => 'manufacturing',
                'label' => 'Manufacturing',
                'active' => $request->routeIs('manufacturing.make-orders.*') || $request->routeIs('manufacturing.recipes.*'),
                'items' => $manufacturingItems,
            ];
        }

        $stockItems = array_values(array_filter([
            ($user->can('inventory-adjustments-view') || $user->can('inventory-adjustments-execute')) ? [
                'label' => 'Inventory Counts',
                'url' => route('inventory.counts.index', absolute: false),
                'active' => $request->routeIs('inventory.counts.*'),
                'enabled' => true,
            ] : null,
            ($user->can('inventory-stock-view') || $user->can('inventory-materials-view') || $user->can('inventory-materials-manage')) ? [
                'label' => 'Materials',
                'url' => route('materials.index', absolute: false),
                'active' => $request->routeIs('materials.*') && ! $request->routeIs('materials.uom-categories.*'),
                'enabled' => true,
            ] : null,
            $user->can('inventory-materials-manage') ? [
                'label' => 'UoM',
                'active' => $request->routeIs('materials.uom-categories.*')
                    || $request->routeIs('manufacturing.uoms.*')
                    || $request->routeIs('manufacturing.uom-conversions.*'),
                'enabled' => true,
                'children' => [
                    [
                        'label' => 'UoM Categories',
                        'url' => route('materials.uom-categories.index', absolute: false),
                        'active' => $request->routeIs('materials.uom-categories.*'),
                        'enabled' => true,
                    ],
                    [
                        'label' => 'Units of Measure',
                        'url' => route('manufacturing.uoms.index', absolute: false),
                        'active' => $request->routeIs('manufacturing.uoms.*'),
                        'enabled' => true,
                    ],
                    [
                        'label' => 'UoM Conversions',
                        'url' => route('manufacturing.uom-conversions.index', absolute: false),
                        'active' => $request->routeIs('manufacturing.uom-conversions.*'),
                        'enabled' => true,
                    ],
                ],
            ] : null,
        ]));

        if ($stockItems !== []) {
            $groups[] = [
                'key' => 'stock',
                'label' => 'Stock',
                'active' => $request->routeIs('inventory.counts.*')
                    || $request->routeIs('materials.*')
                    || $request->routeIs('manufacturing.uoms.*')
                    || $request->routeIs('manufacturing.uom-conversions.*'),
                'items' => $stockItems,
            ];
        }

        return $groups;
    }

    /**
     * Build account links for the authenticated user menu.
     *
     * @return array<int, array<string, mixed>>
     */
    private function accountNavigationItems(Request $request, User $user): array
    {
        return array_values(array_filter([
            [
                'label' => 'Profile',
                'url' => route('profile.edit', absolute: false),
                'active' => $request->routeIs('profile.edit'),
                'enabled' => true,
            ],
            $user->can('billing-subscription-manage') ? [
                'label' => 'Billing',
                'url' => route('billing.index', absolute: false),
                'active' => $request->routeIs('billing.*'),
                'enabled' => true,
            ] : null,
            $user->can('system-users-manage') ? [
                'label' => 'Connectors',
                'url' => route('profile.connectors.index', absolute: false),
                'active' => $request->routeIs('profile.connectors.*'),
                'enabled' => true,
            ] : null,
            $user->can('workflow-manage') ? [
                'label' => 'Workflows',
                'url' => route('admin.workflows.index', absolute: false),
                'active' => $request->routeIs('admin.workflows.*'),
                'enabled' => true,
            ] : null,
            $user->can('admin-users-view') ? [
                'label' => 'Users',
                'url' => route('admin.users.index', absolute: false),
                'active' => $request->routeIs('admin.users.*'),
                'enabled' => true,
            ] : null,
        ]));
    }

    /**
     * Build assigned workflow-owned records for the dashboard Todo section.
     *
     * @return array<int, array<string, string|null>>
     */
    private function workflowResponsibilities(User $user, int $tenantId, int $userId): array
    {
        return array_merge(
            $this->makeOrderResponsibilities($user, $tenantId, $userId),
            $this->inventoryCountResponsibilities($user, $tenantId, $userId),
            $this->purchaseOrderResponsibilities($user, $tenantId, $userId)
        );
    }

    /**
     * Build assigned Make Order responsibility rows.
     *
     * @return array<int, array<string, string|null>>
     */
    private function makeOrderResponsibilities(User $user, int $tenantId, int $userId): array
    {
        return MakeOrder::query()
            ->where('tenant_id', $tenantId)
            ->where('made_by_user_id', $userId)
            ->whereNotIn('status', [MakeOrder::STATUS_MADE, MakeOrder::STATUS_CANCELLED])
            ->with(['outputItem', 'workflowStage'])
            ->orderByRaw('due_date IS NULL')
            ->orderBy('due_date')
            ->orderByDesc('id')
            ->limit(10)
            ->get()
            ->filter(fn (MakeOrder $makeOrder): bool => $this->canViewWorkflowResource(
                $user,
                $makeOrder,
                'manufacturing',
                $makeOrder->made_by_user_id,
                'inventory-make-orders-view'
            ))
            ->map(fn (MakeOrder $makeOrder): array => [
                'domainName' => 'Make Order',
                'title' => 'Make Order #' . $makeOrder->id,
                'resource' => $makeOrder->outputItem?->name ?? 'Make Order #' . $makeOrder->id,
                'stage' => $makeOrder->workflowStage?->name ?? 'Draft',
                'dueDate' => $makeOrder->due_date?->format('M j, Y'),
                'status' => 'Open',
                'url' => route('manufacturing.make-orders.show', $makeOrder),
            ])
            ->values()
            ->all();
    }

    /**
     * Build assigned Inventory Count responsibility rows.
     *
     * @return array<int, array<string, string|null>>
     */
    private function inventoryCountResponsibilities(User $user, int $tenantId, int $userId): array
    {
        return InventoryCount::query()
            ->where('tenant_id', $tenantId)
            ->where('assigned_to_user_id', $userId)
            ->whereNull('posted_at')
            ->with('workflowStage')
            ->orderByDesc('counted_at')
            ->orderByDesc('id')
            ->limit(10)
            ->get()
            ->filter(fn (InventoryCount $inventoryCount): bool => $this->canViewWorkflowResource(
                $user,
                $inventoryCount,
                'inventory',
                $inventoryCount->assigned_to_user_id,
                'inventory-adjustments-view'
            ))
            ->map(function (InventoryCount $inventoryCount): array {
                $label = $this->inventoryCountLabel($inventoryCount);

                return [
                    'domainName' => 'Inventory Count',
                    'title' => $label,
                    'resource' => $label,
                    'stage' => $inventoryCount->workflowStage?->name ?? 'Draft',
                    'dueDate' => $inventoryCount->counted_at->format('M j, Y'),
                    'status' => 'Open',
                    'url' => route('inventory.counts.show', $inventoryCount),
                ];
            })
            ->values()
            ->all();
    }

    /**
     * Build assigned Purchase Order responsibility rows.
     *
     * @return array<int, array<string, string|null>>
     */
    private function purchaseOrderResponsibilities(User $user, int $tenantId, int $userId): array
    {
        return PurchaseOrder::query()
            ->where('tenant_id', $tenantId)
            ->where('assigned_to_user_id', $userId)
            ->whereNotIn('status', [PurchaseOrder::STATUS_COMPLETED, PurchaseOrder::STATUS_CANCELLED])
            ->whereNull('workflow_cancelled_at')
            ->with('currentWorkflowStage')
            ->orderByRaw('order_date IS NULL')
            ->orderBy('order_date')
            ->orderByDesc('id')
            ->limit(10)
            ->get()
            ->filter(fn (PurchaseOrder $purchaseOrder): bool => $this->canViewWorkflowResource(
                $user,
                $purchaseOrder,
                'purchasing',
                $purchaseOrder->assigned_to_user_id,
                'purchasing-purchase-orders-create'
            ))
            ->map(function (PurchaseOrder $purchaseOrder): array {
                $label = $this->purchaseOrderLabel($purchaseOrder);

                return [
                    'domainName' => 'Purchase Order',
                    'title' => $label,
                    'resource' => $label,
                    'stage' => $purchaseOrder->currentWorkflowStage?->name ?? 'Draft',
                    'dueDate' => $purchaseOrder->order_date?->format('M j, Y'),
                    'status' => 'Open',
                    'url' => route('purchasing.orders.show', $purchaseOrder),
                ];
            })
            ->values()
            ->all();
    }

    /**
     * Build assigned workflow-stage task rows for supported resource detail pages.
     *
     * @return array<int, array<string, bool|string|null>>
     */
    private function stageTasks(User $user, int $tenantId, int $userId): array
    {
        $tasks = Task::query()
            ->where('tenant_id', $tenantId)
            ->where('assigned_to_user_id', $userId)
            ->where('status', Task::STATUS_OPEN)
            ->with(['workflowDomain', 'workflowStage'])
            ->orderBy('sort_order')
            ->orderByDesc('id')
            ->limit(25)
            ->get();

        $resources = $this->taskResources($user, $tasks, $tenantId);

        return $tasks
            ->map(function (Task $task) use ($resources): ?array {
                $resource = $resources[$task->id] ?? null;

                if ($resource === null) {
                    return null;
                }

                return [
                    'title' => $task->title,
                    'domainName' => $resource['domainName'],
                    'resource' => $resource['label'],
                    'stage' => $task->workflowStage?->name,
                    'dueDate' => $task->due_date?->format('M j, Y') ?? $resource['dueDate'],
                    'status' => $this->statusLabel($task->status),
                    'url' => $resource['url'],
                ];
            })
            ->filter()
            ->values()
            ->all();
    }

    /**
     * Resolve task domain records in batches so dashboard rows do not trigger N+1 lookups.
     *
     * @param Collection<int, Task> $tasks
     * @return array<int, array{domainName: string, label: string, dueDate: string|null, url: string}>
     */
    private function taskResources(User $user, Collection $tasks, int $tenantId): array
    {
        $resources = [];
        $tasksByDomain = $tasks->groupBy(fn (Task $task): string => (string) $task->workflowDomain?->key);

        $resources += $this->makeOrderTaskResources($user, $tasksByDomain->get('manufacturing', collect()), $tenantId);
        $resources += $this->inventoryCountTaskResources($user, $tasksByDomain->get('inventory', collect()), $tenantId);
        $resources += $this->salesOrderTaskResources($user, $tasksByDomain->get('sales', collect()), $tenantId);
        $resources += $this->purchaseOrderTaskResources($user, $tasksByDomain->get('purchasing', collect()), $tenantId);

        return $resources;
    }

    /**
     * Resolve Make Order task resources.
     *
     * @param Collection<int, Task> $tasks
     * @return array<int, array{domainName: string, label: string, dueDate: string|null, url: string}>
     */
    private function makeOrderTaskResources(User $user, Collection $tasks, int $tenantId): array
    {
        if ($tasks->isEmpty()) {
            return [];
        }

        $makeOrders = MakeOrder::query()
            ->where('tenant_id', $tenantId)
            ->whereIn('id', $tasks->pluck('domain_record_id')->unique()->all())
            ->whereNotIn('status', [MakeOrder::STATUS_MADE, MakeOrder::STATUS_CANCELLED])
            ->get()
            ->keyBy('id');

        return $tasks
            ->mapWithKeys(function (Task $task) use ($makeOrders, $user): array {
                $makeOrder = $makeOrders->get($task->domain_record_id);

                if (
                    ! $makeOrder
                    || ! $this->canViewWorkflowResource(
                        $user,
                        $makeOrder,
                        'manufacturing',
                        $makeOrder->made_by_user_id,
                        'inventory-make-orders-view'
                    )
                ) {
                    return [];
                }

                return [
                    $task->id => [
                        'domainName' => 'Make Order',
                        'label' => 'Make Order #' . $makeOrder->id,
                        'dueDate' => $makeOrder->due_date?->format('M j, Y'),
                        'url' => route('manufacturing.make-orders.show', $makeOrder),
                    ],
                ];
            })
            ->all();
    }

    /**
     * Resolve Inventory Count task resources.
     *
     * @param Collection<int, Task> $tasks
     * @return array<int, array{domainName: string, label: string, dueDate: string|null, url: string}>
     */
    private function inventoryCountTaskResources(User $user, Collection $tasks, int $tenantId): array
    {
        if ($tasks->isEmpty()) {
            return [];
        }

        $counts = InventoryCount::query()
            ->where('tenant_id', $tenantId)
            ->whereIn('id', $tasks->pluck('domain_record_id')->unique()->all())
            ->whereNull('posted_at')
            ->get()
            ->keyBy('id');

        return $tasks
            ->mapWithKeys(function (Task $task) use ($counts, $user): array {
                $count = $counts->get($task->domain_record_id);

                if (
                    ! $count
                    || ! $this->canViewWorkflowResource(
                        $user,
                        $count,
                        'inventory',
                        $count->assigned_to_user_id,
                        'inventory-adjustments-view'
                    )
                ) {
                    return [];
                }

                return [
                    $task->id => [
                        'domainName' => 'Inventory Count',
                        'label' => $this->inventoryCountLabel($count),
                        'dueDate' => $count->counted_at->format('M j, Y'),
                        'url' => route('inventory.counts.show', $count),
                    ],
                ];
            })
            ->all();
    }

    /**
     * Resolve Sales Order task resources.
     *
     * @param Collection<int, Task> $tasks
     * @return array<int, array{domainName: string, label: string, dueDate: string|null, url: string}>
     */
    private function salesOrderTaskResources(User $user, Collection $tasks, int $tenantId): array
    {
        if ($tasks->isEmpty()) {
            return [];
        }

        $orders = SalesOrder::query()
            ->where('tenant_id', $tenantId)
            ->whereIn('id', $tasks->pluck('domain_record_id')->unique()->all())
            ->whereNotIn('status', SalesOrder::terminalStatuses())
            ->get()
            ->keyBy('id');

        return $tasks
            ->mapWithKeys(function (Task $task) use ($orders, $user): array {
                $order = $orders->get($task->domain_record_id);

                if (
                    ! $order
                    || ! $this->canViewWorkflowResource($user, $order, 'sales', null, 'sales-sales-orders-manage')
                ) {
                    return [];
                }

                return [
                    $task->id => [
                        'domainName' => 'Sales Order',
                        'label' => 'Sales Order #' . $order->id,
                        'dueDate' => $order->order_date?->format('M j, Y'),
                        'url' => route('sales.orders.show', $order),
                    ],
                ];
            })
            ->all();
    }

    /**
     * Resolve Purchase Order task resources.
     *
     * @param Collection<int, Task> $tasks
     * @return array<int, array{domainName: string, label: string, dueDate: string|null, url: string}>
     */
    private function purchaseOrderTaskResources(User $user, Collection $tasks, int $tenantId): array
    {
        if ($tasks->isEmpty()) {
            return [];
        }

        $orders = PurchaseOrder::query()
            ->where('tenant_id', $tenantId)
            ->whereIn('id', $tasks->pluck('domain_record_id')->unique()->all())
            ->whereNotIn('status', [PurchaseOrder::STATUS_COMPLETED, PurchaseOrder::STATUS_CANCELLED])
            ->whereNull('workflow_cancelled_at')
            ->get()
            ->keyBy('id');

        return $tasks
            ->mapWithKeys(function (Task $task) use ($orders, $user): array {
                $order = $orders->get($task->domain_record_id);

                if (
                    ! $order
                    || ! $this->canViewWorkflowResource(
                        $user,
                        $order,
                        'purchasing',
                        $order->assigned_to_user_id,
                        'purchasing-purchase-orders-create'
                    )
                ) {
                    return [];
                }

                return [
                    $task->id => [
                        'domainName' => 'Purchase Order',
                        'label' => $order->po_number ?: 'Purchase Order #' . $order->id,
                        'dueDate' => $order->order_date?->format('M j, Y'),
                        'url' => route('purchasing.orders.show', $order),
                    ],
                ];
            })
            ->all();
    }

    /**
     * Convert task status values to dashboard labels.
     */
    private function statusLabel(string $status): string
    {
        return match ($status) {
            Task::STATUS_OPEN => 'Open',
            Task::STATUS_COMPLETED => 'Completed',
            default => ucfirst($status),
        };
    }

    /**
     * Return the display label for inventory count dashboard rows.
     */
    private function inventoryCountLabel(InventoryCount $inventoryCount): string
    {
        $name = trim((string) $inventoryCount->name);

        return $name !== '' ? $name : 'Inventory Count #' . $inventoryCount->id;
    }

    /**
     * Return the display label for purchase order dashboard rows.
     */
    private function purchaseOrderLabel(PurchaseOrder $purchaseOrder): string
    {
        $poNumber = trim((string) $purchaseOrder->po_number);

        return $poNumber !== '' ? 'PO ' . $poNumber : 'PO ID: ' . $purchaseOrder->id;
    }

    /**
     * Determine whether a dashboard row may link to the workflow resource.
     */
    private function canViewWorkflowResource(
        User $user,
        Model $resource,
        string $workflowDomainKey,
        ?int $responsibleUserId,
        string $permission
    ): bool {
        return Gate::forUser($user)->allows($permission)
            || app(CanViewAssignedWorkflowResourceAction::class)->execute(
                $user,
                $resource,
                $workflowDomainKey,
                $responsibleUserId
            );
    }
}
