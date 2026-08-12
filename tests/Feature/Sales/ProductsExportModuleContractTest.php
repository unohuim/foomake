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
        $props = $response->viewData('page')['props'] ?? null;

        if (is_array($props) && isset($props['crudConfig'])) {
            return $props['crudConfig'];
        }

        preg_match("/data-crud-config='([^']+)'/", $response->getContent(), $matches);

        expect($matches)->toHaveKey(1);

        $config = json_decode(html_entity_decode($matches[1], ENT_QUOTES), true);

        expect(json_last_error())->toBe(JSON_ERROR_NONE);

        return is_array($config) ? $config : [];
    };

    $this->getProductsIndex = function (User $user) {
        return $this->actingAs($user)->get(route('sales.products.index'));
    };

    $this->bladeSource = file_get_contents(base_path('resources/js/pages/Sales/Products/Index.vue'));
    $this->pageModulePath = base_path('resources/js/pages/Sales/Products/Index.vue');
    $this->pageModuleSource = file_get_contents($this->pageModulePath);
    $this->exportModulePath = base_path('resources/js/lib/export-module.js');
    $this->exportModuleSource = file_exists($this->exportModulePath)
        ? file_get_contents($this->exportModulePath)
        : '';
    $this->exportComponentSource = file_get_contents(base_path('resources/js/components/ResourceExportDrawer.vue'));
    $this->importModulePath = base_path('resources/js/lib/import-module.js');
    $this->importModuleSource = file_exists($this->importModulePath)
        ? file_get_contents($this->importModulePath)
        : '';
});

it('1. products page no longer renders export slide over markup server side', function () {
    $tenant = ($this->makeTenant)();
    $user = ($this->makeUser)($tenant);

    ($this->grantPermissions)($user, ['inventory-products-view']);

    ($this->getProductsIndex)($user)
        ->assertOk()
        ->assertDontSee('data-products-export-panel', false)
        ->assertDontSee('data-shared-export-panel', false);
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

it('7. shared export drawer component file exists', function () {
    expect(file_exists(base_path('resources/js/components/ResourceExportDrawer.vue')))->toBeTrue();
});

it('8. shared export drawer exposes a reusable vue component contract', function () {
    expect($this->exportComponentSource)
        ->toContain('defineProps')
        ->and($this->exportComponentSource)->toContain('defineEmits')
        ->and($this->exportComponentSource)->toContain('resource-export-drawer-title');
});

it('9. sales products page imports the shared export drawer component', function () {
    expect($this->pageModuleSource)
        ->toContain('ResourceExportDrawer');
});

it('10. sales products page composes the shared export drawer component', function () {
    expect($this->pageModuleSource)
        ->toContain('<ResourceExportDrawer')
        ->and($this->pageModuleSource)->toContain('@submit="submitExport"')
        ->and($this->pageModuleSource)->toContain(':open="exportDrawerOpen"');
});

it('11. page module wires the resource index export event to openExportDrawer', function () {
    expect($this->pageModuleSource)
        ->toContain('@export="openExportDrawer"')
        ->and($this->pageModuleSource)->toContain('function openExportDrawer()');
});

it('12. export url construction no longer lives only inside the products page module', function () {
    expect($this->pageModuleSource)
        ->not->toContain('buildExportUrl() {')
        ->and($this->pageModuleSource)->not->toContain('slideOvers:')
        ->and($this->pageModuleSource)->not->toContain('slideOverTitle(');
});

it('13. export submit logic no longer lives only inside the products page module', function () {
    expect($this->pageModuleSource)
        ->not->toContain('submitExport() {')
        ->and($this->pageModuleSource)->toContain('function submitExport(scope)');
});

it('14. shared export component exposes the current export scope state', function () {
    expect($this->exportComponentSource)
        ->toContain('modelValue');
});

it('15. shared export component renders export validation errors', function () {
    expect($this->exportComponentSource)
        ->toContain('error');
});

it('16. shared export component receives export submitting state', function () {
    expect($this->exportComponentSource)
        ->toContain('submitting');
});

it('17. shared export component emits close and submit panel events', function () {
    expect($this->exportComponentSource)
        ->toContain('defineEmits')
        ->and($this->exportComponentSource)->toContain('"close"')
        ->and($this->exportComponentSource)->toContain('"submit"');
});

it('18. products page preserves config driven export url building for all records and current filters', function () {
    expect($this->pageModuleSource)
        ->toContain('props.crudConfig.endpoints.export')
        ->and($this->pageModuleSource)->toContain('new URLSearchParams({ scope })')
        ->and($this->pageModuleSource)->toContain('params.set("search", search.value)')
        ->and($this->pageModuleSource)->toContain('params.set("sort", sort.column)')
        ->and($this->pageModuleSource)->toContain('params.set("direction", sort.direction)');
});

it('19. products export still targets the configured export endpoint instead of a hardcoded products path', function () {
    expect($this->pageModuleSource)
        ->toContain('props.crudConfig.endpoints.export')
        ->and($this->pageModuleSource)->not->toContain('/sales/products/export');
});

it('20. products export submit still starts download and closes after successful submission', function () {
    expect($this->pageModuleSource)
        ->toContain('window.location.assign')
        ->and($this->pageModuleSource)->toContain('exportDrawerOpen.value = false');
});

it('21. products export resets submitting state after export submission attempts', function () {
    expect($this->pageModuleSource)
        ->toContain('exportSubmitting.value = true')
        ->and($this->pageModuleSource)->toContain('exportSubmitting.value = false');
});

it('22. shared export component keeps current and all scope options unchanged', function () {
    expect($this->exportComponentSource)
        ->toContain('Current filters and sort')
        ->and($this->exportComponentSource)->toContain('All records')
        ->and($this->exportComponentSource)->toContain('v-model="localScope"');
});

it('23. products page still delegates import behavior to the shared import component', function () {
    expect($this->pageModuleSource)
        ->toContain('ResourceImportDrawer')
        ->and($this->pageModuleSource)->toContain('@submit="submitImport"');
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
