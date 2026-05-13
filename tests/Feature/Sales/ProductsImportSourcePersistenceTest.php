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
    $this->importModulePath = base_path('resources/js/lib/import-module.js');
    $this->importModuleSource = file_exists($this->importModulePath)
        ? file_get_contents($this->importModulePath)
        : '';
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

it('2. products import persistence is extracted into a shared import module file', function () {
    expect($this->importModulePath)->toBeString()
        ->and(str_ends_with($this->importModulePath, 'resources/js/lib/import-module.js'))->toBeTrue()
        ->and(file_exists($this->importModulePath))->toBeTrue();
});

it('3. cached file sources exist in the shared import module state', function () {
    expect($this->importModuleSource)
        ->toContain('cachedFileSources: []');
});

it('4. the next cached file source id exists in the shared import module state', function () {
    expect($this->importModuleSource)
        ->toContain('nextCachedFileSourceId: 1');
});

it('5. reset import state clears cached file sources', function () {
    expect($this->importModuleSource)
        ->toContain('this.cachedFileSources = [];');
});

it('6. reset import state resets the cached file source counter', function () {
    expect($this->importModuleSource)
        ->toContain('this.nextCachedFileSourceId = 1;');
});

it('7. selecting file upload triggers the file picker flow', function () {
    expect($this->importModuleSource)
        ->toContain('if (this.isFileUploadMode()) {')
        ->and($this->importModuleSource)->toContain('this.openImportFilePicker();');
});

it('8. the file picker flow clears the native input before click', function () {
    expect($this->importModuleSource)
        ->toContain('clearImportFileInput()')
        ->and($this->importModuleSource)->toContain('this.clearImportFileInput();')
        ->and($this->importModuleSource)->toContain('if (this.$refs.importFileInput) {')
        ->and($this->importModuleSource)->toContain('this.$refs.importFileInput.click();');
});

it('9. loaded files are stored through cache current file preview rows', function () {
    expect($this->importModuleSource)
        ->toContain('cacheCurrentFilePreviewRows(rows)')
        ->and($this->importModuleSource)->toContain('this.cachedFileSources.push({');
});

it('10. cached file source values are generated with a distinct prefixed id', function () {
    expect($this->importModuleSource)
        ->toContain('const value = `file-upload-cached:${this.nextCachedFileSourceId}`;');
});

it('11. cached file source ids are incremented after storage', function () {
    expect($this->importModuleSource)
        ->toContain('this.nextCachedFileSourceId += 1;');
});

it('12. cached file source labels use the selected filename', function () {
    expect($this->importModuleSource)
        ->toContain('label: this.selectedFileName,');
});

it('13. selecting a cached file source is detected by helper', function () {
    expect($this->importModuleSource)
        ->toContain("return this.selectedSource.startsWith('file-upload-cached:');");
});

it('14. selecting a cached file source restores preview rows', function () {
    expect($this->importModuleSource)
        ->toContain('if (this.isCachedFileSource()) {')
        ->and($this->importModuleSource)->toContain('this.restoreCachedFilePreview();');
});

it('15. restoring a cached file preview resolves the current cached source by selected value', function () {
    expect($this->importModuleSource)
        ->toContain('currentCachedFileSource()')
        ->and($this->importModuleSource)->toContain('return this.cachedFileSources.find((fileSource) => fileSource.value === this.selectedSource) || null;');
});

it('16. restoring a cached file preview maps rows back through normalize preview row', function () {
    expect($this->importModuleSource)
        ->toContain('this.previewRows = fileSource.rows.map((row) => normalizePreviewRow({');
});

it('17. cached file source options now render through the shared import component source dropdown', function () {
    expect($this->importModuleSource)
        ->toContain('<template x-for="fileSource in cachedFileSources" :key="fileSource.value">');
});

it('18. cached file source options still bind both the stored value and label', function () {
    expect($this->importModuleSource)
        ->toContain('<option :value="fileSource.value" x-text="fileSource.label"></option>');
});

it('19. switching to woocommerce does not clear cached file sources in handle source change', function () {
    $handleSourceStart = strpos($this->importModuleSource, 'handleSourceChange() {');
    $handleSourceEnd = strpos($this->importModuleSource, 'isFileUploadMode() {');

    expect($handleSourceStart)->not->toBeFalse()
        ->and($handleSourceEnd)->not->toBeFalse();

    $handleSourceBlock = substr($this->importModuleSource, $handleSourceStart, $handleSourceEnd - $handleSourceStart);

    expect($handleSourceBlock)
        ->not->toContain('this.cachedFileSources = [];')
        ->and($handleSourceBlock)->not->toContain('this.nextCachedFileSourceId = 1;')
        ->and($this->importModuleSource)->toContain("const loadingExternalPreviewLabel = labels.loadingPreviewExternal || 'Loading WooCommerce preview...';")
        ->and($handleSourceBlock)->toContain('loadingMessage: loadingExternalPreviewLabel');
});

it('20. cached file sources do not alter import payload shape', function () {
    expect($this->importModuleSource)
        ->toContain('source: importSource,')
        ->and($this->importModuleSource)->toContain('is_local_file_import: this.hasLocalFileRows')
        ->and($this->importModuleSource)->toContain('rows,');
});

it('21. import source value stays null for file upload and cached file sources', function () {
    expect($this->importModuleSource)
        ->toContain('return this.isFileUploadMode() || this.isCachedFileSource() ? null : this.selectedSource;');
});

it('22. sales products page module delegates import persistence to the shared import module', function () {
    expect($this->pageModuleSource)
        ->toContain("import { createImportModule } from '../lib/import-module';")
        ->and($this->pageModuleSource)->toContain('createImportModule(');
});
