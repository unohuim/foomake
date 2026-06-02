<?php

namespace App\Http\Controllers;

use App\Actions\Workflows\CanViewAssignedWorkflowResourceAction;
use App\Models\InventoryCount;
use App\Models\MakeOrder;
use App\Models\PurchaseOrder;
use App\Models\SalesOrder;
use App\Models\Task;
use App\Models\User;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Gate;

/**
 * Render the authenticated app dashboard.
 */
class DashboardController extends Controller
{
    /**
     * Display the dashboard with the current user's assigned work.
     */
    public function __invoke(Request $request): View
    {
        $user = $request->user();
        $tenantId = (int) $user->tenant_id;
        $userId = (int) $user->id;

        return view('dashboard', [
            'todo' => [
                'responsibilities' => $this->workflowResponsibilities($user, $tenantId, $userId),
                'stageTasks' => $this->stageTasks($user, $tenantId, $userId),
            ],
        ]);
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
            $this->inventoryCountResponsibilities($user, $tenantId, $userId)
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
                'title' => 'Make Order #' . $makeOrder->id,
                'resource' => $makeOrder->outputItem?->name ?? 'Make Order #' . $makeOrder->id,
                'stage' => $makeOrder->workflowStage?->name,
                'dueDate' => $makeOrder->due_date?->format('Y-m-d'),
                'status' => $makeOrder->workflowStage?->name ?? $makeOrder->status,
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
            ->map(fn (InventoryCount $inventoryCount): array => [
                'title' => 'Inventory Count #' . $inventoryCount->id,
                'resource' => 'Inventory Count #' . $inventoryCount->id,
                'stage' => $inventoryCount->workflowStage?->name,
                'dueDate' => $inventoryCount->counted_at->format('Y-m-d'),
                'status' => $inventoryCount->workflowStage?->name
                    ?? ($inventoryCount->posted_at === null ? 'Draft' : 'Posted'),
                'url' => route('inventory.counts.show', $inventoryCount),
            ])
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
                    'resource' => $resource['label'],
                    'stage' => $task->workflowStage?->name,
                    'dueDate' => $resource['dueDate'],
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
     * @return array<int, array{label: string, dueDate: string|null, url: string}>
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
     * @return array<int, array{label: string, dueDate: string|null, url: string}>
     */
    private function makeOrderTaskResources(User $user, Collection $tasks, int $tenantId): array
    {
        if ($tasks->isEmpty()) {
            return [];
        }

        $makeOrders = MakeOrder::query()
            ->where('tenant_id', $tenantId)
            ->whereIn('id', $tasks->pluck('domain_record_id')->unique()->all())
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
                        'label' => 'Make Order #' . $makeOrder->id,
                        'dueDate' => $makeOrder->due_date?->format('Y-m-d'),
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
     * @return array<int, array{label: string, dueDate: string|null, url: string}>
     */
    private function inventoryCountTaskResources(User $user, Collection $tasks, int $tenantId): array
    {
        if ($tasks->isEmpty()) {
            return [];
        }

        $counts = InventoryCount::query()
            ->where('tenant_id', $tenantId)
            ->whereIn('id', $tasks->pluck('domain_record_id')->unique()->all())
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
                        'label' => 'Inventory Count #' . $count->id,
                        'dueDate' => $count->counted_at->format('Y-m-d'),
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
     * @return array<int, array{label: string, dueDate: string|null, url: string}>
     */
    private function salesOrderTaskResources(User $user, Collection $tasks, int $tenantId): array
    {
        if ($tasks->isEmpty()) {
            return [];
        }

        $orders = SalesOrder::query()
            ->where('tenant_id', $tenantId)
            ->whereIn('id', $tasks->pluck('domain_record_id')->unique()->all())
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
                        'label' => 'Sales Order #' . $order->id,
                        'dueDate' => $order->order_date?->format('Y-m-d'),
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
     * @return array<int, array{label: string, dueDate: string|null, url: string}>
     */
    private function purchaseOrderTaskResources(User $user, Collection $tasks, int $tenantId): array
    {
        if ($tasks->isEmpty()) {
            return [];
        }

        $orders = PurchaseOrder::query()
            ->where('tenant_id', $tenantId)
            ->whereIn('id', $tasks->pluck('domain_record_id')->unique()->all())
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
                        null,
                        'purchasing-purchase-orders-create'
                    )
                ) {
                    return [];
                }

                return [
                    $task->id => [
                        'label' => $order->po_number ?: 'Purchase Order #' . $order->id,
                        'dueDate' => $order->order_date?->format('Y-m-d'),
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
