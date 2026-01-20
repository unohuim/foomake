<?php

namespace App\Http\Controllers;

use App\Models\Item;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Gate;

class InventoryController extends Controller
{
    /**
     * Display the inventory overview.
     */
    public function index(): View
    {
        Gate::authorize('inventory-adjustments-view');

        $items = Item::query()
            ->with(['baseUom', 'stockMoves'])
            ->orderBy('name')
            ->get();

        return view('inventory.index', [
            'items' => $items,
        ]);
    }
}
