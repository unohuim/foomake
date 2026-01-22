<?php

namespace App\Http\Controllers;

use App\Models\Recipe;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Gate;

/**
 * Handle read-only recipe views.
 */
class RecipeController extends Controller
{
    /**
     * Display the recipes index.
     *
     * @return View
     */
    public function index(): View
    {
        Gate::authorize('inventory-recipes-view');

        $recipes = Recipe::query()
            ->with('item.baseUom')
            ->orderByDesc('updated_at')
            ->get();

        return view('manufacturing.recipes.index', [
            'recipes' => $recipes,
        ]);
    }

    /**
     * Display a recipe detail page.
     *
     * @param Recipe $recipe
     * @return View
     */
    public function show(Recipe $recipe): View
    {
        Gate::authorize('inventory-recipes-view');

        $recipe->load([
            'item.baseUom',
            'lines' => function ($query) {
                $query->with('item.baseUom')->orderBy('id');
            },
        ]);

        return view('manufacturing.recipes.show', [
            'recipe' => $recipe,
        ]);
    }
}
