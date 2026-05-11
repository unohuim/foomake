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

    $this->bladeSource = file_get_contents(base_path('resources/views/sales/products/index.blade.php'));
    $this->pageModuleSource = file_get_contents(base_path('resources/js/pages/sales-products-index.js'));
});

it('1. products page still renders the import panel root for persistence flows', function () {
    $tenant = ($this->makeTenant)();
    $user = ($this->makeUser)($tenant);

    ($this->grantPermissions)($user, ['inventory-products-view', 'inventory-products-manage']);

    $this->actingAs($user)
        ->get(route('sales.products.index'))
        ->assertOk()
        ->assertSee('data-products-import-panel', false)
        ->assertSee('data-products-import-file-input', false);
});

it('2. cached file sources exist in page module state', function () {
    expect($this->pageModuleSource)
        ->toContain('cachedFileSources: []');
});

it('3. the next cached file source id exists in page module state', function () {
    expect($this->pageModuleSource)
        ->toContain('nextCachedFileSourceId: 1');
});

it('4. reset import state clears cached file sources', function () {
    expect($this->pageModuleSource)
        ->toContain('this.cachedFileSources = [];');
});

it('5. reset import state resets the cached file source counter', function () {
    expect($this->pageModuleSource)
        ->toContain('this.nextCachedFileSourceId = 1;');
});

it('6. selecting file upload triggers the file picker flow', function () {
    expect($this->pageModuleSource)
        ->toContain('if (this.isFileUploadMode()) {')
        ->and($this->pageModuleSource)->toContain('this.openImportFilePicker();');
});

it('7. the file picker flow clears the native input before click', function () {
    expect($this->pageModuleSource)
        ->toContain('clearImportFileInput()')
        ->and($this->pageModuleSource)->toContain('this.clearImportFileInput();')
        ->and($this->pageModuleSource)->toContain('this.$refs.importFileInput?.click();');
});

it('8. loaded files are stored through cache current file preview rows', function () {
    expect($this->pageModuleSource)
        ->toContain('cacheCurrentFilePreviewRows(rows)')
        ->and($this->pageModuleSource)->toContain('this.cachedFileSources.push({');
});

it('9. cached file source values are generated with a distinct prefixed id', function () {
    expect($this->pageModuleSource)
        ->toContain('const value = `file-upload-cached:${this.nextCachedFileSourceId}`;');
});

it('10. cached file source ids are incremented after storage', function () {
    expect($this->pageModuleSource)
        ->toContain('this.nextCachedFileSourceId += 1;');
});

it('11. cached file source labels use the selected filename', function () {
    expect($this->pageModuleSource)
        ->toContain('label: this.selectedFileName,');
});

it('12. selecting a cached file source is detected by helper', function () {
    expect($this->pageModuleSource)
        ->toContain("return this.selectedSource.startsWith('file-upload-cached:');");
});

it('13. selecting a cached file source restores preview rows', function () {
    expect($this->pageModuleSource)
        ->toContain('if (this.isCachedFileSource()) {')
        ->and($this->pageModuleSource)->toContain('this.restoreCachedFilePreview();');
});

it('14. restoring a cached file preview resolves the current cached source by selected value', function () {
    expect($this->pageModuleSource)
        ->toContain('currentCachedFileSource()')
        ->and($this->pageModuleSource)->toContain('return this.cachedFileSources.find((fileSource) => fileSource.value === this.selectedSource) || null;');
});

it('15. restoring a cached file preview maps rows back through normalize preview row', function () {
    expect($this->pageModuleSource)
        ->toContain('this.previewRows = fileSource.rows.map((row) => normalizePreviewRow({');
});

it('16. cached file source options render through x for in the source dropdown', function () {
    expect($this->bladeSource)
        ->toContain('<template x-for="fileSource in cachedFileSources" :key="fileSource.value">');
});

it('17. cached file source options bind both the stored value and label', function () {
    expect($this->bladeSource)
        ->toContain('<option :value="fileSource.value" x-text="fileSource.label"></option>');
});

it('18. switching to woocommerce does not clear cached file sources in handle source change', function () {
    $handleSourceStart = strpos($this->pageModuleSource, 'handleSourceChange() {');
    $handleSourceEnd = strpos($this->pageModuleSource, 'isFileUploadMode() {');

    expect($handleSourceStart)->not->toBeFalse()
        ->and($handleSourceEnd)->not->toBeFalse();

    $handleSourceBlock = substr($this->pageModuleSource, $handleSourceStart, $handleSourceEnd - $handleSourceStart);

    expect($handleSourceBlock)
        ->not->toContain('this.cachedFileSources = [];')
        ->and($handleSourceBlock)->not->toContain('this.nextCachedFileSourceId = 1;')
        ->and($handleSourceBlock)->toContain("loadingMessage: 'Loading WooCommerce preview...'");
});

it('19. cached file sources do not alter import payload shape', function () {
    expect($this->pageModuleSource)
        ->toContain('source: importSource,')
        ->and($this->pageModuleSource)->toContain('is_local_file_import: this.hasLocalFileRows')
        ->and($this->pageModuleSource)->toContain('rows,');
});

it('20. import source value stays null for file upload and cached file sources', function () {
    expect($this->pageModuleSource)
        ->toContain('return this.isFileUploadMode() || this.isCachedFileSource() ? null : this.selectedSource;');
});
