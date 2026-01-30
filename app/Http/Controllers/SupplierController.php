<?php

namespace App\Http\Controllers;

use App\Models\Supplier;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

/**
 * Handle supplier index and creation.
 */
class SupplierController extends Controller
{
    /**
     * Display the suppliers index.
     */
    public function index(Request $request): View
    {
        Gate::authorize('purchasing-suppliers-view');

        $suppliers = Supplier::query()
            ->orderBy('company_name')
            ->get();

        $tenantCurrency = $request->user()?->tenant?->currency_code;
        $defaultCurrency = $tenantCurrency ?: (string) config('app.currency_code', 'USD');

        return view('purchasing.suppliers.index', [
            'suppliers' => $suppliers,
            'defaultCurrency' => $defaultCurrency,
        ]);
    }

    /**
     * Store a new supplier.
     */
    public function store(Request $request): JsonResponse
    {
        Gate::authorize('purchasing-suppliers-manage');

        $validated = $request->validate([
            'company_name' => ['required', 'string', 'max:255'],
            'url' => ['nullable', 'string', 'max:255'],
            'phone' => ['nullable', 'string', 'max:50'],
            'email' => ['nullable', 'email', 'max:255'],
            'currency_code' => ['nullable', 'string', 'size:3'],
        ]);

        $tenantCurrency = $request->user()?->tenant?->currency_code;
        $defaultCurrency = $tenantCurrency ?: (string) config('app.currency_code', 'USD');
        $currencyCode = $validated['currency_code'] ?? null;

        if ($currencyCode === null || $currencyCode === '') {
            $currencyCode = $defaultCurrency;
        } else {
            $currencyCode = strtoupper($currencyCode);
        }

        $supplier = Supplier::query()->create([
            'tenant_id' => $request->user()->tenant_id,
            'company_name' => $validated['company_name'],
            'url' => $validated['url'] ?? null,
            'phone' => $validated['phone'] ?? null,
            'email' => $validated['email'] ?? null,
            'currency_code' => $currencyCode,
        ]);

        return response()->json([
            'data' => [
                'id' => $supplier->id,
                'company_name' => $supplier->company_name,
                'url' => $supplier->url,
                'phone' => $supplier->phone,
                'email' => $supplier->email,
                'currency_code' => $supplier->currency_code,
            ],
        ], 201);
    }
}
