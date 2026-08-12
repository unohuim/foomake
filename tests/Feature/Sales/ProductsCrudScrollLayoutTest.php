<?php

declare(strict_types=1);

use App\Models\Permission;
use App\Models\Role;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->roleCounter = 1;

    $this->makeTenant = function (): Tenant {
        return Tenant::factory()->create([
            'tenant_name' => 'Products Crud Scroll Tenant',
        ]);
    };

    $this->makeUser = function (Tenant $tenant): User {
        return User::factory()->create([
            'tenant_id' => $tenant->id,
            'email_verified_at' => now(),
        ]);
    };

    $this->grantPermissions = function (User $user, array $slugs): void {
        foreach ($slugs as $slug) {
            $permission = Permission::query()->firstOrCreate([
                'slug' => $slug,
            ]);

            $role = Role::query()->create([
                'name' => 'products-crud-scroll-role-' . $this->roleCounter,
            ]);

            $this->roleCounter++;

            $role->permissions()->syncWithoutDetaching([$permission->id]);
            $user->roles()->syncWithoutDetaching([$role->id]);
        }
    };

    $this->bladeSource = file_get_contents(base_path('resources/js/pages/Sales/Products/Index.vue'));
    $this->rendererSource = file_get_contents(base_path('resources/js/lib/crud-card-page.js'));
    $this->pageModuleSource = file_get_contents(base_path('resources/js/pages/Sales/Products/Index.vue'));
});

it('1. products page still renders the shared crud root', function () {
    $tenant = ($this->makeTenant)();
    $user = ($this->makeUser)($tenant);

    ($this->grantPermissions)($user, ['inventory-products-view', 'inventory-products-manage']);

    $this->actingAs($user)
        ->get(route('sales.products.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page): Assert => $page
            ->component('Sales/Products/Index')
            ->has('crudConfig'));
});

it('2. products page shell uses a bounded viewport layout', function () {
    expect($this->bladeSource)
        ->toContain('bounded-height-class="h-full"')
        ->and($this->bladeSource)->toContain('<ResourceIndex');
});

it('3. the old loose py-12 wrapper is absent from the products page shell', function () {
    expect($this->bladeSource)->not->toContain('class="py-12"');
});

it('4. the products vue shell fills the available bounded height', function () {
    expect($this->bladeSource)
        ->toContain('class="flex h-full min-h-0 w-full"');
});

it('5. resource index fills the available height', function () {
    expect($this->bladeSource)
        ->toContain('bounded-height-class="h-full"');
});

it('6. the shared resource index fills the bounded page area', function () {
    $resourceIndexSource = file_get_contents(base_path('resources/js/components/ResourceIndex.vue'));

    expect($resourceIndexSource)
        ->toContain('class="resource-index-shell flex min-h-0 w-full flex-col overflow-hidden"')
        ->and($resourceIndexSource)->toContain('data-resource-index-records-scroll');
});

it('7. the shared crud renderer no longer hardcodes a fixed 36rem shell height', function () {
    expect($this->rendererSource)->not->toContain('class="h-[36rem] overflow-hidden border border-gray-100 bg-white shadow-sm" data-crud-renderer');
});

it('8. desktop products use a full height wrapper before the records scroller', function () {
    expect($this->rendererSource)
        ->toContain('class="hidden h-full min-h-0 md:block"')
        ->and($this->rendererSource)->toContain('class="flex h-full min-h-0 flex-col"');
});

it('9. desktop records container owns overflow y auto', function () {
    expect($this->rendererSource)
        ->toContain('class="min-h-0 flex-1 overflow-y-auto p-6" data-crud-records-scroll');
});

it('10. desktop toolbar remains above the records scroller', function () {
    $toolbarPosition = strpos($this->rendererSource, 'data-crud-toolbar');
    $scrollPosition = strpos($this->rendererSource, 'data-crud-records-scroll');

    expect($toolbarPosition)->not->toBeFalse()
        ->and($scrollPosition)->not->toBeFalse()
        ->and($toolbarPosition < $scrollPosition)->toBeTrue();
});

it('11. desktop toolbar itself does not own overflow y auto', function () {
    expect($this->rendererSource)
        ->toContain('data-crud-toolbar')
        ->and($this->rendererSource)->toContain('border-b border-gray-100 bg-white')
        ->and($this->rendererSource)->toContain('px-4 py-3 sm:px-6')
        ->and($this->rendererSource)->not->toContain('data-crud-toolbar overflow-y-auto');
});

it('12. desktop card grid remains inside the scrolling records region', function () {
    expect($this->rendererSource)
        ->toContain('data-crud-card-grid')
        ->and($this->rendererSource)->toContain('grid gap-4 md:grid-cols-3')
        ->and($this->rendererSource)->not->toContain('<thead');
});

it('13. mobile products use a full height wrapper before the records scroller', function () {
    expect($this->rendererSource)
        ->toContain('<div class="h-full min-h-0 md:hidden" data-crud-mobile-cards>')
        ->and($this->rendererSource)->toContain('class="flex h-full min-h-0 flex-col"');
});

it('14. mobile records container owns overflow y auto', function () {
    expect($this->rendererSource)
        ->toContain('class="min-h-0 flex-1 overflow-y-auto p-0" data-crud-records-scroll');
});

it('15. mobile toolbar remains above the records scroller', function () {
    expect($this->rendererSource)
        ->toContain('data-crud-mobile-cards')
        ->and($this->rendererSource)->toContain('data-crud-toolbar')
        ->and($this->rendererSource)->toContain('data-crud-records-scroll')
        ->and($this->rendererSource)->toContain('class="min-h-0 flex-1 overflow-y-auto p-0" data-crud-records-scroll')
        ->and($this->rendererSource)->not->toContain('data-crud-toolbar overflow-y-auto');
});

it('16. mobile toolbar itself does not own overflow y auto', function () {
    expect($this->rendererSource)
        ->toContain('data-crud-toolbar')
        ->and($this->rendererSource)->toContain('border-b border-gray-100 bg-white')
        ->and($this->rendererSource)->toContain('px-4 py-3 sm:px-6')
        ->and($this->rendererSource)->not->toContain('data-crud-toolbar overflow-y-auto');
});

it('17. desktop and mobile use the same scroll containment pattern', function () {
    expect($this->rendererSource)
        ->toContain('data-crud-toolbar')
        ->and($this->rendererSource)->toContain('class="flex h-full min-h-0 flex-col"')
        ->and($this->rendererSource)->toContain('class="min-h-0 flex-1 overflow-y-auto p-6" data-crud-records-scroll')
        ->and($this->rendererSource)->toContain('class="min-h-0 flex-1 overflow-y-auto p-0" data-crud-records-scroll');
});

it('18. toolbar order remains search export import add', function () {
    $searchPosition = strpos($this->rendererSource, 'data-crud-toolbar-search');
    $exportPosition = strpos($this->rendererSource, 'data-crud-toolbar-export-button');
    $importPosition = strpos($this->rendererSource, 'data-crud-toolbar-import-button');
    $createPosition = strpos($this->rendererSource, 'data-crud-toolbar-create-button');

    expect($searchPosition)->not->toBeFalse()
        ->and($exportPosition)->not->toBeFalse()
        ->and($importPosition)->not->toBeFalse()
        ->and($createPosition)->not->toBeFalse()
        ->and($searchPosition < $exportPosition)->toBeTrue()
        ->and($exportPosition < $importPosition)->toBeTrue()
        ->and($importPosition < $createPosition)->toBeTrue();
});

it('19. the gray gap wrapper between header and toolbar does not return', function () {
    expect($this->bladeSource)
        ->not->toContain('class="py-12"')
        ->and($this->rendererSource)->not->toContain('mt-6')
        ->and($this->rendererSource)->not->toContain('space-y-6');
});

it('20. the products vue page uses the shared resource index and card grid', function () {
    expect($this->pageModuleSource)
        ->toContain('ResourceIndex')
        ->and($this->pageModuleSource)->toContain('ResourceCardGrid');
});
