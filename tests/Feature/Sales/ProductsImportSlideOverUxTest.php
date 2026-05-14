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

    $this->bladeSource = file_get_contents(base_path('resources/views/sales/products/index.blade.php'));
    $this->pageModuleSource = file_get_contents(base_path('resources/js/pages/sales-products-index.js'));
    $this->importModulePath = base_path('resources/js/lib/import-module.js');
    $this->importModuleSource = file_exists($this->importModulePath)
        ? file_get_contents($this->importModulePath)
        : '';
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

it('2. products blade keeps the page root import config contract only', function () {
    expect($this->bladeSource)
        ->toContain('data-import-config=')
        ->and($this->bladeSource)->not->toContain('data-products-import-panel')
        ->and($this->bladeSource)->not->toContain('data-products-import-preview-card');
});

it('3. products blade no longer contains import close button wiring', function () {
    expect($this->bladeSource)
        ->not->toContain('x-on:click="closeImportPanel()"')
        ->and($this->bladeSource)->not->toContain('x-on:change="handleSourceChange()"')
        ->and($this->bladeSource)->not->toContain('x-on:click="submitImport()"');
});

it('4. the shared import module file exists', function () {
    expect(file_exists($this->importModulePath))->toBeTrue();
});

it('5. products page module delegates import behavior to the shared import module', function () {
    expect($this->pageModuleSource)
        ->toContain("import { createImportModule } from '../lib/import-module';")
        ->and($this->pageModuleSource)->toContain('const importModule = createImportModule(')
        ->and($this->pageModuleSource)->toContain('importModule.mount(rootEl);');
});

it('6. products page module still wires the shared toolbar import handler', function () {
    expect($this->pageModuleSource)
        ->toContain("importHandler: 'openImportPanel()'")
        ->and($this->pageModuleSource)->toContain("import: 'openImportPanel()'");
});

it('7. products page module does not contain page local import parsing adapters', function () {
    expect($this->pageModuleSource)
        ->not->toContain('parseLocalRows:')
        ->and($this->pageModuleSource)->not->toContain('normalizePreviewRow:')
        ->and($this->pageModuleSource)->not->toContain('buildImportRowPayload:')
        ->and($this->pageModuleSource)->not->toContain('buildSubmitBody:');
});

it('8. shared import module renders the shared panel root', function () {
    expect($this->importModuleSource)
        ->toContain('data-shared-import-panel')
        ->and($this->importModuleSource)->toContain('data-shared-import-root');
});

it('9. shared import module renders the shared file input controls', function () {
    expect($this->importModuleSource)
        ->toContain('data-shared-import-file-input')
        ->and($this->importModuleSource)->toContain('type="file"')
        ->and($this->importModuleSource)->toContain('accept=".csv,text/csv"');
});

it('10. shared import module renders the shared empty state', function () {
    expect($this->importModuleSource)
        ->toContain('data-shared-import-empty-state')
        ->and($this->importModuleSource)->toContain('Choose an import source');
});

it('11. shared import module renders the bulk options accordion', function () {
    expect($this->importModuleSource)
        ->toContain('data-shared-import-bulk-options-accordion')
        ->and($this->importModuleSource)->toContain('Bulk Import Options');
});

it('12. shared import module renders the preview accordion', function () {
    expect($this->importModuleSource)
        ->toContain('data-shared-import-preview-records-accordion')
        ->and($this->importModuleSource)->toContain('Import Preview');
});

it('13. shared import module renders preview search and duplicate controls', function () {
    expect($this->importModuleSource)
        ->toContain('data-shared-import-preview-search')
        ->and($this->importModuleSource)->toContain('data-shared-import-show-duplicates')
        ->and($this->importModuleSource)->toContain('data-shared-import-select-visible');
});

it('14. shared import module renders preview loading and empty states', function () {
    expect($this->importModuleSource)
        ->toContain('data-shared-import-preview-loading')
        ->and($this->importModuleSource)->toContain('data-shared-import-preview-empty-state')
        ->and($this->importModuleSource)->toContain('data-shared-import-preview-scroll');
});

it('15. shared import module renders preview cards', function () {
    expect($this->importModuleSource)
        ->toContain('data-shared-import-preview-card')
        ->and($this->importModuleSource)->toContain('rowValidationMessages(index)');
});

it('15a. shared import module keeps preview rows to a single compact line', function () {
    expect($this->importModuleSource)
        ->toContain('items-center justify-between gap-3')
        ->and($this->importModuleSource)->toContain('min-w-0 flex-1 truncate text-sm font-medium')
        ->and($this->importModuleSource)->toContain('shrink-0 truncate text-xs text-gray-500')
        ->and($this->importModuleSource)->not->toContain('bodyExpression')
        ->and($this->importModuleSource)->not->toContain('rounded-full px-2.5 py-1')
        ->and($this->importModuleSource)->not->toContain("mt-1 truncate text-xs text-gray-500");
});

it('16. products import no longer requires a manual load preview button', function () {
    expect($this->importModuleSource)
        ->not->toContain('Load Preview')
        ->and($this->importModuleSource)->not->toContain('x-on:click="loadPreview()"');
});

it('17. file selection still auto loads preview in the shared module', function () {
    expect($this->importModuleSource)
        ->toContain('async handleLocalFileChange(event)')
        ->and($this->importModuleSource)->toContain("source: 'file-upload'")
        ->and($this->importModuleSource)->toContain('loadingMessage: loadingFilePreviewLabel');
});

it('18. woo commerce source selection still auto loads preview in the shared module', function () {
    expect($this->importModuleSource)
        ->toContain('handleSourceChange()')
        ->and($this->importModuleSource)->toContain('this.loadPreview({')
        ->and($this->importModuleSource)->toContain('loadingMessage: loadingExternalPreviewLabel');
});

it('19. cached file source persistence remains in the shared module', function () {
    expect($this->importModuleSource)
        ->toContain('cachedFileSources: []')
        ->and($this->importModuleSource)->toContain('cacheCurrentFilePreviewRows(rows)')
        ->and($this->importModuleSource)->toContain('restoreCachedFilePreview()')
        ->and($this->importModuleSource)->toContain("return this.selectedSource.startsWith('file-upload-cached:');");
});

it('20. products fallback import message remains unchanged in the shared module', function () {
    expect($this->importModuleSource)
        ->toContain("const importUnavailableMessage = messages.importUnavailable || 'Unable to import products.';")
        ->and($this->pageModuleSource)->not->toContain('importUnavailable:');
});

it('21. products preview payload building remains in the shared module defaults', function () {
    expect($this->importModuleSource)
        ->toContain('buildImportRowPayload(row, importSource)')
        ->and($this->importModuleSource)->toContain('default_price_cents: Object.prototype.hasOwnProperty.call(row, \'default_price_cents\')')
        ->and($this->importModuleSource)->toContain('image_url: Object.prototype.hasOwnProperty.call(row, \'image_url\')');
});

it('22. products submit still uses selected visible preview rows by default', function () {
    expect($this->importModuleSource)
        ->toContain('const submitSelectedVisibleRowsOnly = rowBehavior.submitSelectedVisibleRowsOnly !== false;')
        ->and($this->importModuleSource)->toContain('selectedImportRows()')
        ->and($this->importModuleSource)->toContain('return submitSelectedVisibleRowsOnly');
});

it('23. shared import module always defaults duplicate rows to hidden', function () {
    expect($this->importModuleSource)
        ->toContain('const showDuplicatesDefault = false;')
        ->and($this->importModuleSource)->not->toContain('const showDuplicatesDefault = !hideDuplicatesByDefault;');
});
