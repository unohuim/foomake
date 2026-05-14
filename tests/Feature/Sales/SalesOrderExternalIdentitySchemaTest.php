<?php

declare(strict_types=1);

use App\Models\Customer;
use App\Models\Permission;
use App\Models\Role;
use App\Models\SalesOrder;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->tenantCounter = 1;
    $this->userCounter = 1;
    $this->roleCounter = 1;
    $this->customerCounter = 1;

    $this->makeTenant = function (array $attributes = []): Tenant {
        $tenant = Tenant::query()->create(array_merge([
            'tenant_name' => 'Sales Order Schema Tenant ' . $this->tenantCounter,
        ], $attributes));

        $this->tenantCounter++;

        return $tenant;
    };

    $this->makeUser = function (Tenant $tenant, array $attributes = []): User {
        $user = User::query()->create(array_merge([
            'tenant_id' => $tenant->id,
            'name' => 'Sales Order Schema User ' . $this->userCounter,
            'email' => 'sales-order-schema-user-' . $this->userCounter . '@example.test',
            'email_verified_at' => now(),
            'password' => '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi',
            'remember_token' => null,
        ], $attributes));

        $this->userCounter++;

        return $user;
    };

    $this->grantPermissions = function (User $user, array $slugs): void {
        foreach ($slugs as $slug) {
            $permission = Permission::query()->firstOrCreate(['slug' => $slug]);
            $role = Role::query()->create(['name' => 'sales-order-schema-role-' . $this->roleCounter]);

            $this->roleCounter++;

            $role->permissions()->syncWithoutDetaching([$permission->id]);
            $user->roles()->syncWithoutDetaching([$role->id]);
        }
    };

    $this->makeCustomer = function (Tenant $tenant, array $attributes = []): Customer {
        $customer = Customer::query()->create(array_merge([
            'tenant_id' => $tenant->id,
            'name' => 'Sales Order Schema Customer ' . $this->customerCounter,
            'status' => Customer::STATUS_ACTIVE,
        ], $attributes));

        $this->customerCounter++;

        return $customer;
    };
});

it('1. sales orders table has external source column', function () {
    expect(Schema::hasColumn('sales_orders', 'external_source'))->toBeTrue();
});

it('2. sales orders table has external id column', function () {
    expect(Schema::hasColumn('sales_orders', 'external_id'))->toBeTrue();
});

it('3. sales orders table has external status column', function () {
    expect(Schema::hasColumn('sales_orders', 'external_status'))->toBeTrue();
});

it('4. sales orders table has external status synced at column', function () {
    expect(Schema::hasColumn('sales_orders', 'external_status_synced_at'))->toBeTrue();
});

it('5. sales orders table has order date column', function () {
    expect(Schema::hasColumn('sales_orders', 'order_date'))->toBeTrue();
});

it('6. order date is nullable for manually created orders', function () {
    $tenant = ($this->makeTenant)();
    $customer = ($this->makeCustomer)($tenant);

    $order = SalesOrder::query()->create([
        'tenant_id' => $tenant->id,
        'customer_id' => $customer->id,
        'contact_id' => null,
        'status' => SalesOrder::STATUS_DRAFT,
        'order_date' => null,
        'external_source' => null,
        'external_id' => null,
        'external_status' => null,
        'external_status_synced_at' => null,
    ]);

    expect($order->fresh()->order_date)->toBeNull();
});

it('7. external fields are nullable for manually created app orders', function () {
    $tenant = ($this->makeTenant)();
    $customer = ($this->makeCustomer)($tenant);

    $order = SalesOrder::query()->create([
        'tenant_id' => $tenant->id,
        'customer_id' => $customer->id,
        'contact_id' => null,
        'status' => SalesOrder::STATUS_DRAFT,
        'order_date' => null,
        'external_source' => null,
        'external_id' => null,
        'external_status' => null,
        'external_status_synced_at' => null,
    ]);

    expect($order->external_source)->toBeNull()
        ->and($order->external_id)->toBeNull()
        ->and($order->external_status)->toBeNull()
        ->and($order->external_status_synced_at)->toBeNull();
});

it('8. sales order model allows fillable external identity fields', function () {
    $fillable = (new SalesOrder())->getFillable();

    expect($fillable)->toContain('order_date')
        ->and($fillable)->toContain('external_source')
        ->and($fillable)->toContain('external_id')
        ->and($fillable)->toContain('external_status')
        ->and($fillable)->toContain('external_status_synced_at');
});

it('9. external status is not treated as a model enum cast', function () {
    $casts = (new SalesOrder())->getCasts();
    $externalStatusCast = $casts['external_status'] ?? null;

    expect($externalStatusCast)->toBeNull();
});

it('10. order date is date cast on the model', function () {
    $casts = (new SalesOrder())->getCasts();

    expect($casts['order_date'] ?? null)->toContain('date');
});

it('11. external status synced at is datetime cast on the model', function () {
    $casts = (new SalesOrder())->getCasts();

    expect($casts['external_status_synced_at'] ?? null)->toContain('datetime');
});

it('12. duplicate protection exists for tenant scoped external identity', function () {
    $indexes = Schema::getIndexes('sales_orders');

    $matching = collect($indexes)->contains(function (array $index): bool {
        $columns = $index['columns'] ?? [];

        return in_array('tenant_id', $columns, true)
            && in_array('external_source', $columns, true)
            && in_array('external_id', $columns, true)
            && ($index['unique'] ?? false) === true;
    });

    expect($matching)->toBeTrue();
});

it('13. duplicate protection blocks same tenant duplicate external identity', function () {
    $tenant = ($this->makeTenant)();
    $customer = ($this->makeCustomer)($tenant);

    SalesOrder::query()->create([
        'tenant_id' => $tenant->id,
        'customer_id' => $customer->id,
        'contact_id' => null,
        'status' => SalesOrder::STATUS_OPEN,
        'order_date' => '2026-05-11',
        'external_source' => 'woocommerce',
        'external_id' => '1001',
        'external_status' => 'processing',
        'external_status_synced_at' => now(),
    ]);

    $thrown = false;

    try {
        SalesOrder::query()->create([
            'tenant_id' => $tenant->id,
            'customer_id' => $customer->id,
            'contact_id' => null,
            'status' => SalesOrder::STATUS_OPEN,
            'order_date' => '2026-05-12',
            'external_source' => 'woocommerce',
            'external_id' => '1001',
            'external_status' => 'completed',
            'external_status_synced_at' => now(),
        ]);
    } catch (\Throwable $exception) {
        $thrown = true;
    }

    expect($thrown)->toBeTrue();
});

it('14. same external identity is allowed in a different tenant', function () {
    $tenantA = ($this->makeTenant)(['tenant_name' => 'Tenant A']);
    $tenantB = ($this->makeTenant)(['tenant_name' => 'Tenant B']);
    $customerA = ($this->makeCustomer)($tenantA);
    $customerB = ($this->makeCustomer)($tenantB);

    SalesOrder::query()->create([
        'tenant_id' => $tenantA->id,
        'customer_id' => $customerA->id,
        'contact_id' => null,
        'status' => SalesOrder::STATUS_OPEN,
        'order_date' => '2026-05-11',
        'external_source' => 'woocommerce',
        'external_id' => '1001',
        'external_status' => 'processing',
        'external_status_synced_at' => now(),
    ]);

    SalesOrder::query()->create([
        'tenant_id' => $tenantB->id,
        'customer_id' => $customerB->id,
        'contact_id' => null,
        'status' => SalesOrder::STATUS_OPEN,
        'order_date' => '2026-05-11',
        'external_source' => 'woocommerce',
        'external_id' => '1001',
        'external_status' => 'processing',
        'external_status_synced_at' => now(),
    ]);

    expect(SalesOrder::query()->count())->toBe(2);
});

it('15. local status remains app controlled independent of external status', function () {
    $tenant = ($this->makeTenant)();
    $customer = ($this->makeCustomer)($tenant);

    $order = SalesOrder::query()->create([
        'tenant_id' => $tenant->id,
        'customer_id' => $customer->id,
        'contact_id' => null,
        'status' => SalesOrder::STATUS_OPEN,
        'order_date' => '2026-05-11',
        'external_source' => 'woocommerce',
        'external_id' => '1001',
        'external_status' => 'processing',
        'external_status_synced_at' => now(),
    ]);

    $order->update([
        'external_status' => 'completed',
    ]);

    expect($order->fresh()->status)->toBe(SalesOrder::STATUS_OPEN);
});

it('16. external status updates do not create stock moves', function () {
    $tenant = ($this->makeTenant)();
    $customer = ($this->makeCustomer)($tenant);

    $order = SalesOrder::query()->create([
        'tenant_id' => $tenant->id,
        'customer_id' => $customer->id,
        'contact_id' => null,
        'status' => SalesOrder::STATUS_OPEN,
        'order_date' => '2026-05-11',
        'external_source' => 'woocommerce',
        'external_id' => '1001',
        'external_status' => 'processing',
        'external_status_synced_at' => now(),
    ]);

    $before = \DB::table('stock_moves')->count();

    $order->update([
        'external_status' => 'completed',
        'external_status_synced_at' => now(),
    ]);

    expect(\DB::table('stock_moves')->count())->toBe($before);
});

it('17. manually created order still uses app status constants', function () {
    $tenant = ($this->makeTenant)();
    $customer = ($this->makeCustomer)($tenant);

    $order = SalesOrder::query()->create([
        'tenant_id' => $tenant->id,
        'customer_id' => $customer->id,
        'contact_id' => null,
        'status' => SalesOrder::STATUS_DRAFT,
        'order_date' => '2026-05-11',
    ]);

    expect($order->status)->toBe(SalesOrder::STATUS_DRAFT);
});

it('18. list endpoint for orders should accept order date search and export shape later through request validation file', function () {
    $requestPath = base_path('app/Http/Requests/Sales/ListSalesOrdersRequest.php');

    expect(file_exists($requestPath))->toBeTrue();
});

it('19. dedicated sales order external identity migration exists', function () {
    $files = [
        base_path('database/migrations/2026_05_14_120000_add_external_identity_to_sales_orders_table.php'),
    ];

    expect(file_exists($files[0]))->toBeTrue();
});

it('20. migration is reversible', function () {
    $source = file_get_contents(base_path('database/migrations/2026_05_14_120000_add_external_identity_to_sales_orders_table.php'));

    expect($source)->toContain('public function down(): void')
        ->and($source)->toContain('drop')
        ->and($source)->toContain('external_source')
        ->and($source)->toContain('external_id');
});

it('21. migration remains focused on sales orders related columns only', function () {
    $source = file_get_contents(base_path('database/migrations/2026_05_14_120000_add_external_identity_to_sales_orders_table.php'));

    expect($source)->toContain('sales_orders')
        ->and($source)->not->toContain("Schema::table('customers'")
        ->and($source)->not->toContain("Schema::table('items'");
});

it('22. sales order lines table has external id column for imported source line identity', function () {
    expect(Schema::hasColumn('sales_order_lines', 'external_id'))->toBeTrue();
});

it('23. sales order line model allows fillable external id field', function () {
    expect((new \App\Models\SalesOrderLine())->getFillable())->toContain('external_id');
});

it('24. dedicated sales order line external identity migration exists and is reversible', function () {
    $path = base_path('database/migrations/2026_05_14_130000_add_external_id_to_sales_order_lines_table.php');
    $source = file_get_contents($path);

    expect(file_exists($path))->toBeTrue()
        ->and($source)->toContain("Schema::table('sales_order_lines'")
        ->and($source)->toContain('dropColumn(\'external_id\')');
});
