@php
    $user = auth()->user();

    $manufacturingActive = request()->routeIs('materials.*')
        || request()->routeIs('manufacturing.*')
        || request()->routeIs('inventory.*')
        || request()->routeIs('inventory.counts.*')
        || request()->routeIs('manufacturing.uom-conversions.*');
    $purchasingActive = request()->routeIs('purchasing.*');
    $salesActive = request()->routeIs('sales.*');

    $canViewPurchaseOrders = $user?->can('purchasing-purchase-orders-create') ?? false;
    $canViewSuppliers = $user?->can('purchasing-suppliers-view') ?? false;
    $canManageCustomers = $user?->can('sales-customers-manage') ?? false;
    $canManageSalesOrders = $user?->can('sales-sales-orders-manage') ?? false;
    $hasSalesOrderCustomers = $canManageSalesOrders
        ? \App\Models\Customer::query()->exists()
        : false;
    $canViewInventory = $user?->can('inventory-adjustments-view') ?? false;
    $canViewMakeOrders = $user?->can('inventory-make-orders-view') ?? false;
    $canViewMaterials = $user?->can('inventory-materials-view') ?? false;
    $canManageMaterials = $user?->can('inventory-materials-manage') ?? false;
    $canViewRecipes = $user?->can('inventory-recipes-view') ?? false;

    $showPurchasingNav = $canViewPurchaseOrders || $canViewSuppliers;
    $showSalesNav = $canManageCustomers || $canManageSalesOrders;
    $showManufacturingNav = $canViewInventory
        || $canViewMakeOrders
        || $canViewMaterials
        || $canManageMaterials
        || $canViewRecipes;
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

                            @can('sales-sales-orders-manage')
                                @if ($hasSalesOrderCustomers)
                                    <x-nav-dropdown-link :href="route('sales.orders.index')" :active="request()->routeIs('sales.orders.*')">
                                        {{ __('Orders') }}
                                    </x-nav-dropdown-link>
                                @else
                                    <span class="block w-full cursor-not-allowed rounded-xl border border-transparent px-4 py-3 text-left text-sm font-medium text-slate-500 opacity-70">
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
                                <x-nav-dropdown-link :href="route('purchasing.orders.index')" :active="request()->routeIs('purchasing.orders.*')">
                                    {{ __('Orders') }}
                                </x-nav-dropdown-link>
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
                            @can('inventory-adjustments-view')
                                <x-nav-dropdown-link :href="route('inventory.index')" :active="request()->routeIs('inventory.index')">
                                    {{ __('Inventory') }}
                                </x-nav-dropdown-link>

                                <x-nav-dropdown-link :href="route('inventory.counts.index')" :active="request()->routeIs('inventory.counts.*')">
                                    {{ __('Inventory Counts') }}
                                </x-nav-dropdown-link>
                            @endcan

                            @can('inventory-make-orders-view')
                                <x-nav-dropdown-link :href="route('manufacturing.make-orders.index')" :active="request()->routeIs('manufacturing.make-orders.*')">
                                    {{ __('Orders (Make Orders)') }}
                                </x-nav-dropdown-link>
                            @endcan

                            @can('inventory-materials-view')
                                <x-nav-dropdown-link :href="route('materials.index')" :active="request()->routeIs('materials.*')">
                                    {{ __('Materials') }}
                                </x-nav-dropdown-link>
                            @endcan

                            @can('inventory-recipes-view')
                                <x-nav-dropdown-link :href="route('manufacturing.recipes.index')" :active="request()->routeIs('manufacturing.recipes.*')">
                                    {{ __('Recipes') }}
                                </x-nav-dropdown-link>
                            @endcan

                            @can('inventory-materials-manage')
                                <x-nav-dropdown-link :href="route('manufacturing.uoms.index')" :active="request()->routeIs('manufacturing.uoms.*')">
                                    {{ __('Units of Measure') }}
                                </x-nav-dropdown-link>

                                <x-nav-dropdown-link :href="route('manufacturing.uom-conversions.index')" :active="request()->routeIs('manufacturing.uom-conversions.*')">
                                    {{ __('UoM Conversions') }}
                                </x-nav-dropdown-link>

                                <x-nav-dropdown-link :href="route('materials.uom-categories.index')" :active="request()->routeIs('materials.uom-categories.*')">
                                    {{ __('UoM Categories') }}
                                </x-nav-dropdown-link>
                            @endcan
                        </x-slot>
                    </x-nav-dropdown>
                @endif
            </div>
        </div>

        <div class="hidden items-center sm:flex">
            <x-nav-dropdown align="right">
                <x-slot name="trigger">
                    {{ $user?->name }}
                </x-slot>

                <x-slot name="content">
                    <x-nav-dropdown-link :href="route('profile.edit')" :active="request()->routeIs('profile.edit')">
                        {{ __('Profile') }}
                    </x-nav-dropdown-link>

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

                        @can('sales-sales-orders-manage')
                            @if ($hasSalesOrderCustomers)
                                <x-nav-dropdown-link :href="route('sales.orders.index')" :active="request()->routeIs('sales.orders.*')" mobile>
                                    {{ __('Orders') }}
                                </x-nav-dropdown-link>
                            @else
                                <span class="block w-full cursor-not-allowed rounded-xl border border-transparent px-4 py-3 text-left text-sm font-medium text-slate-500 opacity-70">
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
                            <x-nav-dropdown-link :href="route('purchasing.orders.index')" :active="request()->routeIs('purchasing.orders.*')" mobile>
                                {{ __('Orders') }}
                            </x-nav-dropdown-link>
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
                        @can('inventory-adjustments-view')
                            <x-nav-dropdown-link :href="route('inventory.index')" :active="request()->routeIs('inventory.index')" mobile>
                                {{ __('Inventory') }}
                            </x-nav-dropdown-link>

                            <x-nav-dropdown-link :href="route('inventory.counts.index')" :active="request()->routeIs('inventory.counts.*')" mobile>
                                {{ __('Inventory Counts') }}
                            </x-nav-dropdown-link>
                        @endcan

                        @can('inventory-make-orders-view')
                            <x-nav-dropdown-link :href="route('manufacturing.make-orders.index')" :active="request()->routeIs('manufacturing.make-orders.*')" mobile>
                                {{ __('Orders (Make Orders)') }}
                            </x-nav-dropdown-link>
                        @endcan

                        @can('inventory-materials-view')
                            <x-nav-dropdown-link :href="route('materials.index')" :active="request()->routeIs('materials.*')" mobile>
                                {{ __('Materials') }}
                            </x-nav-dropdown-link>
                        @endcan

                        @can('inventory-recipes-view')
                            <x-nav-dropdown-link :href="route('manufacturing.recipes.index')" :active="request()->routeIs('manufacturing.recipes.*')" mobile>
                                {{ __('Recipes') }}
                            </x-nav-dropdown-link>
                        @endcan

                        @can('inventory-materials-manage')
                            <x-nav-dropdown-link :href="route('manufacturing.uoms.index')" :active="request()->routeIs('manufacturing.uoms.*')" mobile>
                                {{ __('Units of Measure') }}
                            </x-nav-dropdown-link>

                            <x-nav-dropdown-link :href="route('manufacturing.uom-conversions.index')" :active="request()->routeIs('manufacturing.uom-conversions.*')" mobile>
                                {{ __('UoM Conversions') }}
                            </x-nav-dropdown-link>

                            <x-nav-dropdown-link :href="route('materials.uom-categories.index')" :active="request()->routeIs('materials.uom-categories.*')" mobile>
                                {{ __('UoM Categories') }}
                            </x-nav-dropdown-link>
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

            <form method="POST" action="{{ route('logout') }}">
                @csrf
                <x-nav-link as="button" type="submit" mobile>
                    {{ __('Log Out') }}
                </x-nav-link>
            </form>
        </div>
    </div>
</nav>
