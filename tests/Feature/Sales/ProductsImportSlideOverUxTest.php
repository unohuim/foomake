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
});

it('1. products page still renders the import panel root after the ux refactor', function () {
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

it('4. woo commerce source selection auto loads preview', function () {
    expect($this->pageModuleSource)
        ->toContain('handleSourceChange()')
        ->and($this->pageModuleSource)->toContain('this.loadPreview({')
        ->and($this->pageModuleSource)->toContain("loadingMessage: 'Loading WooCommerce preview...'");
});

it('5. file selection auto loads preview after the file is read', function () {
    expect($this->pageModuleSource)
        ->toContain('async handleLocalFileChange(event)')
        ->and($this->pageModuleSource)->toContain('const text = await file.text();')
        ->and($this->pageModuleSource)->toContain("source: 'file-upload'")
        ->and($this->pageModuleSource)->toContain("loadingMessage: 'Loading file preview...'");
});

it('5a. selecting file upload auto opens the hidden file picker', function () {
    expect($this->pageModuleSource)
        ->toContain('openImportFilePicker()')
        ->and($this->pageModuleSource)->toContain('this.$refs.importFileInput?.click();')
        ->and($this->pageModuleSource)->toContain('if (this.isFileUploadMode()) {')
        ->and($this->pageModuleSource)->toContain('this.openImportFilePicker();');
});

it('5b. a selected file becomes a separate cached source option that can restore preview rows', function () {
    expect($this->pageModuleSource)
        ->toContain("return this.selectedSource.startsWith('file-upload-cached:');")
        ->and($this->pageModuleSource)->toContain('cachedFileSources: []')
        ->and($this->pageModuleSource)->toContain('nextCachedFileSourceId: 1')
        ->and($this->pageModuleSource)->toContain('this.cachedFileSources = [];')
        ->and($this->pageModuleSource)->toContain('restoreCachedFilePreview()')
        ->and($this->pageModuleSource)->toContain('cacheCurrentFilePreviewRows(rows)')
        ->and($this->pageModuleSource)->toContain('const value = `file-upload-cached:${this.nextCachedFileSourceId}`;')
        ->and($this->pageModuleSource)->toContain('this.cachedFileSources.push({')
        ->and($this->pageModuleSource)->toContain('if (this.isCachedFileSource()) {')
        ->and($this->pageModuleSource)->toContain('this.restoreCachedFilePreview();')
        ->and($this->bladeSource)->toContain('<template x-for="fileSource in cachedFileSources" :key="fileSource.value">');
});

it('6. slide over keeps mobile safe horizontal padding', function () {
    expect($this->bladeSource)
        ->toContain('pointer-events-none fixed inset-y-0 right-0 flex max-w-full')
        ->and($this->bladeSource)->toContain('sm:pl-10')
        ->and($this->bladeSource)->toContain('pl-4 pr-6 py-6')
        ->and($this->bladeSource)->toContain('sm:px-6')
        ->and($this->bladeSource)->toContain('border-t border-gray-200 pl-4 pr-6 py-4 sm:px-6');
});

it('7. file upload mode reuses the full width source dropdown, keeps the file input hidden, and preserves the file upload option', function () {
    expect($this->bladeSource)
        ->toContain('Ecommerce Store')
        ->and($this->bladeSource)->toContain('data-products-import-file-input')
        ->and($this->bladeSource)->toContain('class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500"')
        ->and($this->bladeSource)->toContain('class="sr-only"')
        ->and($this->bladeSource)->toContain('<template x-for="fileSource in cachedFileSources" :key="fileSource.value">')
        ->and($this->bladeSource)->not->toContain('Choose File');
});

it('8. loading state appears during preview load', function () {
    expect($this->bladeSource)
        ->toContain('data-products-import-preview-loading')
        ->and($this->bladeSource)->toContain('x-show="isLoadingPreview"')
        ->and($this->pageModuleSource)->toContain('this.isLoadingPreview = true;')
        ->and($this->pageModuleSource)->toContain('this.previewLoadingMessage = loadingMessage;');
});

it('9. bulk import options accordion exists in the import panel', function () {
    expect($this->bladeSource)
        ->toContain('Bulk Import Options')
        ->and($this->bladeSource)->toContain('data-products-import-bulk-options-accordion');
});

it('10. bulk import options accordion defaults collapsed', function () {
    expect($this->pageModuleSource)->toContain('bulkOptionsAccordionOpen: false');
});

it('11. import preview accordion exists in the import panel', function () {
    expect($this->bladeSource)
        ->toContain('Import Preview')
        ->and($this->bladeSource)->toContain('data-products-import-preview-records-accordion');
});

it('12. import preview accordion defaults open', function () {
    expect($this->pageModuleSource)->toContain('previewRecordsAccordionOpen: true');
});

it('12a. import preview accordion body is structurally collapsible', function () {
    expect($this->bladeSource)
        ->toContain('data-products-import-preview-records-accordion')
        ->and($this->bladeSource)->toContain('x-on:click="previewRecordsAccordionOpen = !previewRecordsAccordionOpen"')
        ->and($this->bladeSource)->toContain('x-show="previewRecordsAccordionOpen"')
        ->and($this->bladeSource)->toContain('x-cloak')
        ->and($this->bladeSource)->toContain('border-t border-gray-100')
        ->and($this->bladeSource)->not->toContain('flex min-h-0 flex-1 flex-col rounded-lg border border-gray-200 bg-white');
});

it('12b. import slide over shell and sections remain full width when accordions collapse', function () {
    expect($this->bladeSource)
        ->toContain('class="flex h-full min-h-0 w-full flex-col bg-white shadow-xl"')
        ->and($this->bladeSource)->toContain('class="flex min-h-0 w-full flex-1 flex-col gap-6 overflow-hidden pl-4 pr-6 py-6 sm:px-6"')
        ->and($this->bladeSource)->toContain('class="w-full min-w-0 box-border rounded-lg border border-gray-200 bg-white" x-show="hasSelectedImportSource()"');
});

it('12c. accordion bodies constrain expanded content to section width', function () {
    expect($this->bladeSource)
        ->toContain('w-full min-w-0 overflow-hidden border-t border-gray-100')
        ->and($this->bladeSource)->toContain('grid w-full min-w-0 gap-4')
        ->and($this->bladeSource)->toContain('shrink-0 w-full min-w-0 space-y-4')
        ->and($this->bladeSource)->toContain('flex w-full min-w-0 box-border flex-col gap-3')
        ->and($this->bladeSource)->toContain('w-full min-w-0 box-border overflow-hidden')
        ->and($this->bladeSource)->toContain('data-products-import-preview-scroll')
        ->and($this->bladeSource)->toContain('max-h-[32rem]')
        ->and($this->bladeSource)->toContain('overflow-y-auto');
});

it('13. preview uses cards instead of a table', function () {
    expect($this->bladeSource)
        ->toContain('data-products-import-preview-card')
        ->and($this->bladeSource)->not->toContain('<table class="min-w-full divide-y divide-gray-100">');
});

it('14. preview cards keep truncated product names', function () {
    expect($this->bladeSource)
        ->toContain('class="min-w-0 flex-1 overflow-hidden"')
        ->and($this->bladeSource)->toContain('class="block truncate text-sm font-medium text-gray-900"')
        ->and($this->bladeSource)->toContain('x-bind:title="row.name"');
});

it('15. mobile preview cards keep the product name visible', function () {
    expect($this->bladeSource)
        ->toContain('class="flex min-h-10 items-center gap-3"')
        ->and($this->bladeSource)->toContain('class="shrink-0 rounded border-gray-300 text-blue-600 shadow-sm focus:ring-blue-500"')
        ->and($this->bladeSource)->toContain('class="min-w-0 flex-1 overflow-hidden"')
        ->and($this->bladeSource)->toContain('class="block truncate text-sm font-medium text-gray-900"')
        ->and($this->bladeSource)->toContain('class="shrink-0 rounded-full px-2.5 py-1 text-[11px] font-semibold uppercase leading-none"');
});

it('16. preview cards still show status labels', function () {
    expect($this->bladeSource)
        ->toContain('x-text="previewStatusLabel(row)"')
        ->and($this->pageModuleSource)->toContain("return 'Duplicate';")
        ->and($this->pageModuleSource)->toContain("return row.is_active ? 'Active' : 'Inactive';");
});

it('17. preview cards only show product name and status details', function () {
    expect($this->bladeSource)
        ->not->toContain('x-text="row.sku"')
        ->and($this->bladeSource)->not->toContain('x-text="row.external_id"')
        ->and($this->bladeSource)->not->toContain('Source: ${row.external_source}')
        ->and($this->bladeSource)->not->toContain('Price: ${row.price}');
});

it('18. sellable on import copy is removed from preview cards', function () {
    expect($this->bladeSource)->not->toContain('Sellable on import');
});

it('19. duplicate validation text is removed from the preview card body', function () {
    expect($this->bladeSource)
        ->not->toContain('row.duplicate_reason')
        ->and($this->pageModuleSource)->not->toContain("this.rowError(index, 'external_id')");
});

it('20. import preview description paragraph is removed', function () {
    expect($this->bladeSource)->not->toContain('Preview importable product rows before confirming the import.');
});

it('21. preview toolbar includes search input on the left', function () {
    expect($this->bladeSource)
        ->toContain('type="search"')
        ->and($this->bladeSource)->toContain('data-products-import-preview-search')
        ->and($this->bladeSource)->toContain('placeholder="Search preview records"')
        ->and($this->bladeSource)->toContain('flex')
        ->and($this->bladeSource)->toContain('min-w-0')
        ->and($this->bladeSource)->toContain('flex-1')
        ->and($this->bladeSource)->toContain('sm:flex-row')
        ->and($this->bladeSource)->toContain('sm:items-center');
});

it('22. show duplicates defaults off', function () {
    expect($this->pageModuleSource)->toContain('showDuplicateRows: false');
});

it('23. show duplicates label includes the duplicate row count and uses an unwrapped checkbox label', function () {
    expect($this->bladeSource)
        ->toContain('Show Duplicates (${duplicateRowCount()} rows)')
        ->and($this->bladeSource)->toContain('data-products-import-show-duplicates')
        ->and($this->bladeSource)->toContain('inline-flex')
        ->and($this->bladeSource)->toContain('items-center gap-2')
        ->and($this->pageModuleSource)->toContain('duplicateRowCount()');
});

it('24. duplicate rows are visually hidden instead of being removed from state', function () {
    expect($this->bladeSource)
        ->toContain('x-show="rowVisibleInPreview(row)"')
        ->and($this->bladeSource)->toContain('x-bind:aria-hidden="rowVisibleInPreview(row) ? \'false\' : \'true\'"')
        ->and($this->pageModuleSource)->toContain('if (!this.showDuplicateRows && row.is_duplicate) {');
});

it('25. only the preview records area is scrollable', function () {
    expect($this->bladeSource)
        ->toContain('data-products-import-preview-scroll')
        ->and($this->bladeSource)->toContain('max-h-[32rem]')
        ->and($this->bladeSource)->toContain('overflow-y-auto')
        ->and($this->bladeSource)->toContain('w-full min-w-0 box-border overflow-hidden')
        ->and($this->bladeSource)->not->toContain('data-products-import-preview-records-accordion overflow-y-auto');
});

it('26. select all still excludes duplicate rows by default and is labeled select all', function () {
    expect($this->bladeSource)
        ->toContain('data-products-import-select-visible')
        ->and($this->bladeSource)->toContain('Select All')
        ->and($this->pageModuleSource)->toContain('visibleSelectablePreviewRows()')
        ->and($this->pageModuleSource)->toContain('this.rowVisibleInPreview(row) && !row.is_duplicate')
        ->and($this->pageModuleSource)->toContain('toggleVisibleRowSelection(event)');
});

it('27. select all uses filtered visible non duplicate records only', function () {
    expect($this->pageModuleSource)
        ->toContain('visibleSelectablePreviewRows()')
        ->and($this->pageModuleSource)->toContain('return this.previewRows.filter((row) => this.rowVisibleInPreview(row) && !row.is_duplicate);')
        ->and($this->pageModuleSource)->toContain('this.visibleSelectablePreviewRows().forEach((row) => {');
});

it('28. import selected action still exists after the ux refactor', function () {
    expect($this->bladeSource)
        ->toContain('Import Selected')
        ->and($this->bladeSource)->toContain('x-on:click="submitImport()"')
        ->and($this->pageModuleSource)->toContain('async submitImport()');
});

it('29. import selected submits only the currently selected rows', function () {
    expect($this->pageModuleSource)
        ->toContain('selectedPreviewRows()')
        ->and($this->pageModuleSource)->toContain('return this.previewRows.filter((row) => row.selected);')
        ->and($this->pageModuleSource)->toContain('selectedVisiblePreviewRows()')
        ->and($this->pageModuleSource)->toContain('return this.previewRows.filter((row) => row.selected && this.rowVisibleInPreview(row));')
        ->and($this->pageModuleSource)->toContain('const rows = this.selectedVisiblePreviewRows()');
});

it('30. per record uom and override controls are removed from the compact cards', function () {
    expect($this->bladeSource)
        ->not->toContain('Overrides')
        ->and($this->bladeSource)->not->toContain('x-model="row.base_uom_id"')
        ->and($this->bladeSource)->not->toContain('x-model="row.is_manufacturable"')
        ->and($this->bladeSource)->not->toContain('x-model="row.is_purchasable"');
});

it('31. preview cards remain compact and responsive', function () {
    expect($this->bladeSource)
        ->toContain('class="rounded-lg border border-gray-200 bg-white px-3 py-2 shadow-sm"')
        ->and($this->bladeSource)->toContain('class="flex min-h-10 items-center gap-3"');
});

it('32. preview empty state exists for no records or hidden duplicate rows', function () {
    expect($this->bladeSource)
        ->toContain('data-products-import-preview-empty-state')
        ->and($this->pageModuleSource)->toContain('previewEmptyStateTitle()')
        ->and($this->pageModuleSource)->toContain('previewEmptyStateMessage()')
        ->and($this->pageModuleSource)->toContain('hasVisiblePreviewRows()');
});

it('33. import selected excludes selected rows hidden by search or duplicate filters', function () {
    expect($this->pageModuleSource)
        ->toContain('selectedVisiblePreviewRows()')
        ->and($this->pageModuleSource)->toContain('row.selected && this.rowVisibleInPreview(row)')
        ->and($this->pageModuleSource)->toContain('if (!this.showDuplicateRows && row.is_duplicate) {');
});

it('33a. submit import uses selected visible preview rows instead of all selected rows', function () {
    expect($this->pageModuleSource)
        ->toContain('const rows = this.selectedVisiblePreviewRows()')
        ->and($this->pageModuleSource)->not->toContain('const rows = this.selectedPreviewRows()');
});

it('33b. filtered search contributes to import selected visibility through row visible in preview', function () {
    expect($this->pageModuleSource)
        ->toContain('rowMatchesPreviewSearch(row)')
        ->and($this->pageModuleSource)->toContain('if (!this.rowMatchesPreviewSearch(row)) {')
        ->and($this->pageModuleSource)->toContain('return this.previewRows.filter((row) => row.selected && this.rowVisibleInPreview(row));');
});

it('33c. duplicate hidden rows remain in dom state but are excluded from import visibility', function () {
    expect($this->bladeSource)
        ->toContain('x-show="rowVisibleInPreview(row)"')
        ->and($this->pageModuleSource)->toContain('if (!this.showDuplicateRows && row.is_duplicate) {')
        ->and($this->pageModuleSource)->toContain('return this.previewRows.filter((row) => row.selected && this.rowVisibleInPreview(row));');
});

it('34. preview records scroll container preserves right padding when cards render', function () {
    expect($this->bladeSource)
        ->toContain('shrink-0 w-full min-w-0 space-y-4 pl-4 pr-5 py-4 sm:px-4')
        ->and($this->bladeSource)->toContain('border-t border-gray-100 pl-4 pr-5 py-4 sm:px-4')
        ->and($this->bladeSource)->toContain('w-full min-w-0 box-border overflow-hidden pl-4 pr-5 sm:px-4')
        ->and($this->bladeSource)->toContain('space-y-2');
});

it('34a. preview scroll region includes bottom clearance for the last record', function () {
    expect($this->bladeSource)
        ->toContain('data-products-import-preview-scroll')
        ->and($this->bladeSource)->toContain('max-h-[32rem]')
        ->and($this->bladeSource)->toContain('overflow-y-auto')
        ->and($this->bladeSource)->toContain('pb-52')
        ->and($this->bladeSource)->toContain('sm:pb-32');
});

it('35. slide over close row keeps additional right side breathing room', function () {
    expect($this->bladeSource)
        ->toContain('class="shrink-0 border-b border-gray-100 pl-4 pr-6 py-6 sm:px-6"')
        ->and($this->bladeSource)->toContain('class="flex items-start justify-between gap-4"');
});

it('36. preview rows do not carry extra right padding classes', function () {
    expect($this->bladeSource)
        ->toContain('space-y-2')
        ->and($this->bladeSource)->not->toContain('class="space-y-2 pr-2 sm:pr-3"');
});

it('37. architecture yaml documents the reusable import slide over preview pattern', function () {
    expect($this->architectureSource)
        ->toContain('name: Import Slide-Over Preview Pattern')
        ->and($this->architectureSource)->toContain('auto-preview')
        ->and($this->architectureSource)->toContain('duplicate rows must remain in DOM state')
        ->and($this->architectureSource)->toContain('only scrollable region');
});

it('38. architecture inventory mentions the reusable import slide over preview pattern', function () {
    expect($this->inventorySource)
        ->toContain('Import Slide-Over Preview Pattern')
        ->and($this->inventorySource)->toContain('Preview Records accordion')
        ->and($this->inventorySource)->toContain('duplicate rows remain in DOM state');
});
