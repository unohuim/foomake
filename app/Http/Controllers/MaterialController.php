<?php

namespace App\Http\Controllers;

use App\Models\Item;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Gate;

/**
 * Handle read-only materials listing.
 */
class MaterialController extends Controller
{
    /**
     * Display the materials index.
     */
    public function index(): View
    {
        Gate::authorize('inventory-materials-view');

        $items = Item::with('baseUom')->get();

        return view('materials.index', [
            'items' => $items,
        ]);
    }
}
