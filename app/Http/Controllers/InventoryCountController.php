<?php

namespace App\Http\Controllers;

use App\Actions\Inventory\PostInventoryCountAction;
use App\Models\InventoryCount;
use App\Models\InventoryCountLine;
use App\Models\Item;
use DomainException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpFoundation\Response;

class InventoryCountController extends Controller
{
    /**
     * Display a listing of inventory counts.
     */
    public function index(Request $request): Response
    {
        Gate::authorize('inventory-adjustments-view');

        $counts = InventoryCount::query()
            ->where('tenant_id', $request->user()->tenant_id)
            ->withCount('lines')
            ->orderByDesc('counted_at')
            ->get();

        return response()->view('inventory.counts.index', [
            'counts' => $counts,
        ]);
    }

    /**
     * Show a specific inventory count.
     */
    public function show(Request $request, int $inventoryCount): Response
    {
        Gate::authorize('inventory-adjustments-view');

        $count = $this->findInventoryCount($request, $inventoryCount);

        $count->load(['lines.item.baseUom']);
        $count->loadCount('lines');

        $items = Item::query()
            ->where('tenant_id', $request->user()->tenant_id)
            ->with('baseUom')
            ->orderBy('name')
            ->get();

        return response()->view('inventory.counts.show', [
            'inventoryCount' => $count,
            'items' => $items,
        ]);
    }

    /**
     * Store a new inventory count draft.
     */
    public function store(Request $request): JsonResponse
    {
        Gate::authorize('inventory-adjustments-execute');

        $validated = $request->validate([
            'counted_at' => ['required', 'date'],
            'notes' => ['nullable', 'string'],
        ]);

        $count = InventoryCount::query()->forceCreate([
            'tenant_id' => $request->user()->tenant_id,
            'counted_at' => Carbon::parse($validated['counted_at']),
            'notes' => $validated['notes'] ?? null,
        ]);

        $count->loadCount('lines');

        return response()->json([
            'count' => $this->countPayload($count),
        ], 201);
    }

    /**
     * Update an inventory count draft.
     */
    public function update(Request $request, int $inventoryCount): JsonResponse
    {
        Gate::authorize('inventory-adjustments-execute');

        $count = $this->findInventoryCount($request, $inventoryCount);

        if ($response = $this->ensureDraft($count)) {
            return $response;
        }

        $validated = $request->validate([
            'counted_at' => ['required', 'date'],
            'notes' => ['nullable', 'string'],
        ]);

        $count->counted_at = Carbon::parse($validated['counted_at']);
        $count->notes = $validated['notes'] ?? null;
        $count->save();

        $count->loadCount('lines');

        return response()->json([
            'count' => $this->countPayload($count),
        ]);
    }

    /**
     * Delete an inventory count draft.
     */
    public function destroy(Request $request, int $inventoryCount): JsonResponse
    {
        Gate::authorize('inventory-adjustments-execute');

        $count = $this->findInventoryCount($request, $inventoryCount);

        if ($response = $this->ensureDraft($count)) {
            return $response;
        }

        $count->delete();

        return response()->json([
            'deleted' => true,
        ]);
    }

    /**
     * Post an inventory count.
     */
    public function post(
        Request $request,
        int $inventoryCount,
        PostInventoryCountAction $action
    ): JsonResponse {
        Gate::authorize('inventory-adjustments-execute');

        $count = $this->findInventoryCount($request, $inventoryCount);

        if ($response = $this->ensureDraft($count)) {
            return $response;
        }

        try {
            $action->execute($count, (int) $request->user()->id);
        } catch (DomainException $e) {
            return response()->json([
                'message' => $e->getMessage(),
            ], 422);
        }

        $count->refresh();

        return response()->json([
            'count' => $this->countPayload($count),
        ]);
    }

    /**
     * Store a new inventory count line.
     */
    public function storeLine(Request $request, int $inventoryCount): JsonResponse
    {
        Gate::authorize('inventory-adjustments-execute');

        $count = $this->findInventoryCount($request, $inventoryCount);

        if ($response = $this->ensureDraft($count)) {
            return $response;
        }

        $validated = $request->validate([
            'item_id' => [
                'required',
                'integer',
                Rule::exists('items', 'id')->where('tenant_id', $request->user()->tenant_id),
            ],
            'counted_quantity' => ['required', 'string', 'regex:/^\d+(\.\d{1,6})?$/'],
            'notes' => ['nullable', 'string'],
        ]);

        $line = InventoryCountLine::query()->forceCreate([
            'tenant_id' => $request->user()->tenant_id,
            'inventory_count_id' => $count->id,
            'item_id' => $validated['item_id'],
            'counted_quantity' => $validated['counted_quantity'],
            'notes' => $validated['notes'] ?? null,
        ]);

        $line->load('item.baseUom');

        return response()->json([
            'line' => $this->linePayload($line),
        ], 201);
    }

    /**
     * Update an inventory count line.
     */
    public function updateLine(Request $request, int $inventoryCount, int $line): JsonResponse
    {
        Gate::authorize('inventory-adjustments-execute');

        $count = $this->findInventoryCount($request, $inventoryCount);

        if ($response = $this->ensureDraft($count)) {
            return $response;
        }

        $lineModel = $count->lines()
            ->where('tenant_id', $request->user()->tenant_id)
            ->whereKey($line)
            ->firstOrFail();

        $validated = $request->validate([
            'item_id' => [
                'required',
                'integer',
                Rule::exists('items', 'id')->where('tenant_id', $request->user()->tenant_id),
            ],
            'counted_quantity' => ['required', 'string', 'regex:/^\d+(\.\d{1,6})?$/'],
            'notes' => ['nullable', 'string'],
        ]);

        $lineModel->item_id = $validated['item_id'];
        $lineModel->counted_quantity = $validated['counted_quantity'];
        $lineModel->notes = $validated['notes'] ?? null;
        $lineModel->save();

        $lineModel->load('item.baseUom');

        return response()->json([
            'line' => $this->linePayload($lineModel),
        ]);
    }

    /**
     * Delete an inventory count line.
     */
    public function destroyLine(Request $request, int $inventoryCount, int $line): JsonResponse
    {
        Gate::authorize('inventory-adjustments-execute');

        $count = $this->findInventoryCount($request, $inventoryCount);

        if ($response = $this->ensureDraft($count)) {
            return $response;
        }

        $lineModel = $count->lines()
            ->where('tenant_id', $request->user()->tenant_id)
            ->whereKey($line)
            ->firstOrFail();

        $lineModel->delete();

        return response()->json([
            'deleted' => true,
        ]);
    }

    /**
     * Find a tenant-scoped inventory count or fail.
     */
    private function findInventoryCount(Request $request, int $inventoryCount): InventoryCount
    {
        return InventoryCount::query()
            ->where('tenant_id', $request->user()->tenant_id)
            ->findOrFail($inventoryCount);
    }

    /**
     * Ensure the inventory count is still draft.
     */
    private function ensureDraft(InventoryCount $inventoryCount): ?JsonResponse
    {
        if ($inventoryCount->posted_at !== null) {
            return response()->json([
                'message' => 'Inventory count is posted and cannot be modified.',
            ], 422);
        }

        return null;
    }

    /**
     * Build JSON payload for inventory counts.
     */
    private function countPayload(InventoryCount $inventoryCount): array
    {
        $inventoryCount->loadCount('lines');

        return [
            'id' => $inventoryCount->id,
            'counted_at' => $inventoryCount->counted_at->format('Y-m-d H:i'),
            'counted_at_iso' => $inventoryCount->counted_at->format('Y-m-d\TH:i'),
            'notes' => $inventoryCount->notes ?? '',
            'status' => $inventoryCount->status,
            'posted_at_display' => $inventoryCount->posted_at?->format('Y-m-d H:i'),
            'posted_at_iso' => $inventoryCount->posted_at?->format('Y-m-d\TH:i'),
            'lines_count' => $inventoryCount->lines_count,
            'show_url' => route('inventory.counts.show', $inventoryCount),
            'update_url' => route('inventory.counts.update', $inventoryCount),
            'delete_url' => route('inventory.counts.destroy', $inventoryCount),
            'post_url' => route('inventory.counts.post', $inventoryCount),
        ];
    }

    /**
     * Build JSON payload for inventory count lines.
     */
    private function linePayload(InventoryCountLine $line): array
    {
        return [
            'id' => $line->id,
            'item_id' => $line->item_id,
            'item_display' => $line->item->name . ' (' . $line->item->baseUom->symbol . ')',
            'counted_quantity' => $line->counted_quantity,
            'notes' => $line->notes ?? '',
            'update_url' => route('inventory.counts.lines.update', [
                'inventoryCount' => $line->inventory_count_id,
                'line' => $line->id,
            ]),
            'delete_url' => route('inventory.counts.lines.destroy', [
                'inventoryCount' => $line->inventory_count_id,
                'line' => $line->id,
            ]),
        ];
    }
}
