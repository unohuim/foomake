<?php

namespace App\Http\Controllers;

use App\Actions\Notes\AuthorizeNoteableAccessAction;
use App\Http\Requests\Notes\StoreNoteRequest;
use App\Models\InventoryCount;
use App\Models\MakeOrder;
use App\Models\Note;
use App\Models\PurchaseOrder;
use App\Models\SalesOrder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Shared tenant-scoped notes controller for supported resource detail pages.
 */
class NoteController extends Controller
{
    /**
     * List notes for an Inventory Count.
     */
    public function inventoryCountIndex(Request $request, InventoryCount $inventoryCount): JsonResponse
    {
        return $this->indexFor($request, $inventoryCount);
    }

    /**
     * Create a note for an Inventory Count.
     */
    public function inventoryCountStore(StoreNoteRequest $request, InventoryCount $inventoryCount): JsonResponse
    {
        return $this->storeFor($request, $inventoryCount);
    }

    /**
     * List notes for a Make Order.
     */
    public function makeOrderIndex(Request $request, MakeOrder $makeOrder): JsonResponse
    {
        return $this->indexFor($request, $makeOrder);
    }

    /**
     * Create a note for a Make Order.
     */
    public function makeOrderStore(StoreNoteRequest $request, MakeOrder $makeOrder): JsonResponse
    {
        return $this->storeFor($request, $makeOrder);
    }

    /**
     * List notes for a Purchase Order.
     */
    public function purchaseOrderIndex(Request $request, PurchaseOrder $purchaseOrder): JsonResponse
    {
        return $this->indexFor($request, $purchaseOrder);
    }

    /**
     * Create a note for a Purchase Order.
     */
    public function purchaseOrderStore(StoreNoteRequest $request, PurchaseOrder $purchaseOrder): JsonResponse
    {
        return $this->storeFor($request, $purchaseOrder);
    }

    /**
     * List notes for a Sales Order.
     */
    public function salesOrderIndex(Request $request, SalesOrder $salesOrder): JsonResponse
    {
        return $this->indexFor($request, $salesOrder);
    }

    /**
     * Create a note for a Sales Order.
     */
    public function salesOrderStore(StoreNoteRequest $request, SalesOrder $salesOrder): JsonResponse
    {
        return $this->storeFor($request, $salesOrder);
    }

    /**
     * Return tenant-scoped notes for a supported parent resource.
     */
    private function indexFor(Request $request, Model $noteable): JsonResponse
    {
        $this->authorizeNoteable($request, $noteable);

        $notes = $this->notesQuery($noteable)
            ->with('author')
            ->orderBy('created_at')
            ->orderBy('id')
            ->get();

        return response()->json([
            'data' => $notes
                ->map(fn (Note $note): array => $this->notePayload($note))
                ->values()
                ->all(),
            'meta' => [
                'system_activity_supported' => false,
            ],
        ]);
    }

    /**
     * Create a tenant-scoped note for a supported parent resource.
     */
    private function storeFor(StoreNoteRequest $request, Model $noteable): JsonResponse
    {
        $this->authorizeNoteable($request, $noteable);

        $note = Note::query()->forceCreate([
            'tenant_id' => (int) $noteable->tenant_id,
            'noteable_type' => $noteable::class,
            'noteable_id' => $noteable->getKey(),
            'author_user_id' => $request->user()->id,
            'body' => trim((string) $request->validated('body')),
            'visibility' => 'internal',
            'is_pinned' => false,
        ]);

        $note->load('author');

        return response()->json([
            'note' => $this->notePayload($note),
        ], 201);
    }

    /**
     * Authorize note access through the parent resource.
     */
    private function authorizeNoteable(Request $request, Model $noteable): void
    {
        abort_unless((int) $noteable->tenant_id === (int) $request->user()->tenant_id, 404);

        abort_unless(
            app(AuthorizeNoteableAccessAction::class)->execute($request->user(), $noteable),
            403
        );
    }

    /**
     * Build the tenant-scoped note query for a parent resource.
     */
    private function notesQuery(Model $noteable)
    {
        return Note::query()
            ->where('tenant_id', (int) $noteable->tenant_id)
            ->where('noteable_type', $noteable::class)
            ->where('noteable_id', $noteable->getKey());
    }

    /**
     * Build a note read model for AJAX and initial page payloads.
     *
     * @return array<string, mixed>
     */
    public function notePayload(Note $note): array
    {
        $note->loadMissing('author');

        return [
            'id' => $note->id,
            'tenant_id' => $note->tenant_id,
            'noteable_type' => $note->noteable_type,
            'noteable_id' => $note->noteable_id,
            'author_user_id' => $note->author_user_id,
            'author_name' => $note->author?->name ?? 'Unknown user',
            'body' => $note->body,
            'visibility' => $note->visibility,
            'is_pinned' => (bool) $note->is_pinned,
            'created_at' => $note->created_at?->toISOString(),
            'created_at_display' => $note->created_at?->diffForHumans() ?? '',
        ];
    }
}
