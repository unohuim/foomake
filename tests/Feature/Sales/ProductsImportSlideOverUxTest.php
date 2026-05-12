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
    $this->inventorySource = file_get_contents(base_path('docs/ARCHITECTURE_INVENTORY.md'));
    $this->architectureSource = file_get_contents(base_path('docs/architecture/ui/ImportSlideOverPreviewPattern.yaml'));
    $this->importModulePath = base_path('resources/js/lib/import-module.js');
    $this->importModuleSource = file_exists($this->importModulePath)
        ? file_get_contents($this->importModulePath)
        : '';
});

it('1. products page still renders the import panel root after the extraction', function () {
    $tenant = ($this->makeTenant)();
    $user = ($this->makeUser)($tenant);

    ($this->grantPermissions)($user, ['inventory-products-view', 'inventory-products-manage']);

    $this->actingAs($user)
        ->get(route('sales.products.index'))
        ->assertOk()
        ->assertSee('data-products-import-panel', false)
        ->assertSee('data-products-import-preview-search', false);
});

it('2. preview records stay hidden until an import source or mode is selected', function () {
    expect($this->bladeSource)
        ->toContain('x-show="!hasSelectedImportSource()"')
        ->and($this->bladeSource)->toContain('x-show="hasSelectedImportSource()"')
        ->and($this->bladeSource)->toContain('data-products-import-empty-state');
});

it('3. load preview button no longer exists in the products import slide over', function () {
    expect($this->bladeSource)
        ->not->toContain('Load Preview')
        ->and($this->bladeSource)->not->toContain("x-on:click=\"loadPreview()\"");
});

it('4. the shared import module file exists for delegated import behavior', function () {
    expect(file_exists($this->importModulePath))->toBeTrue();
});

it('5. sales products page module delegates import behavior to the shared import module', function () {
    expect($this->pageModuleSource)
        ->toContain("import { createImportModule } from '../lib/import-module';")
        ->and($this->pageModuleSource)->toContain('createImportModule(');
});

it('6. woo commerce source selection auto loads preview in the shared import module', function () {
    expect($this->importModuleSource)
        ->toContain('handleSourceChange()')
        ->and($this->importModuleSource)->toContain('this.loadPreview({')
        ->and($this->importModuleSource)->toContain("config.labels?.loadingPreviewExternal || 'Loading WooCommerce preview...'")
        ->and($this->importModuleSource)->toContain('loadingMessage: loadingExternalPreviewLabel');
});

it('7. file selection auto loads preview after the file is read', function () {
    expect($this->importModuleSource)
        ->toContain('async handleLocalFileChange(event)')
        ->and($this->importModuleSource)->toContain('const text = await file.text();')
        ->and($this->importModuleSource)->toContain("source: 'file-upload'")
        ->and($this->importModuleSource)->toContain("config.labels?.loadingPreviewFile || 'Loading file preview...'")
        ->and($this->importModuleSource)->toContain('loadingMessage: loadingFilePreviewLabel');
});

it('8. selecting file upload auto opens the hidden file picker', function () {
    expect($this->importModuleSource)
        ->toContain('openImportFilePicker()')
        ->and($this->importModuleSource)->toContain('this.$refs.importFileInput?.click();')
        ->and($this->importModuleSource)->toContain('if (this.isFileUploadMode()) {')
        ->and($this->importModuleSource)->toContain('this.openImportFilePicker();');
});

it('9. a selected file becomes a separate cached source option that can restore preview rows', function () {
    expect($this->importModuleSource)
        ->toContain("return this.selectedSource.startsWith('file-upload-cached:');")
        ->and($this->importModuleSource)->toContain('cachedFileSources: []')
        ->and($this->importModuleSource)->toContain('nextCachedFileSourceId: 1')
        ->and($this->importModuleSource)->toContain('restoreCachedFilePreview()')
        ->and($this->importModuleSource)->toContain('cacheCurrentFilePreviewRows(rows)')
        ->and($this->importModuleSource)->toContain('const value = `file-upload-cached:${this.nextCachedFileSourceId}`;')
        ->and($this->bladeSource)->toContain('<template x-for="fileSource in cachedFileSources" :key="fileSource.value">');
});

it('10. loading state appears during preview load', function () {
    expect($this->bladeSource)
        ->toContain('data-products-import-preview-loading')
        ->and($this->bladeSource)->toContain('x-show="isLoadingPreview"')
        ->and($this->importModuleSource)->toContain('this.isLoadingPreview = true;')
        ->and($this->importModuleSource)->toContain('this.previewLoadingMessage = loadingMessage;');
});

it('11. bulk import options accordion exists in the import panel', function () {
    expect($this->bladeSource)
        ->toContain('Bulk Import Options')
        ->and($this->bladeSource)->toContain('data-products-import-bulk-options-accordion');
});

it('12. bulk import options accordion defaults collapsed in the shared import module', function () {
    expect($this->importModuleSource)->toContain('bulkOptionsAccordionOpen: false');
});

it('13. import preview accordion exists in the import panel', function () {
    expect($this->bladeSource)
        ->toContain('Import Preview')
        ->and($this->bladeSource)->toContain('data-products-import-preview-records-accordion');
});

it('14. import preview accordion defaults open in the shared import module', function () {
    expect($this->importModuleSource)->toContain('previewRecordsAccordionOpen: true');
});

it('15. preview uses cards instead of a table', function () {
    expect($this->bladeSource)
        ->toContain('data-products-import-preview-card')
        ->and($this->bladeSource)->not->toContain('<table class="min-w-full divide-y divide-gray-100">');
});

it('16. preview cards still show status labels', function () {
    expect($this->bladeSource)
        ->toContain('x-text="previewStatusLabel(row)"')
        ->and($this->importModuleSource)->toContain("return 'Duplicate';")
        ->and($this->importModuleSource)->toContain("return row.is_active ? 'Active' : 'Inactive';");
});

it('17. show duplicates defaults off in the shared import module', function () {
    expect($this->importModuleSource)->toContain('showDuplicateRows: false');
});

it('18. duplicate rows are visually hidden instead of being removed from state', function () {
    expect($this->bladeSource)
        ->toContain('x-show="rowVisibleInPreview(row)"')
        ->and($this->bladeSource)->toContain('x-bind:aria-hidden="rowVisibleInPreview(row) ? \'false\' : \'true\'"')
        ->and($this->importModuleSource)->toContain('if (!this.showDuplicateRows && row.is_duplicate) {');
});

it('19. only the preview records area is scrollable', function () {
    expect($this->bladeSource)
        ->toContain('data-products-import-preview-scroll')
        ->and($this->bladeSource)->toContain('max-h-[32rem]')
        ->and($this->bladeSource)->toContain('overflow-y-auto')
        ->and($this->bladeSource)->not->toContain('data-products-import-preview-records-accordion overflow-y-auto');
});

it('20. select all still excludes duplicate rows by default', function () {
    expect($this->bladeSource)
        ->toContain('data-products-import-select-visible')
        ->and($this->bladeSource)->toContain('Select All')
        ->and($this->importModuleSource)->toContain('visibleSelectablePreviewRows()')
        ->and($this->importModuleSource)->toContain('this.rowVisibleInPreview(row) && !row.is_duplicate');
});

it('21. import selected submits only the currently selected visible rows', function () {
    expect($this->importModuleSource)
        ->toContain('selectedVisiblePreviewRows()')
        ->and($this->importModuleSource)->toContain('return this.previewRows.filter((row) => row.selected && this.rowVisibleInPreview(row));')
        ->and($this->importModuleSource)->toContain('const rows = this.selectedVisiblePreviewRows()');
});

it('22. preview empty state exists for no records or hidden duplicate rows', function () {
    expect($this->bladeSource)
        ->toContain('data-products-import-preview-empty-state')
        ->and($this->importModuleSource)->toContain('previewEmptyStateTitle()')
        ->and($this->importModuleSource)->toContain('previewEmptyStateMessage()');
});

it('23. the shared import module is responsible for csv parsing and normalization', function () {
    expect($this->importModuleSource)
        ->toContain('parseLocalCsv(text)')
        ->and($this->importModuleSource)->toContain('parseCsvRows(text)')
        ->and($this->importModuleSource)->toContain('csvBooleanOrNull(value)')
        ->and($this->importModuleSource)->toContain('slugify(value)');
});

it('24. the shared import module is responsible for import submission payload building', function () {
    expect($this->importModuleSource)
        ->toContain('buildImportRowPayload(row, importSource)')
        ->and($this->importModuleSource)->toContain('source: importSource')
        ->and($this->importModuleSource)->toContain('is_local_file_import: this.hasLocalFileRows')
        ->and($this->importModuleSource)->toContain('create_fulfillment_recipes: this.createFulfillmentRecipes');
});

it('25. the sales products page module no longer owns csv parsing internals directly', function () {
    expect($this->pageModuleSource)
        ->not->toContain('parseLocalCsv(text)')
        ->and($this->pageModuleSource)->not->toContain('parseCsvRows(text)')
        ->and($this->pageModuleSource)->not->toContain('csvBooleanOrNull(value)')
        ->and($this->pageModuleSource)->not->toContain('cacheCurrentFilePreviewRows(rows)');
});

it('26. architecture yaml documents the reusable import slide over preview pattern', function () {
    expect($this->architectureSource)
        ->toContain('name: Import Slide-Over Preview Pattern')
        ->and($this->architectureSource)->toContain('auto-preview')
        ->and($this->architectureSource)->toContain('duplicate rows must remain in DOM state')
        ->and($this->architectureSource)->toContain('only scrollable region');
});

it('27. architecture inventory mentions the reusable import slide over preview pattern', function () {
    expect($this->inventorySource)
        ->toContain('Import Slide-Over Preview Pattern')
        ->and($this->inventorySource)->toContain('Preview Records accordion')
        ->and($this->inventorySource)->toContain('duplicate rows remain in DOM state');
});
