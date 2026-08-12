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
            'tenant_name' => 'Products Import UX Tenant',
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
                'name' => 'products-import-ux-role-' . $this->roleCounter,
            ]);

            $this->roleCounter++;

            $role->permissions()->syncWithoutDetaching([$permission->id]);
            $user->roles()->syncWithoutDetaching([$role->id]);
        }
    };

    $this->bladeSource = file_get_contents(base_path('resources/js/pages/Sales/Products/Index.vue'));
    $this->pageModuleSource = file_get_contents(base_path('resources/js/pages/Sales/Products/Index.vue'));
    $this->importModulePath = base_path('resources/js/lib/import-module.js');
    $this->importModuleSource = file_exists($this->importModulePath)
        ? file_get_contents($this->importModulePath)
        : '';
    $this->importComponentSource = file_get_contents(base_path('resources/js/components/ResourceImportDrawer.vue'));
});

it('1. products page response no longer renders import slide over markup server side', function () {
    $tenant = ($this->makeTenant)();
    $user = ($this->makeUser)($tenant);

    ($this->grantPermissions)($user, ['inventory-products-view', 'inventory-products-manage']);

    $this->actingAs($user)
        ->get(route('sales.products.index'))
        ->assertOk()
        ->assertDontSee('data-products-import-panel', false)
        ->assertDontSee('data-shared-import-panel', false);
});

it('2. products Vue page uses the shared import drawer component only', function () {
    expect($this->bladeSource)
        ->toContain('ResourceImportDrawer')
        ->and($this->bladeSource)->not->toContain('data-products-import-panel')
        ->and($this->bladeSource)->not->toContain('data-products-import-preview-card');
});

it('3. products vue page no longer contains import close button wiring', function () {
    expect($this->bladeSource)
        ->not->toContain('x-on:click="closeImportPanel()"')
        ->and($this->bladeSource)->not->toContain('x-on:change="handleSourceChange()"')
        ->and($this->bladeSource)->not->toContain('x-on:click="submitImport()"');
});

it('4. the shared import drawer component file exists', function () {
    expect(file_exists(base_path('resources/js/components/ResourceImportDrawer.vue')))->toBeTrue();
});

it('5. products vue page delegates import behavior to the shared import drawer component', function () {
    expect($this->pageModuleSource)
        ->toContain('ResourceImportDrawer')
        ->and($this->pageModuleSource)->toContain('handleImportSourceChange')
        ->and($this->pageModuleSource)->toContain('submitImport');
});

it('6. products vue page still wires the resource index import event', function () {
    expect($this->pageModuleSource)
        ->toContain('@import="openImportDrawer"')
        ->and($this->pageModuleSource)->toContain(':open="importDrawerOpen"');
});

it('7. products vue page does not contain legacy import parsing adapters', function () {
    expect($this->pageModuleSource)
        ->not->toContain('parseLocalRows:')
        ->and($this->pageModuleSource)->not->toContain('normalizePreviewRow:')
        ->and($this->pageModuleSource)->not->toContain('buildImportRowPayload:')
        ->and($this->pageModuleSource)->not->toContain('buildSubmitBody:');
});

it('8. shared import drawer renders the shared panel root', function () {
    expect($this->importComponentSource)
        ->toContain('resource-import-drawer-title')
        ->and($this->importComponentSource)->toContain('<BaseDrawer');
});

it('9. shared import drawer renders the shared file input controls', function () {
    expect($this->importComponentSource)
        ->toContain('data-resource-import-file-input')
        ->and($this->importComponentSource)->toContain('type="file"')
        ->and($this->importComponentSource)->toContain('accept=".csv,text/csv"');
});

it('10. shared import drawer renders the shared empty state', function () {
    expect($this->importComponentSource)
        ->toContain('empty-source')
        ->and($this->importComponentSource)->toContain('Choose an import source');
});

it('11. shared import drawer renders the bulk options accordion', function () {
    expect($this->importComponentSource)
        ->toContain('data-resource-import-bulk-options-accordion')
        ->and($this->importComponentSource)->toContain('Bulk Import Options')
        ->and($this->importComponentSource)->toContain('hasSource && showBulkOptions')
        ->and($this->importComponentSource)->not->toContain('No additional import options are available for this resource.');
});

it('12. shared import drawer renders the preview accordion', function () {
    expect($this->importComponentSource)
        ->toContain('data-resource-import-preview-records-accordion')
        ->and($this->importComponentSource)->toContain('Import Preview');
});

it('13. shared import drawer renders preview search and duplicate controls', function () {
    expect($this->importComponentSource)
        ->toContain('data-resource-import-preview-search')
        ->and($this->importComponentSource)->toContain('data-resource-import-show-duplicates')
        ->and($this->importComponentSource)->toContain('data-resource-import-select-visible');
});

it('14. shared import drawer renders preview loading and empty states', function () {
    expect($this->importComponentSource)
        ->toContain('preview-loading')
        ->and($this->importComponentSource)->toContain('preview-empty')
        ->and($this->importComponentSource)->toContain('max-h-[32rem] overflow-y-auto');
});

it('15. shared import drawer renders preview rows through a slot', function () {
    expect($this->importComponentSource)
        ->toContain('name="preview-rows"')
        ->and($this->pageModuleSource)->toContain('<template #preview-rows="{ rows }">');
});

it('15a. products import preview rows remain compact', function () {
    expect($this->pageModuleSource)
        ->toContain('class="flex min-h-10 items-center gap-3"')
        ->and($this->pageModuleSource)->toContain('class="min-w-0 flex-1"')
        ->and($this->pageModuleSource)->toContain('truncate text-sm font-medium')
        ->and($this->pageModuleSource)->not->toContain('bodyExpression');
});

it('16. products import no longer requires a manual load preview button', function () {
    expect($this->pageModuleSource)
        ->not->toContain('Load Preview')
        ->and($this->pageModuleSource)->not->toContain('x-on:click="loadPreview()"');
});

it('17. file selection still auto loads preview in the vue page', function () {
    expect($this->pageModuleSource)
        ->toContain('async handleLocalFileChange(event)')
        ->and($this->pageModuleSource)->toContain('source: "file-upload"')
        ->and($this->pageModuleSource)->toContain('props.importConfig.endpoints.preview');
});

it('18. woo commerce source selection still auto loads preview in the vue page', function () {
    expect($this->pageModuleSource)
        ->toContain('async function handleImportSourceChange(source)')
        ->and($this->pageModuleSource)->toContain('await loadExternalPreview(source)')
        ->and($this->pageModuleSource)->toContain('async function loadExternalPreview(source)');
});

it('19. products vue import does not introduce hidden cached file source state', function () {
    expect($this->pageModuleSource)
        ->not->toContain('cachedFileSources')
        ->and($this->pageModuleSource)->not->toContain('file-upload-cached:')
        ->and($this->pageModuleSource)->not->toContain('restoreCachedFilePreview()');
});

it('20. products fallback import message remains unchanged in the vue page', function () {
    expect($this->pageModuleSource)
        ->toContain('"Unable to import products."')
        ->and($this->pageModuleSource)->not->toContain('importUnavailable:');
});

it('21. products preview payload building remains in the page-owned import submit', function () {
    expect($this->pageModuleSource)
        ->toContain('default_price_cents: row.default_price_cents ?? null')
        ->and($this->pageModuleSource)->toContain('image_url: row.image_url ?? null')
        ->and($this->pageModuleSource)->toContain('external_source: row.external_source || selectedImportSource.value');
});

it('22. products submit still uses selected preview rows by default', function () {
    expect($this->pageModuleSource)
        ->toContain('.filter((row) => row.selected)')
        ->and($this->pageModuleSource)->toContain('Select at least one product to import.');
});

it('23. products import drawer defaults duplicate rows to hidden', function () {
    expect($this->pageModuleSource)
        ->toContain('const showDuplicateRows = ref(false);');
});
