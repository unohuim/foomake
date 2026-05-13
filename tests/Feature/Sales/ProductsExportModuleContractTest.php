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
    $this->tenantCounter = 1;
    $this->userCounter = 1;

    $this->makeTenant = function (): Tenant {
        $tenant = Tenant::query()->create([
            'tenant_name' => 'Products Export Module Tenant ' . $this->tenantCounter,
        ]);

        $this->tenantCounter++;

        return $tenant;
    };

    $this->makeUser = function (Tenant $tenant): User {
        $user = User::query()->create([
            'tenant_id' => $tenant->id,
            'name' => 'Products Export Module User ' . $this->userCounter,
            'email' => 'products-export-module-user-' . $this->userCounter . '@example.test',
            'email_verified_at' => now(),
            'password' => '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi',
            'remember_token' => null,
        ]);

        $this->userCounter++;

        return $user;
    };

    $this->grantPermissions = function (User $user, array $slugs): void {
        foreach ($slugs as $slug) {
            $permission = Permission::query()->firstOrCreate([
                'slug' => $slug,
            ]);

            $role = Role::query()->create([
                'name' => 'products-export-module-role-' . $this->roleCounter,
            ]);

            $this->roleCounter++;

            $role->permissions()->syncWithoutDetaching([$permission->id]);
            $user->roles()->syncWithoutDetaching([$role->id]);
        }
    };

    $this->extractCrudConfig = function ($response): array {
        preg_match("/data-crud-config='([^']+)'/", $response->getContent(), $matches);

        expect($matches)->toHaveKey(1);

        $config = json_decode(html_entity_decode($matches[1], ENT_QUOTES), true);

        expect(json_last_error())->toBe(JSON_ERROR_NONE);

        return is_array($config) ? $config : [];
    };

    $this->getProductsIndex = function (User $user) {
        return $this->actingAs($user)->get(route('sales.products.index'));
    };

    $this->bladeSource = file_get_contents(base_path('resources/views/sales/products/index.blade.php'));
    $this->pageModulePath = base_path('resources/js/pages/sales-products-index.js');
    $this->pageModuleSource = file_get_contents($this->pageModulePath);
    $this->exportModulePath = base_path('resources/js/lib/export-module.js');
    $this->exportModuleSource = file_exists($this->exportModulePath)
        ? file_get_contents($this->exportModulePath)
        : '';
    $this->importModulePath = base_path('resources/js/lib/import-module.js');
    $this->importModuleSource = file_exists($this->importModulePath)
        ? file_get_contents($this->importModulePath)
        : '';
});

it('1. products page still renders the export slide over root', function () {
    $tenant = ($this->makeTenant)();
    $user = ($this->makeUser)($tenant);

    ($this->grantPermissions)($user, ['inventory-products-view']);

    ($this->getProductsIndex)($user)
        ->assertOk()
        ->assertSee('data-products-export-panel', false);
});

it('2. products crud config still decodes successfully after export extraction', function () {
    $tenant = ($this->makeTenant)();
    $user = ($this->makeUser)($tenant);

    ($this->grantPermissions)($user, ['inventory-products-view']);

    $config = ($this->extractCrudConfig)(($this->getProductsIndex)($user));

    expect($config)->toBeArray();
});

it('3. products crud config still includes the export endpoint', function () {
    $tenant = ($this->makeTenant)();
    $user = ($this->makeUser)($tenant);

    ($this->grantPermissions)($user, ['inventory-products-view']);

    $config = ($this->extractCrudConfig)(($this->getProductsIndex)($user));

    expect($config['endpoints']['export'] ?? null)->toBe(route('sales.products.export'));
});

it('4. products crud config still exposes export labels for the shared toolbar', function () {
    $tenant = ($this->makeTenant)();
    $user = ($this->makeUser)($tenant);

    ($this->grantPermissions)($user, ['inventory-products-view']);

    $config = ($this->extractCrudConfig)(($this->getProductsIndex)($user));

    expect($config['labels']['exportTitle'] ?? null)->toBe('Export Products')
        ->and($config['labels']['exportAriaLabel'] ?? null)->toBe('Export Products');
});

it('5. products crud config still enables export visibility for view permission', function () {
    $tenant = ($this->makeTenant)();
    $user = ($this->makeUser)($tenant);

    ($this->grantPermissions)($user, ['inventory-products-view']);

    $config = ($this->extractCrudConfig)(($this->getProductsIndex)($user));

    expect($config['permissions']['showExport'] ?? null)->toBeTrue();
});

it('6. products crud config still enables export visibility for manage permission', function () {
    $tenant = ($this->makeTenant)();
    $user = ($this->makeUser)($tenant);

    ($this->grantPermissions)($user, ['inventory-products-manage']);

    $config = ($this->extractCrudConfig)(($this->getProductsIndex)($user));

    expect($config['permissions']['showExport'] ?? null)->toBeTrue();
});

it('7. shared export module file exists', function () {
    expect(file_exists($this->exportModulePath))->toBeTrue();
});

it('8. shared export module exports a reusable factory', function () {
    expect($this->exportModuleSource)
        ->toContain('export function createExportModule')
        ->and($this->exportModuleSource)->toContain('return {');
});

it('9. sales products page imports the shared export module', function () {
    expect($this->pageModuleSource)
        ->toContain("import { createExportModule } from '../lib/export-module';");
});

it('10. sales products page composes the shared export module', function () {
    expect($this->pageModuleSource)
        ->toContain('const exportModule = createExportModule(')
        ->and($this->pageModuleSource)->toContain('...exportModule,');
});

it('11. page module still wires the shared crud renderer export trigger to openExportPanel', function () {
    expect($this->pageModuleSource)
        ->toContain("exportHandler: 'openExportPanel()'")
        ->and($this->pageModuleSource)->toContain("export: 'openExportPanel()'");
});

it('12. export url construction no longer lives only inside the products page module', function () {
    expect($this->pageModuleSource)
        ->not->toContain('buildExportUrl() {');
});

it('13. export submit logic no longer lives only inside the products page module', function () {
    expect($this->pageModuleSource)
        ->not->toContain('submitExport() {')
        ->and($this->pageModuleSource)->not->toContain('window.location.assign(exportUrl);');
});

it('14. shared export module owns the default export scope state', function () {
    expect($this->exportModuleSource)
        ->toContain('exportScope: initialScope');
});

it('15. shared export module owns export validation error state', function () {
    expect($this->exportModuleSource)
        ->toContain("exportError: ''");
});

it('16. shared export module owns export submitting state', function () {
    expect($this->exportModuleSource)
        ->toContain('isExportSubmitting: false');
});

it('17. shared export module preserves open and close slide over behavior', function () {
    expect($this->exportModuleSource)
        ->toContain('openExportPanel()')
        ->and($this->exportModuleSource)->toContain("this.openSlideOver('export');")
        ->and($this->exportModuleSource)->toContain('closeExportPanel()')
        ->and($this->exportModuleSource)->toContain("this.closeSlideOver('export');");
});

it('18. shared export module preserves config driven export url building for all records and current filters', function () {
    expect($this->exportModuleSource)
        ->toContain("if (this.exportScope === 'all') {")
        ->and($this->exportModuleSource)->toContain("exportUrl.searchParams.set('scope', 'all');")
        ->and($this->exportModuleSource)->toContain("exportUrl.searchParams.set('scope', 'current');")
        ->and($this->exportModuleSource)->toContain("exportUrl.searchParams.set('search', this.search.trim());")
        ->and($this->exportModuleSource)->toContain("exportUrl.searchParams.set('sort', this.sort.column);")
        ->and($this->exportModuleSource)->toContain("exportUrl.searchParams.set('direction', this.sort.direction);");
});

it('19. shared export module still targets the configured export endpoint instead of a hardcoded products path', function () {
    expect($this->exportModuleSource)
        ->toContain('if (!this.endpoints.export) {')
        ->and($this->exportModuleSource)->toContain('const exportUrl = new URL(this.endpoints.export, window.location.origin);')
        ->and($this->exportModuleSource)->not->toContain('/sales/products/export');
});

it('20. shared export module still surfaces export errors and closes after successful submission', function () {
    expect($this->exportModuleSource)
        ->toContain('this.exportError = unavailableMessage;')
        ->and($this->exportModuleSource)->toContain('window.location.assign(exportUrl);')
        ->and($this->exportModuleSource)->toContain('this.closeExportPanel();')
        ->and($this->pageModuleSource)->toContain("unavailableMessage: 'Unable to export products.'");
});

it('21. shared export module resets submitting state after export submission attempts', function () {
    expect($this->exportModuleSource)
        ->toContain('this.isExportSubmitting = true;')
        ->and($this->exportModuleSource)->toContain('this.isExportSubmitting = false;');
});

it('22. products export blade markup still keeps current and all scope options unchanged', function () {
    expect($this->bladeSource)
        ->toContain('Current filters and sort')
        ->and($this->bladeSource)->toContain('All records')
        ->and($this->bladeSource)->toContain('x-model="exportScope"');
});

it('23. products page still delegates import behavior to the shared import module', function () {
    expect($this->pageModuleSource)
        ->toContain("import { createImportModule } from '../lib/import-module';")
        ->and($this->pageModuleSource)->toContain('createImportModule(');
});

it('24. no reusable import abstraction is introduced alongside the export extraction', function () {
    expect($this->pageModuleSource)
        ->not->toContain('createReusableImport')
        ->and($this->exportModuleSource)->not->toContain('createImportModule')
        ->and($this->exportModuleSource)->not->toContain('importPreview')
        ->and($this->exportModuleSource)->not->toContain('importStore');
});

it('25. existing shared import module remains independent from export extraction', function () {
    expect($this->importModuleSource)
        ->toContain('export function createImportModule')
        ->and($this->importModuleSource)->not->toContain('createExportModule')
        ->and($this->importModuleSource)->not->toContain('buildExportUrl');
});
