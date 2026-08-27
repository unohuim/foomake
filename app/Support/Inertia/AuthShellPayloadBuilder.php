<?php

namespace App\Support\Inertia;

use App\Models\User;
use App\Navigation\NavigationEligibility;
use Illuminate\Http\Request;

/**
 * Build the authenticated Inertia shell payload shared by migrated auth routes.
 */
class AuthShellPayloadBuilder
{
    public function __construct(
        private readonly NavigationEligibility $navigationEligibility
    ) {
    }

    /**
     * Build the AuthShell payload for the current request user.
     *
     * @return array<string, mixed>
     */
    public function build(Request $request): array
    {
        $user = $request->user();

        return [
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
                'groups' => $this->navigationGroups(
                    $request,
                    $user,
                    $this->navigationEligibility->forUser($user)
                ),
                'accountItems' => $this->accountNavigationItems($request, $user),
            ],
        ];
    }

    /**
     * Build the authenticated navigation groups for the Inertia auth shell.
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
        $canAccessAdminHub = $user->can('billing-subscription-manage')
            || $user->can('system-users-manage')
            || $user->can('workflow-manage')
            || $user->can('admin-users-view');

        return array_values(array_filter([
            [
                'label' => 'Profile',
                'url' => route('profile.edit', absolute: false),
                'active' => $request->routeIs('profile.edit'),
                'enabled' => true,
            ],
            $canAccessAdminHub ? [
                'label' => 'Admin',
                'url' => route('admin.index', absolute: false),
                'active' => $request->routeIs('admin.index')
                    || $request->routeIs('admin.users.*')
                    || $request->routeIs('profile.connectors.*'),
                'enabled' => true,
            ] : null,
            $user->hasRole('super-admin') ? [
                'label' => 'Marketing',
                'url' => route('admin.marketing.index', absolute: false),
                'active' => $request->routeIs('admin.marketing.*'),
                'enabled' => true,
            ] : null,
        ]));
    }
}
