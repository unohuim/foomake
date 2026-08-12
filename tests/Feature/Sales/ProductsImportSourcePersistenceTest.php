<?php

declare(strict_types=1);

use App\Models\Permission;
use App\Models\Role;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->roleCounter = 1;

    $this->makeTenant = function (): Tenant {
        return Tenant::factory()->create([
            'tenant_name' => 'Products Import Persistence Tenant',
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
                'name' => 'products-import-persistence-role-' . $this->roleCounter,
            ]);

            $this->roleCounter++;

            $role->permissions()->syncWithoutDetaching([$permission->id]);
            $user->roles()->syncWithoutDetaching([$role->id]);
        }
    };

    $this->bladeSource = file_get_contents(base_path('resources/js/pages/Sales/Products/Index.vue'));
    $this->pageModuleSource = file_get_contents(base_path('resources/js/pages/Sales/Products/Index.vue'));
    $this->importComponentSource = file_get_contents(base_path('resources/js/components/ResourceImportDrawer.vue'));
});

it('1. products page no longer renders import panel markup server side for persistence flows', function () {
    $tenant = ($this->makeTenant)();
    $user = ($this->makeUser)($tenant);

    ($this->grantPermissions)($user, ['inventory-products-view', 'inventory-products-manage']);

    $this->actingAs($user)
        ->get(route('sales.products.index'))
        ->assertOk()
        ->assertDontSee('data-products-import-panel', false)
        ->assertDontSee('data-products-import-file-input', false)
        ->assertDontSee('data-shared-import-panel', false);
});

it('2. products import persistence is handled by the vue page and shared import drawer', function () {
    expect($this->pageModuleSource)
        ->toContain('ResourceImportDrawer')
        ->and($this->pageModuleSource)->toContain('selectedImportSource')
        ->and($this->pageModuleSource)->toContain('previewRows')
        ->and($this->importComponentSource)->toContain('v-model="localSelectedSource"');
});

it('3. local file import payload keeps the explicit file upload source shape', function () {
    expect($this->pageModuleSource)
        ->toContain('source: "file-upload"')
        ->and($this->pageModuleSource)->toContain('is_local_file_import: selectedImportSource.value === "file-upload"')
        ->and($this->pageModuleSource)->toContain('rows,');
});

it('4. switching import sources clears preview state without hidden cached source state', function () {
    expect($this->pageModuleSource)
        ->toContain('async function handleImportSourceChange(source)')
        ->and($this->pageModuleSource)->toContain('previewRows.value = []')
        ->and($this->pageModuleSource)->not->toContain('cachedFileSources')
        ->and($this->pageModuleSource)->not->toContain('file-upload-cached:');
});

it('5. sales products vue page delegates import persistence to the shared import drawer', function () {
    expect($this->pageModuleSource)
        ->toContain('ResourceImportDrawer')
        ->and($this->pageModuleSource)->toContain('submitImport')
        ->and($this->pageModuleSource)->toContain('is_local_file_import: selectedImportSource.value === "file-upload"');
});
