@php
    $user = auth()->user();
    $navigationEligibility = app(\App\Navigation\NavigationEligibility::class)->forUser($user);

    $manufacturingActive = request()->routeIs('manufacturing.make-orders.*')
        || request()->routeIs('manufacturing.recipes.*');
    $stockActive = request()->routeIs('inventory.*')
        || request()->routeIs('inventory.counts.*')
        || request()->routeIs('materials.*')
        || request()->routeIs('manufacturing.uoms.*')
        || request()->routeIs('manufacturing.uom-conversions.*')
        || request()->routeIs('materials.uom-categories.*');
    $purchasingActive = request()->routeIs('purchasing.*');
    $salesActive = request()->routeIs('sales.*');
    $profileActive = request()->routeIs('profile.*') || request()->routeIs('admin.workflows.*') || request()->routeIs('admin.users.*');

    $canViewPurchaseOrders = $user?->can('purchasing-purchase-orders-create') ?? false;
    $canViewSuppliers = $user?->can('purchasing-suppliers-view') ?? false;
    $canManageCustomers = $user?->can('sales-customers-manage') ?? false;
    $canManageSalesOrders = $user?->can('sales-sales-orders-manage') ?? false;
    $canViewProducts = $user?->can('inventory-products-view') ?? false;
    $canManageProducts = $user?->can('inventory-products-manage') ?? false;
    $canOpenSalesOrders = $navigationEligibility['salesOrdersEnabled'] ?? false;
    $canManageSystemUsers = $user?->can('system-users-manage') ?? false;
    $canViewAdminUsers = $user?->can('admin-users-view') ?? false;
    $canManageWorkflows = $user?->can('workflow-manage') ?? false;
    $canOpenPurchaseOrders = $navigationEligibility['purchaseOrdersEnabled'] ?? false;
    $canViewStockInventory = ($user?->can('inventory-stock-view') ?? false)
        || ($user?->can('inventory-adjustments-view') ?? false);
    $canViewInventoryCounts = ($user?->can('inventory-adjustments-view') ?? false)
        || ($user?->can('inventory-adjustments-execute') ?? false);
    $canViewMakeOrders = $user?->can('inventory-make-orders-view') ?? false;
    $canOpenMakeOrders = $navigationEligibility['makeOrdersEnabled'] ?? false;
    $canViewMaterials = $user?->can('inventory-materials-view') ?? false;
    $canManageMaterials = $user?->can('inventory-materials-manage') ?? false;
    $canViewRecipes = $user?->can('inventory-recipes-view') ?? false;

    $showPurchasingNav = $canViewPurchaseOrders || $canViewSuppliers;
    $showSalesNav = $canManageCustomers || $canManageSalesOrders || $canViewProducts || $canManageProducts;
    $showManufacturingNav = $canViewMakeOrders || $canViewRecipes;
    $showStockNav = $canViewStockInventory || $canViewInventoryCounts || $canViewMaterials || $canManageMaterials;
@endphp

<nav x-data="{ open: false }" class="border-b border-slate-800 bg-slate-950 shadow-lg shadow-slate-950/20">
    <div class="mx-auto flex h-16 max-w-7xl items-center justify-between px-4 sm:px-6 lg:px-8">
        <div class="flex items-center gap-3">
            <a href="{{ route('dashboard') }}" class="flex items-center rounded-full border border-transparent p-2 transition duration-200 ease-out hover:border-slate-700 hover:bg-slate-900/80">
                <x-application-logo class="block h-8 w-auto fill-current text-white" />
            </a>

            <div class="hidden items-center gap-2 sm:flex">
                <x-nav-link :href="route('dashboard')" :active="request()->routeIs('dashboard')">
                    {{ __('Dashboard') }}
                </x-nav-link>

                @if ($showSalesNav)
                    <x-nav-dropdown :active="$salesActive" align="left" data-nav-dropdown-trigger="sales">
                        <x-slot name="trigger">
                            {{ __('Sales') }}
                        </x-slot>

                        <x-slot name="content">
                            @can('sales-customers-manage')
                                <x-nav-dropdown-link :href="route('sales.customers.index')" :active="request()->routeIs('sales.customers.*')">
                                    {{ __('Customers') }}
                                </x-nav-dropdown-link>
                            @endcan

                            @if ($canViewProducts || $canManageProducts)
                                <x-nav-dropdown-link :href="route('sales.products.index')" :active="request()->routeIs('sales.products.*')">
                                    {{ __('Products') }}
                                </x-nav-dropdown-link>
                            @endif

                            @can('sales-sales-orders-manage')
                                @if ($canOpenSalesOrders)
                                    <x-nav-dropdown-link
                                        :href="route('sales.orders.index')"
                                        :active="request()->routeIs('sales.orders.*')"
                                        data-sales-orders-nav-link="desktop"
                                        data-nav-eligibility-key="salesOrdersEnabled"
                                        data-nav-href="{{ route('sales.orders.index') }}"
                                        data-nav-label="Orders"
                                        data-nav-active="{{ request()->routeIs('sales.orders.*') ? 'true' : 'false' }}"
                                    >
                                        {{ __('Orders') }}
                                    </x-nav-dropdown-link>
                                @else
                                    <span
                                        class="block w-full cursor-not-allowed rounded-xl border border-transparent px-4 py-3 text-left text-sm font-medium text-slate-500 opacity-70"
                                        data-sales-orders-nav-disabled="desktop"
                                        data-nav-eligibility-key="salesOrdersEnabled"
                                        data-nav-label="Orders"
                                        data-nav-active="{{ request()->routeIs('sales.orders.*') ? 'true' : 'false' }}"
                                    >
                                        {{ __('Orders') }}
                                    </span>
                                @endif
                            @endcan
                        </x-slot>
                    </x-nav-dropdown>
                @endif

                @if ($showPurchasingNav)
                    <x-nav-dropdown :active="$purchasingActive" align="left" data-nav-dropdown-trigger="purchasing">
                        <x-slot name="trigger">
                            {{ __('Purchasing') }}
                        </x-slot>

                        <x-slot name="content">
                            @can('purchasing-purchase-orders-create')
                                @if ($canOpenPurchaseOrders)
                                    <x-nav-dropdown-link
                                        :href="route('purchasing.orders.index')"
                                        :active="request()->routeIs('purchasing.orders.*')"
                                        data-purchase-orders-nav-link="desktop"
                                        data-nav-eligibility-key="purchaseOrdersEnabled"
                                        data-nav-href="{{ route('purchasing.orders.index') }}"
                                        data-nav-label="Orders"
                                        data-nav-active="{{ request()->routeIs('purchasing.orders.*') ? 'true' : 'false' }}"
                                    >
                                        {{ __('Orders') }}
                                    </x-nav-dropdown-link>
                                @else
                                    <span
                                        class="block w-full cursor-not-allowed rounded-xl border border-transparent px-4 py-3 text-left text-sm font-medium text-slate-500 opacity-70"
                                        data-purchase-orders-nav-disabled="desktop"
                                        data-nav-eligibility-key="purchaseOrdersEnabled"
                                        data-nav-label="Orders"
                                        data-nav-active="{{ request()->routeIs('purchasing.orders.*') ? 'true' : 'false' }}"
                                    >
                                        {{ __('Orders') }}
                                    </span>
                                @endif
                            @endcan

                            @can('purchasing-suppliers-view')
                                <x-nav-dropdown-link :href="route('purchasing.suppliers.index')" :active="request()->routeIs('purchasing.suppliers.*')">
                                    {{ __('Suppliers') }}
                                </x-nav-dropdown-link>
                            @endcan
                        </x-slot>
                    </x-nav-dropdown>
                @endif

                @if ($showManufacturingNav)
                    <x-nav-dropdown :active="$manufacturingActive" align="left" data-nav-dropdown-trigger="manufacturing">
                        <x-slot name="trigger">
                            {{ __('Manufacturing') }}
                        </x-slot>

                        <x-slot name="content">
                            @can('inventory-make-orders-view')
                                @if ($canOpenMakeOrders)
                                    <x-nav-dropdown-link
                                        :href="route('manufacturing.make-orders.index')"
                                        :active="request()->routeIs('manufacturing.make-orders.*')"
                                        data-make-orders-nav-link="desktop"
                                        data-nav-eligibility-key="makeOrdersEnabled"
                                        data-nav-href="{{ route('manufacturing.make-orders.index') }}"
                                        data-nav-label="Make Orders"
                                        data-nav-active="{{ request()->routeIs('manufacturing.make-orders.*') ? 'true' : 'false' }}"
                                    >
                                        {{ __('Make Orders') }}
                                    </x-nav-dropdown-link>
                                @else
                                    <span
                                        class="block w-full cursor-not-allowed rounded-xl border border-transparent px-4 py-3 text-left text-sm font-medium text-slate-500 opacity-70"
                                        data-make-orders-nav-disabled="desktop"
                                        data-nav-eligibility-key="makeOrdersEnabled"
                                        data-nav-label="Make Orders"
                                        data-nav-active="{{ request()->routeIs('manufacturing.make-orders.*') ? 'true' : 'false' }}"
                                    >
                                        {{ __('Make Orders') }}
                                    </span>
                                @endif
                            @endcan

                            @can('inventory-recipes-view')
                                <x-nav-dropdown-link :href="route('manufacturing.recipes.index')" :active="request()->routeIs('manufacturing.recipes.*')">
                                    {{ __('Recipes') }}
                                </x-nav-dropdown-link>
                            @endcan
                        </x-slot>
                    </x-nav-dropdown>
                @endif

                @if ($showStockNav)
                    <x-nav-dropdown :active="$stockActive" align="left" data-nav-dropdown-trigger="stock">
                        <x-slot name="trigger">
                            {{ __('Stock') }}
                        </x-slot>

                        <x-slot name="content">
                            @if ($canViewStockInventory)
                                <x-nav-dropdown-link :href="route('inventory.index')" :active="request()->routeIs('inventory.index')">
                                    {{ __('Inventory') }}
                                </x-nav-dropdown-link>
                            @endif

                            @if ($canViewInventoryCounts)
                                <x-nav-dropdown-link :href="route('inventory.counts.index')" :active="request()->routeIs('inventory.counts.*')">
                                    {{ __('Inventory Counts') }}
                                </x-nav-dropdown-link>
                            @endif

                            @if ($canViewMaterials || $canManageMaterials)
                                <x-nav-dropdown-link :href="route('materials.index')" :active="request()->routeIs('materials.*') && !request()->routeIs('materials.uom-categories.*')">
                                    {{ __('Materials') }}
                                </x-nav-dropdown-link>
                            @endif

                            @can('inventory-materials-manage')
                                <div
                                    class="mt-2 rounded-2xl border border-slate-700/70 bg-slate-950/70 p-2"
                                    data-stock-uom-section="desktop"
                                    x-data="{ open: false }"
                                >
                                    <button
                                        type="button"
                                        class="flex w-full items-center justify-between rounded-xl px-3 py-2 text-left text-xs font-semibold uppercase tracking-[0.18em] text-slate-400 transition hover:bg-slate-800/60 hover:text-slate-200"
                                        x-on:click="open = !open"
                                        x-bind:aria-expanded="open ? 'true' : 'false'"
                                    >
                                        <span>{{ __('UoM') }}</span>
                                        <svg class="h-4 w-4 transition duration-200 ease-out" x-bind:class="open ? 'rotate-180' : ''" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                                            <path fill-rule="evenodd" d="M5.23 7.21a.75.75 0 011.06.02L10 11.168l3.71-3.938a.75.75 0 111.08 1.04l-4.25 4.512a.75.75 0 01-1.08 0L5.21 8.27a.75.75 0 01.02-1.06z" clip-rule="evenodd" />
                                        </svg>
                                    </button>

                                    <div class="mt-2 space-y-2" x-show="open" x-cloak>
                                        <x-nav-dropdown-link :href="route('materials.uom-categories.index')" :active="request()->routeIs('materials.uom-categories.*')">
                                            {{ __('UoM Categories') }}
                                        </x-nav-dropdown-link>

                                        <x-nav-dropdown-link :href="route('manufacturing.uoms.index')" :active="request()->routeIs('manufacturing.uoms.*')">
                                            {{ __('Units of Measure') }}
                                        </x-nav-dropdown-link>

                                        <x-nav-dropdown-link :href="route('manufacturing.uom-conversions.index')" :active="request()->routeIs('manufacturing.uom-conversions.*')">
                                            {{ __('UoM Conversions') }}
                                        </x-nav-dropdown-link>
                                    </div>
                                </div>
                            @endcan
                        </x-slot>
                    </x-nav-dropdown>
                @endif
            </div>
        </div>

        <div class="hidden items-center sm:flex">
            <x-nav-dropdown align="right" :active="$profileActive">
                <x-slot name="trigger">
                    {{ $user?->name }}
                </x-slot>

                <x-slot name="content">
                    <x-nav-dropdown-link :href="route('profile.edit')" :active="request()->routeIs('profile.edit')">
                        {{ __('Profile') }}
                    </x-nav-dropdown-link>

                    @if ($canManageSystemUsers)
                        <x-nav-dropdown-link :href="route('profile.connectors.index')" :active="request()->routeIs('profile.connectors.*')">
                            {{ __('Connectors') }}
                        </x-nav-dropdown-link>
                    @endif

                    @if ($canManageWorkflows)
                        <x-nav-dropdown-link :href="route('admin.workflows.index')" :active="request()->routeIs('admin.workflows.*')" data-profile-workflows-link="desktop">
                            {{ __('Workflows') }}
                        </x-nav-dropdown-link>
                    @endif

                    @if ($canViewAdminUsers)
                        <x-nav-dropdown-link :href="route('admin.users.index')" :active="request()->routeIs('admin.users.*')">
                            {{ __('Users') }}
                        </x-nav-dropdown-link>
                    @endif

                    <form method="POST" action="{{ route('logout') }}">
                        @csrf
                        <x-nav-dropdown-link as="button" type="submit">
                            {{ __('Log Out') }}
                        </x-nav-dropdown-link>
                    </form>
                </x-slot>
            </x-nav-dropdown>
        </div>

        <div class="flex items-center sm:hidden">
            <button
                type="button"
                class="inline-flex items-center justify-center rounded-full border border-slate-700 bg-slate-900/80 p-2 text-slate-200 transition duration-200 ease-out hover:bg-slate-800 hover:text-white"
                x-on:click="open = !open"
                x-bind:aria-expanded="open ? 'true' : 'false'"
                aria-controls="mobile-nav-panel"
            >
                <span class="sr-only">{{ __('Toggle navigation') }}</span>
                <svg class="h-5 w-5" stroke="currentColor" fill="none" viewBox="0 0 24 24" aria-hidden="true">
                    <path x-bind:class="open ? 'hidden' : 'inline-flex'" class="inline-flex" stroke-linecap="round" stroke-linejoin="round" stroke-width="1.75" d="M4 6h16M4 12h16M4 18h16" />
                    <path x-bind:class="open ? 'inline-flex' : 'hidden'" class="hidden" stroke-linecap="round" stroke-linejoin="round" stroke-width="1.75" d="M6 6l12 12M18 6L6 18" />
                </svg>
            </button>
        </div>
    </div>

    <div
        id="mobile-nav-panel"
        class="border-t border-slate-800 bg-slate-950 px-4 pb-4 pt-3 sm:hidden"
        data-nav-mobile-panel
        x-cloak
        x-show="open"
        x-transition:enter="transition ease-out duration-200"
        x-transition:enter-start="opacity-0 -translate-y-1"
        x-transition:enter-end="opacity-100 translate-y-0"
        x-transition:leave="transition ease-in duration-150"
        x-transition:leave-start="opacity-100 translate-y-0"
        x-transition:leave-end="opacity-0 -translate-y-1"
    >
        <div class="space-y-2">
            <x-nav-link :href="route('dashboard')" :active="request()->routeIs('dashboard')" mobile>
                {{ __('Dashboard') }}
            </x-nav-link>

            @if ($showSalesNav)
                <x-nav-dropdown :active="$salesActive" mobile panel-id="mobile-nav-sales" data-nav-mobile-group="sales">
                    <x-slot name="trigger">
                        {{ __('Sales') }}
                    </x-slot>

                    <x-slot name="content">
                        @can('sales-customers-manage')
                            <x-nav-dropdown-link :href="route('sales.customers.index')" :active="request()->routeIs('sales.customers.*')" mobile>
                                {{ __('Customers') }}
                            </x-nav-dropdown-link>
                        @endcan

                        @if ($canViewProducts || $canManageProducts)
                            <x-nav-dropdown-link :href="route('sales.products.index')" :active="request()->routeIs('sales.products.*')" mobile>
                                {{ __('Products') }}
                            </x-nav-dropdown-link>
                        @endif

                        @can('sales-sales-orders-manage')
                            @if ($canOpenSalesOrders)
                                <x-nav-dropdown-link
                                    :href="route('sales.orders.index')"
                                    :active="request()->routeIs('sales.orders.*')"
                                    mobile
                                    data-sales-orders-nav-link="mobile"
                                    data-nav-eligibility-key="salesOrdersEnabled"
                                    data-nav-href="{{ route('sales.orders.index') }}"
                                    data-nav-label="Orders"
                                    data-nav-active="{{ request()->routeIs('sales.orders.*') ? 'true' : 'false' }}"
                                >
                                    {{ __('Orders') }}
                                </x-nav-dropdown-link>
                            @else
                                <span
                                    class="block w-full cursor-not-allowed rounded-xl border border-transparent px-4 py-3 text-left text-sm font-medium text-slate-500 opacity-70"
                                    data-sales-orders-nav-disabled="mobile"
                                    data-nav-eligibility-key="salesOrdersEnabled"
                                    data-nav-label="Orders"
                                    data-nav-active="{{ request()->routeIs('sales.orders.*') ? 'true' : 'false' }}"
                                >
                                    {{ __('Orders') }}
                                </span>
                            @endif
                        @endcan
                    </x-slot>
                </x-nav-dropdown>
            @endif

            @if ($showPurchasingNav)
                <x-nav-dropdown :active="$purchasingActive" mobile panel-id="mobile-nav-purchasing" data-nav-mobile-group="purchasing">
                    <x-slot name="trigger">
                        {{ __('Purchasing') }}
                    </x-slot>

                    <x-slot name="content">
                        @can('purchasing-purchase-orders-create')
                            @if ($canOpenPurchaseOrders)
                                <x-nav-dropdown-link
                                    :href="route('purchasing.orders.index')"
                                    :active="request()->routeIs('purchasing.orders.*')"
                                    mobile
                                    data-purchase-orders-nav-link="mobile"
                                    data-nav-eligibility-key="purchaseOrdersEnabled"
                                    data-nav-href="{{ route('purchasing.orders.index') }}"
                                    data-nav-label="Orders"
                                    data-nav-active="{{ request()->routeIs('purchasing.orders.*') ? 'true' : 'false' }}"
                                >
                                    {{ __('Orders') }}
                                </x-nav-dropdown-link>
                            @else
                                <span
                                    class="block w-full cursor-not-allowed rounded-xl border border-transparent px-4 py-3 text-left text-sm font-medium text-slate-500 opacity-70"
                                    data-purchase-orders-nav-disabled="mobile"
                                    data-nav-eligibility-key="purchaseOrdersEnabled"
                                    data-nav-label="Orders"
                                    data-nav-active="{{ request()->routeIs('purchasing.orders.*') ? 'true' : 'false' }}"
                                >
                                    {{ __('Orders') }}
                                </span>
                            @endif
                        @endcan

                        @can('purchasing-suppliers-view')
                            <x-nav-dropdown-link :href="route('purchasing.suppliers.index')" :active="request()->routeIs('purchasing.suppliers.*')" mobile>
                                {{ __('Suppliers') }}
                            </x-nav-dropdown-link>
                        @endcan
                    </x-slot>
                </x-nav-dropdown>
            @endif

            @if ($showManufacturingNav)
                <x-nav-dropdown :active="$manufacturingActive" mobile panel-id="mobile-nav-manufacturing" data-nav-mobile-group="manufacturing">
                    <x-slot name="trigger">
                        {{ __('Manufacturing') }}
                    </x-slot>

                    <x-slot name="content">
                        @can('inventory-make-orders-view')
                            @if ($canOpenMakeOrders)
                                <x-nav-dropdown-link
                                    :href="route('manufacturing.make-orders.index')"
                                    :active="request()->routeIs('manufacturing.make-orders.*')"
                                    mobile
                                    data-make-orders-nav-link="mobile"
                                    data-nav-eligibility-key="makeOrdersEnabled"
                                    data-nav-href="{{ route('manufacturing.make-orders.index') }}"
                                    data-nav-label="Make Orders"
                                    data-nav-active="{{ request()->routeIs('manufacturing.make-orders.*') ? 'true' : 'false' }}"
                                >
                                    {{ __('Make Orders') }}
                                </x-nav-dropdown-link>
                            @else
                                <span
                                    class="block w-full cursor-not-allowed rounded-xl border border-transparent px-4 py-3 text-left text-sm font-medium text-slate-500 opacity-70"
                                    data-make-orders-nav-disabled="mobile"
                                    data-nav-eligibility-key="makeOrdersEnabled"
                                    data-nav-label="Make Orders"
                                    data-nav-active="{{ request()->routeIs('manufacturing.make-orders.*') ? 'true' : 'false' }}"
                                >
                                    {{ __('Make Orders') }}
                                </span>
                            @endif
                        @endcan

                        @can('inventory-recipes-view')
                            <x-nav-dropdown-link :href="route('manufacturing.recipes.index')" :active="request()->routeIs('manufacturing.recipes.*')" mobile>
                                {{ __('Recipes') }}
                            </x-nav-dropdown-link>
                        @endcan
                    </x-slot>
                </x-nav-dropdown>
            @endif

            @if ($showStockNav)
                <x-nav-dropdown :active="$stockActive" mobile panel-id="mobile-nav-stock" data-nav-mobile-group="stock">
                    <x-slot name="trigger">
                        {{ __('Stock') }}
                    </x-slot>

                    <x-slot name="content">
                        @if ($canViewStockInventory)
                            <x-nav-dropdown-link :href="route('inventory.index')" :active="request()->routeIs('inventory.index')" mobile>
                                {{ __('Inventory') }}
                            </x-nav-dropdown-link>
                        @endif

                        @if ($canViewInventoryCounts)
                            <x-nav-dropdown-link :href="route('inventory.counts.index')" :active="request()->routeIs('inventory.counts.*')" mobile>
                                {{ __('Inventory Counts') }}
                            </x-nav-dropdown-link>
                        @endif

                        @if ($canViewMaterials || $canManageMaterials)
                            <x-nav-dropdown-link :href="route('materials.index')" :active="request()->routeIs('materials.*') && !request()->routeIs('materials.uom-categories.*')" mobile>
                                {{ __('Materials') }}
                            </x-nav-dropdown-link>
                        @endif

                        @can('inventory-materials-manage')
                            <div
                                class="mt-2 rounded-2xl border border-slate-700/70 bg-slate-950/70 p-2"
                                data-stock-uom-section="mobile"
                                x-data="{ open: false }"
                            >
                                <button
                                    type="button"
                                    class="flex w-full items-center justify-between rounded-xl px-3 py-2 text-left text-xs font-semibold uppercase tracking-[0.18em] text-slate-400 transition hover:bg-slate-800/60 hover:text-slate-200"
                                    x-on:click="open = !open"
                                    x-bind:aria-expanded="open ? 'true' : 'false'"
                                >
                                    <span>{{ __('UoM') }}</span>
                                    <svg class="h-4 w-4 transition duration-200 ease-out" x-bind:class="open ? 'rotate-180' : ''" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                                        <path fill-rule="evenodd" d="M5.23 7.21a.75.75 0 011.06.02L10 11.168l3.71-3.938a.75.75 0 111.08 1.04l-4.25 4.512a.75.75 0 01-1.08 0L5.21 8.27a.75.75 0 01.02-1.06z" clip-rule="evenodd" />
                                    </svg>
                                </button>

                                <div class="mt-2 space-y-2" x-show="open" x-cloak>
                                    <x-nav-dropdown-link :href="route('materials.uom-categories.index')" :active="request()->routeIs('materials.uom-categories.*')" mobile>
                                        {{ __('UoM Categories') }}
                                    </x-nav-dropdown-link>

                                    <x-nav-dropdown-link :href="route('manufacturing.uoms.index')" :active="request()->routeIs('manufacturing.uoms.*')" mobile>
                                        {{ __('Units of Measure') }}
                                    </x-nav-dropdown-link>

                                    <x-nav-dropdown-link :href="route('manufacturing.uom-conversions.index')" :active="request()->routeIs('manufacturing.uom-conversions.*')" mobile>
                                        {{ __('UoM Conversions') }}
                                    </x-nav-dropdown-link>
                                </div>
                            </div>
                        @endcan
                    </x-slot>
                </x-nav-dropdown>
            @endif
        </div>

        <div class="mt-4 rounded-2xl border border-slate-800 bg-slate-900/70 px-4 py-3">
            <p class="text-sm font-medium text-white">{{ $user?->name }}</p>
            <p class="mt-1 text-xs text-slate-400">{{ $user?->email }}</p>
        </div>

        <div class="mt-3 space-y-2">
            <x-nav-link :href="route('profile.edit')" :active="request()->routeIs('profile.edit')" mobile>
                {{ __('Profile') }}
            </x-nav-link>

            @if ($canManageSystemUsers)
                <x-nav-link :href="route('profile.connectors.index')" :active="request()->routeIs('profile.connectors.*')" mobile>
                    {{ __('Connectors') }}
                </x-nav-link>
            @endif

            @if ($canManageWorkflows)
                <x-nav-link :href="route('admin.workflows.index')" :active="request()->routeIs('admin.workflows.*')" mobile data-profile-workflows-link="mobile">
                    {{ __('Workflows') }}
                </x-nav-link>
            @endif

            @if ($canViewAdminUsers)
                <x-nav-link :href="route('admin.users.index')" :active="request()->routeIs('admin.users.*')" mobile>
                    {{ __('Users') }}
                </x-nav-link>
            @endif

            <form method="POST" action="{{ route('logout') }}">
                @csrf
                <x-nav-link as="button" type="submit" mobile>
                    {{ __('Log Out') }}
                </x-nav-link>
            </form>
        </div>
    </div>
</nav>
