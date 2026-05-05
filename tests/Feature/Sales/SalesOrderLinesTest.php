<?php

declare(strict_types=1);

use App\Models\Item;
use App\Models\Permission;
use App\Models\Role;
use App\Models\SalesOrderLine;
use App\Models\Tenant;
use App\Models\Uom;
use App\Models\UomCategory;
use App\Models\User;
use Illuminate\Support\Facades\DB;

beforeEach(function () {
    $this->tenantCounter = 1;
    $this->userCounter = 1;
    $this->roleCounter = 1;
    $this->customerCounter = 1;
    $this->orderCounter = 1;
    $this->uomCounter = 1;
    $this->itemCounter = 1;

    $this->makeTenant = function (array $attributes = []): Tenant {
        $tenant = Tenant::query()->create(array_merge([
            'tenant_name' => 'Tenant ' . $this->tenantCounter,
        ], $attributes));

        $this->tenantCounter++;

        return $tenant;
    };

    $this->makeUser = function (Tenant $tenant, array $attributes = []): User {
        $user = User::query()->create(array_merge([
            'tenant_id' => $tenant->id,
            'name' => 'User ' . $this->userCounter,
            'email' => 'sales-order-lines-' . $this->userCounter . '@example.test',
            'email_verified_at' => now(),
            'password' => '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi',
            'remember_token' => null,
        ], $attributes));

        $this->userCounter++;

        return $user;
    };

    $this->grantPermission = function (User $user, string $slug): void {
        $permission = Permission::query()->firstOrCreate(['slug' => $slug]);
        $role = Role::query()->create(['name' => 'role-lines-' . $this->roleCounter]);

        $this->roleCounter++;

        $role->permissions()->syncWithoutDetaching([$permission->id]);
        $user->roles()->syncWithoutDetaching([$role->id]);
    };

    $this->createCustomer = function (Tenant $tenant, array $attributes = []): object {
        $customerId = DB::table('customers')->insertGetId(array_merge([
            'tenant_id' => $tenant->id,
            'name' => 'Customer ' . $this->customerCounter,
            'status' => 'active',
            'notes' => null,
            'address_line_1' => null,
            'address_line_2' => null,
            'city' => null,
            'region' => null,
            'postal_code' => null,
            'country_code' => null,
            'formatted_address' => null,
            'latitude' => null,
            'longitude' => null,
            'address_provider' => null,
            'address_provider_id' => null,
            'created_at' => now(),
            'updated_at' => now(),
        ], $attributes));

        $this->customerCounter++;

        return DB::table('customers')->where('id', $customerId)->first();
    };

    $this->createSalesOrder = function (
        Tenant $tenant,
        int $customerId,
        ?int $contactId = null,
        array $attributes = []
    ): object {
        $orderId = DB::table('sales_orders')->insertGetId(array_merge([
            'tenant_id' => $tenant->id,
            'customer_id' => $customerId,
            'contact_id' => $contactId,
            'status' => 'DRAFT',
            'created_at' => now(),
            'updated_at' => now(),
        ], $attributes));

        $this->orderCounter++;

        return DB::table('sales_orders')->where('id', $orderId)->first();
    };

    $this->makeUom = function (Tenant $tenant, array $attributes = []): Uom {
        $symbol = $attributes['symbol'] ?? 'SOL-UOM-' . $this->uomCounter;
        $existing = Uom::query()
            ->where('tenant_id', $tenant->id)
            ->where('symbol', $symbol)
            ->first();

        if ($existing) {
            return $existing;
        }

        $category = UomCategory::query()->create([
            'tenant_id' => $tenant->id,
            'name' => $attributes['category_name'] ?? 'SOL Category ' . $this->uomCounter,
        ]);

        $uom = Uom::query()->create([
            'tenant_id' => $tenant->id,
            'uom_category_id' => $category->id,
            'name' => $attributes['name'] ?? 'SOL UOM ' . $this->uomCounter,
            'symbol' => $symbol,
        ]);

        $this->uomCounter++;

        return $uom;
    };

    $this->createItem = function (Tenant $tenant, Uom $uom, array $attributes = []): Item {
        $item = Item::query()->create(array_merge([
            'tenant_id' => $tenant->id,
            'name' => 'Item ' . $this->itemCounter,
            'base_uom_id' => $uom->id,
            'is_purchasable' => false,
            'is_sellable' => true,
            'is_manufacturable' => false,
            'default_price_cents' => 1000,
            'default_price_currency_code' => 'USD',
        ], $attributes));

        $this->itemCounter++;

        return $item;
    };

    $this->createSalesOrderLine = function (
        Tenant $tenant,
        int $salesOrderId,
        int $itemId,
        array $attributes = []
    ): object {
        $lineId = DB::table('sales_order_lines')->insertGetId(array_merge([
            'tenant_id' => $tenant->id,
            'sales_order_id' => $salesOrderId,
            'item_id' => $itemId,
            'quantity' => '1.000000',
            'unit_price_cents' => 1000,
            'unit_price_currency_code' => 'USD',
            'line_total_cents' => '1000.000000',
            'created_at' => now(),
            'updated_at' => now(),
        ], $attributes));

        return DB::table('sales_order_lines')->where('id', $lineId)->first();
    };

    $this->fetchSalesOrderLine = function (int $lineId): ?object {
        $line = DB::table('sales_order_lines')->where('id', $lineId)->first();

        if (! $line) {
            return null;
        }

        if (isset($line->quantity)) {
            $line->quantity = bcadd((string) $line->quantity, '0', 6);
        }

        if (isset($line->line_total_cents)) {
            $line->line_total_cents = bcadd((string) $line->line_total_cents, '0', 6);
        }

        return $line;
    };

    $this->fetchSalesOrderLineModel = function (int $lineId): ?SalesOrderLine {
        return SalesOrderLine::query()->find($lineId);
    };

    $this->fetchSalesOrder = function (int $orderId): ?object {
        return DB::table('sales_orders')->where('id', $orderId)->first();
    };

    $this->extractPayload = function ($response, string $payloadId): array {
        $html = $response->getContent();
        $pattern = '/<script type="application\\/json" id="' . preg_quote($payloadId, '/') . '">\\s*(.*?)\\s*<\\/script>/s';

        preg_match($pattern, $html, $matches);

        $json = $matches[1] ?? '';
        $payload = json_decode($json, true);

        return is_array($payload) ? $payload : [];
    };

    $this->assertStableLineErrors = function ($response): void {
        $response->assertJsonStructure([
            'message',
            'errors' => [
                'item_id',
                'quantity',
            ],
        ]);

        expect($response->json('errors.item_id'))->toBeArray()
            ->and($response->json('errors.quantity'))->toBeArray();
    };

    $this->renderDashboard = function (User $user) {
        return $this->actingAs($user)->get(route('dashboard'));
    };
});

it('1. guest cannot add a sales order line', function () {
    $tenant = ($this->makeTenant)();
    $customer = ($this->createCustomer)($tenant);
    $order = ($this->createSalesOrder)($tenant, $customer->id);
    $uom = ($this->makeUom)($tenant);
    $item = ($this->createItem)($tenant, $uom);

    $this->postJson(route('sales.orders.lines.store', $order->id), [
        'item_id' => $item->id,
        'quantity' => '1',
    ])->assertUnauthorized();
});

it('2. unauthorized user cannot add a sales order line', function () {
    $tenant = ($this->makeTenant)();
    $user = ($this->makeUser)($tenant);
    $customer = ($this->createCustomer)($tenant);
    $order = ($this->createSalesOrder)($tenant, $customer->id);
    $uom = ($this->makeUom)($tenant);
    $item = ($this->createItem)($tenant, $uom);

    $this->actingAs($user)
        ->postJson(route('sales.orders.lines.store', $order->id), [
            'item_id' => $item->id,
            'quantity' => '1',
        ])->assertForbidden();
});

it('3. authorized user can add a sellable item line to a draft sales order', function () {
    $tenant = ($this->makeTenant)();
    $user = ($this->makeUser)($tenant);
    $customer = ($this->createCustomer)($tenant);
    $order = ($this->createSalesOrder)($tenant, $customer->id);
    $uom = ($this->makeUom)($tenant);
    $item = ($this->createItem)($tenant, $uom, ['name' => 'Chocolate Bar']);

    ($this->grantPermission)($user, 'sales-sales-orders-manage');

    $response = $this->actingAs($user)
        ->postJson(route('sales.orders.lines.store', $order->id), [
            'item_id' => $item->id,
            'quantity' => '2.500000',
        ])->assertCreated()
        ->assertHeader('content-type', 'application/json');

    $lineId = (int) $response->json('data.line.id');
    $line = ($this->fetchSalesOrderLine)($lineId);

    expect((int) ($line?->item_id ?? 0))->toBe($item->id)
        ->and((string) ($line?->quantity ?? ''))->toBe('2.500000')
        ->and((string) ($response->json('data.line.item_name') ?? ''))->toBe('Chocolate Bar');
});

it('4. non sellable item cannot be added to a sales order', function () {
    $tenant = ($this->makeTenant)();
    $user = ($this->makeUser)($tenant);
    $customer = ($this->createCustomer)($tenant);
    $order = ($this->createSalesOrder)($tenant, $customer->id);
    $uom = ($this->makeUom)($tenant);
    $item = ($this->createItem)($tenant, $uom, ['is_sellable' => false]);

    ($this->grantPermission)($user, 'sales-sales-orders-manage');

    $response = $this->actingAs($user)
        ->postJson(route('sales.orders.lines.store', $order->id), [
            'item_id' => $item->id,
            'quantity' => '1.000000',
        ])->assertStatus(422);

    ($this->assertStableLineErrors)($response);
    expect($response->json('errors.item_id'))->not->toBe([]);
});

it('5. line cannot be added to a completed sales order', function () {
    $tenant = ($this->makeTenant)();
    $user = ($this->makeUser)($tenant);
    $customer = ($this->createCustomer)($tenant);
    $order = ($this->createSalesOrder)($tenant, $customer->id, null, ['status' => 'COMPLETED']);
    $uom = ($this->makeUom)($tenant);
    $item = ($this->createItem)($tenant, $uom);

    ($this->grantPermission)($user, 'sales-sales-orders-manage');

    $this->actingAs($user)
        ->postJson(route('sales.orders.lines.store', $order->id), [
            'item_id' => $item->id,
            'quantity' => '1.000000',
        ])->assertStatus(422)
        ->assertJson([
            'message' => 'Only draft or open sales orders can be edited.',
        ]);
});

it('6. tenant isolation is enforced when adding sales order lines', function () {
    $tenant = ($this->makeTenant)();
    $otherTenant = ($this->makeTenant)();
    $user = ($this->makeUser)($tenant);
    $otherCustomer = ($this->createCustomer)($otherTenant);
    $otherOrder = ($this->createSalesOrder)($otherTenant, $otherCustomer->id);
    $uom = ($this->makeUom)($tenant);
    $item = ($this->createItem)($tenant, $uom);

    ($this->grantPermission)($user, 'sales-sales-orders-manage');

    $this->actingAs($user)
        ->postJson(route('sales.orders.lines.store', $otherOrder->id), [
            'item_id' => $item->id,
            'quantity' => '1.000000',
        ])->assertNotFound();
});

it('7. cross tenant items are rejected when adding sales order lines', function () {
    $tenant = ($this->makeTenant)();
    $otherTenant = ($this->makeTenant)();
    $user = ($this->makeUser)($tenant);
    $customer = ($this->createCustomer)($tenant);
    $order = ($this->createSalesOrder)($tenant, $customer->id);
    $otherUom = ($this->makeUom)($otherTenant);
    $otherItem = ($this->createItem)($otherTenant, $otherUom);

    ($this->grantPermission)($user, 'sales-sales-orders-manage');

    $response = $this->actingAs($user)
        ->postJson(route('sales.orders.lines.store', $order->id), [
            'item_id' => $otherItem->id,
            'quantity' => '1.000000',
        ])->assertStatus(422);

    ($this->assertStableLineErrors)($response);
    expect($response->json('errors.item_id'))->not->toBe([]);
});

it('8. unit price snapshot is captured when a line is created', function () {
    $tenant = ($this->makeTenant)(['currency_code' => 'CAD']);
    $user = ($this->makeUser)($tenant);
    $customer = ($this->createCustomer)($tenant);
    $order = ($this->createSalesOrder)($tenant, $customer->id);
    $uom = ($this->makeUom)($tenant);
    $item = ($this->createItem)($tenant, $uom, [
        'default_price_cents' => 1299,
        'default_price_currency_code' => 'CAD',
    ]);

    ($this->grantPermission)($user, 'sales-sales-orders-manage');

    $response = $this->actingAs($user)
        ->postJson(route('sales.orders.lines.store', $order->id), [
            'item_id' => $item->id,
            'quantity' => '1.000000',
        ])->assertCreated();

    $line = ($this->fetchSalesOrderLine)((int) $response->json('data.line.id'));

    expect((int) ($line?->unit_price_cents ?? 0))->toBe(1299)
        ->and((string) ($line?->unit_price_currency_code ?? ''))->toBe('CAD');
});

it('9. later item price changes do not mutate an existing line unit price snapshot', function () {
    $tenant = ($this->makeTenant)();
    $user = ($this->makeUser)($tenant);
    $customer = ($this->createCustomer)($tenant);
    $order = ($this->createSalesOrder)($tenant, $customer->id);
    $uom = ($this->makeUom)($tenant);
    $item = ($this->createItem)($tenant, $uom, [
        'default_price_cents' => 250,
        'default_price_currency_code' => 'USD',
    ]);

    ($this->grantPermission)($user, 'sales-sales-orders-manage');

    $response = $this->actingAs($user)
        ->postJson(route('sales.orders.lines.store', $order->id), [
            'item_id' => $item->id,
            'quantity' => '2.000000',
        ])->assertCreated();

    $item->update([
        'default_price_cents' => 900,
        'default_price_currency_code' => 'EUR',
    ]);

    $line = ($this->fetchSalesOrderLine)((int) $response->json('data.line.id'));

    expect((int) ($line?->unit_price_cents ?? 0))->toBe(250)
        ->and((string) ($line?->unit_price_currency_code ?? ''))->toBe('USD');
});

it('10. quantity is stored at the canonical scale six', function () {
    $tenant = ($this->makeTenant)();
    $user = ($this->makeUser)($tenant);
    $customer = ($this->createCustomer)($tenant);
    $order = ($this->createSalesOrder)($tenant, $customer->id);
    $uom = ($this->makeUom)($tenant);
    $item = ($this->createItem)($tenant, $uom);

    ($this->grantPermission)($user, 'sales-sales-orders-manage');

    $response = $this->actingAs($user)
        ->postJson(route('sales.orders.lines.store', $order->id), [
            'item_id' => $item->id,
            'quantity' => '3.5',
        ])->assertCreated();

    $line = ($this->fetchSalesOrderLine)((int) $response->json('data.line.id'));

    expect((string) ($line?->quantity ?? ''))->toBe('3.500000')
        ->and((string) ($response->json('data.line.quantity') ?? ''))->toBe('3.500000');
});

it('10b. model readback returns quantity as a canonical scale six string', function () {
    $tenant = ($this->makeTenant)();
    $user = ($this->makeUser)($tenant);
    $customer = ($this->createCustomer)($tenant);
    $order = ($this->createSalesOrder)($tenant, $customer->id);
    $uom = ($this->makeUom)($tenant);
    $item = ($this->createItem)($tenant, $uom);

    ($this->grantPermission)($user, 'sales-sales-orders-manage');

    $response = $this->actingAs($user)
        ->postJson(route('sales.orders.lines.store', $order->id), [
            'item_id' => $item->id,
            'quantity' => '2.5',
        ])->assertCreated();

    $line = ($this->fetchSalesOrderLineModel)((int) $response->json('data.line.id'));

    expect($line?->quantity)->toBe('2.500000');
});

it('10c. model readback returns line total cents as a canonical scale six string', function () {
    $tenant = ($this->makeTenant)();
    $user = ($this->makeUser)($tenant);
    $customer = ($this->createCustomer)($tenant);
    $order = ($this->createSalesOrder)($tenant, $customer->id);
    $uom = ($this->makeUom)($tenant);
    $item = ($this->createItem)($tenant, $uom, ['default_price_cents' => 333]);

    ($this->grantPermission)($user, 'sales-sales-orders-manage');

    $response = $this->actingAs($user)
        ->postJson(route('sales.orders.lines.store', $order->id), [
            'item_id' => $item->id,
            'quantity' => '2.5',
        ])->assertCreated();

    $line = ($this->fetchSalesOrderLineModel)((int) $response->json('data.line.id'));

    expect($line?->line_total_cents)->toBe('832.500000');
});

it('11. successful line create returns useful updated payload summary', function () {
    $tenant = ($this->makeTenant)();
    $user = ($this->makeUser)($tenant);
    $customer = ($this->createCustomer)($tenant);
    $order = ($this->createSalesOrder)($tenant, $customer->id);
    $uom = ($this->makeUom)($tenant);
    $item = ($this->createItem)($tenant, $uom, ['default_price_cents' => 250]);

    ($this->grantPermission)($user, 'sales-sales-orders-manage');

    $response = $this->actingAs($user)
        ->postJson(route('sales.orders.lines.store', $order->id), [
            'item_id' => $item->id,
            'quantity' => '2.000000',
        ])->assertCreated();

    expect($response->json('data.order.id'))->toBe($order->id)
        ->and($response->json('data.order.line_count'))->toBe(1)
        ->and($response->json('data.order.order_total_cents'))->toBe('500.000000')
        ->and($response->json('data.order.lines.0.item_id'))->toBe($item->id);
});

it('11b. json create response returns canonical scale six strings', function () {
    $tenant = ($this->makeTenant)();
    $user = ($this->makeUser)($tenant);
    $customer = ($this->createCustomer)($tenant);
    $order = ($this->createSalesOrder)($tenant, $customer->id);
    $uom = ($this->makeUom)($tenant);
    $item = ($this->createItem)($tenant, $uom, ['default_price_cents' => 333]);

    ($this->grantPermission)($user, 'sales-sales-orders-manage');

    $response = $this->actingAs($user)
        ->postJson(route('sales.orders.lines.store', $order->id), [
            'item_id' => $item->id,
            'quantity' => '2.5',
        ])->assertCreated();

    expect($response->json('data.line.quantity'))->toBe('2.500000')
        ->and($response->json('data.line.line_total_cents'))->toBe('832.500000')
        ->and($response->json('data.order.order_total_cents'))->toBe('832.500000');
});

it('12. create validation errors return json 422 with stable structure', function () {
    $tenant = ($this->makeTenant)();
    $user = ($this->makeUser)($tenant);
    $customer = ($this->createCustomer)($tenant);
    $order = ($this->createSalesOrder)($tenant, $customer->id);

    ($this->grantPermission)($user, 'sales-sales-orders-manage');

    $response = $this->actingAs($user)
        ->postJson(route('sales.orders.lines.store', $order->id), [])
        ->assertStatus(422);

    ($this->assertStableLineErrors)($response);
    expect($response->json('message'))->toBe('The given data was invalid.');
});

it('12b. guest cannot update a sales order line quantity', function () {
    $tenant = ($this->makeTenant)();
    $customer = ($this->createCustomer)($tenant);
    $order = ($this->createSalesOrder)($tenant, $customer->id);
    $uom = ($this->makeUom)($tenant);
    $item = ($this->createItem)($tenant, $uom);
    $line = ($this->createSalesOrderLine)($tenant, $order->id, $item->id);

    $this->patchJson(route('sales.orders.lines.update', [$order->id, $line->id]), [
        'quantity' => '2.000000',
    ])->assertUnauthorized();
});

it('12c. unauthorized user cannot update a sales order line quantity', function () {
    $tenant = ($this->makeTenant)();
    $user = ($this->makeUser)($tenant);
    $customer = ($this->createCustomer)($tenant);
    $order = ($this->createSalesOrder)($tenant, $customer->id);
    $uom = ($this->makeUom)($tenant);
    $item = ($this->createItem)($tenant, $uom);
    $line = ($this->createSalesOrderLine)($tenant, $order->id, $item->id);

    $this->actingAs($user)
        ->patchJson(route('sales.orders.lines.update', [$order->id, $line->id]), [
            'quantity' => '2.000000',
        ])->assertForbidden();
});

it('13. quantity edit works on a draft sales order', function () {
    $tenant = ($this->makeTenant)();
    $user = ($this->makeUser)($tenant);
    $customer = ($this->createCustomer)($tenant);
    $order = ($this->createSalesOrder)($tenant, $customer->id);
    $uom = ($this->makeUom)($tenant);
    $item = ($this->createItem)($tenant, $uom);
    $line = ($this->createSalesOrderLine)($tenant, $order->id, $item->id);

    ($this->grantPermission)($user, 'sales-sales-orders-manage');

    $response = $this->actingAs($user)
        ->patchJson(route('sales.orders.lines.update', [$order->id, $line->id]), [
            'quantity' => '4.250000',
        ])->assertOk();

    $updatedLine = ($this->fetchSalesOrderLine)($line->id);

    expect((string) ($updatedLine?->quantity ?? ''))->toBe('4.250000')
        ->and((string) ($response->json('data.line.quantity') ?? ''))->toBe('4.250000');
});

it('13b. quantity edit after later item price changes still recalculates from the stored line snapshot', function () {
    $tenant = ($this->makeTenant)();
    $user = ($this->makeUser)($tenant);
    $customer = ($this->createCustomer)($tenant, ['name' => 'Snapshot Buyer']);
    $order = ($this->createSalesOrder)($tenant, $customer->id);
    $uom = ($this->makeUom)($tenant);
    $item = ($this->createItem)($tenant, $uom, [
        'name' => 'Frozen Pack',
        'default_price_cents' => 250,
        'default_price_currency_code' => 'USD',
    ]);

    ($this->grantPermission)($user, 'sales-sales-orders-manage');

    $createResponse = $this->actingAs($user)
        ->postJson(route('sales.orders.lines.store', $order->id), [
            'item_id' => $item->id,
            'quantity' => '2.000000',
        ])->assertCreated();

    $lineId = (int) $createResponse->json('data.line.id');

    $item->update([
        'default_price_cents' => 900,
        'default_price_currency_code' => 'EUR',
    ]);

    $updateResponse = $this->actingAs($user)
        ->patchJson(route('sales.orders.lines.update', [$order->id, $lineId]), [
            'quantity' => '3.000000',
        ])->assertOk();

    $line = ($this->fetchSalesOrderLine)($lineId);
    $lineModel = ($this->fetchSalesOrderLineModel)($lineId);

    expect((int) ($line?->unit_price_cents ?? 0))->toBe(250)
        ->and((string) ($line?->unit_price_currency_code ?? ''))->toBe('USD')
        ->and($lineModel?->line_total_cents)->toBe('750.000000')
        ->and($updateResponse->json('data.line.unit_price_cents'))->toBe(250)
        ->and($updateResponse->json('data.line.unit_price_currency_code'))->toBe('USD')
        ->and($updateResponse->json('data.line.line_total_cents'))->toBe('750.000000')
        ->and($updateResponse->json('data.order.order_total_cents'))->toBe('750.000000');

    $indexResponse = $this->actingAs($user)
        ->get(route('sales.orders.index'))
        ->assertOk();

    $payload = ($this->extractPayload)($indexResponse, 'sales-orders-index-payload');
    $orderPayload = collect($payload['orders'] ?? [])->firstWhere('id', $order->id);
    $linePayload = collect($orderPayload['lines'] ?? [])->firstWhere('id', $lineId);

    expect($linePayload['unit_price_cents'] ?? null)->toBe(250)
        ->and($linePayload['unit_price_currency_code'] ?? null)->toBe('USD')
        ->and($linePayload['line_total_cents'] ?? null)->toBe('750.000000');
});

it('14. quantity edit recalculates line total from the original unit price snapshot', function () {
    $tenant = ($this->makeTenant)();
    $user = ($this->makeUser)($tenant);
    $customer = ($this->createCustomer)($tenant);
    $order = ($this->createSalesOrder)($tenant, $customer->id);
    $uom = ($this->makeUom)($tenant);
    $item = ($this->createItem)($tenant, $uom);
    $line = ($this->createSalesOrderLine)($tenant, $order->id, $item->id, [
        'quantity' => '1.000000',
        'unit_price_cents' => 333,
        'line_total_cents' => '333.000000',
    ]);

    ($this->grantPermission)($user, 'sales-sales-orders-manage');

    $response = $this->actingAs($user)
        ->patchJson(route('sales.orders.lines.update', [$order->id, $line->id]), [
            'quantity' => '2.500000',
        ])->assertOk();

    $updatedLine = ($this->fetchSalesOrderLine)($line->id);

    expect((string) ($updatedLine?->line_total_cents ?? ''))->toBe('832.500000')
        ->and((string) ($response->json('data.line.line_total_cents') ?? ''))->toBe('832.500000');
});

it('14b. json update response returns canonical scale six strings', function () {
    $tenant = ($this->makeTenant)();
    $user = ($this->makeUser)($tenant);
    $customer = ($this->createCustomer)($tenant);
    $order = ($this->createSalesOrder)($tenant, $customer->id);
    $uom = ($this->makeUom)($tenant);
    $item = ($this->createItem)($tenant, $uom);
    $line = ($this->createSalesOrderLine)($tenant, $order->id, $item->id, [
        'quantity' => '1.000000',
        'unit_price_cents' => 333,
        'line_total_cents' => '333.000000',
    ]);

    ($this->grantPermission)($user, 'sales-sales-orders-manage');

    $response = $this->actingAs($user)
        ->patchJson(route('sales.orders.lines.update', [$order->id, $line->id]), [
            'quantity' => '2.5',
        ])->assertOk();

    expect($response->json('data.line.quantity'))->toBe('2.500000')
        ->and($response->json('data.line.line_total_cents'))->toBe('832.500000')
        ->and($response->json('data.order.order_total_cents'))->toBe('832.500000');
});

it('15. quantity edit does not mutate the unit price snapshot', function () {
    $tenant = ($this->makeTenant)();
    $user = ($this->makeUser)($tenant);
    $customer = ($this->createCustomer)($tenant);
    $order = ($this->createSalesOrder)($tenant, $customer->id);
    $uom = ($this->makeUom)($tenant);
    $item = ($this->createItem)($tenant, $uom);
    $line = ($this->createSalesOrderLine)($tenant, $order->id, $item->id, [
        'quantity' => '1.000000',
        'unit_price_cents' => 450,
        'unit_price_currency_code' => 'USD',
        'line_total_cents' => '450.000000',
    ]);

    ($this->grantPermission)($user, 'sales-sales-orders-manage');

    $this->actingAs($user)
        ->patchJson(route('sales.orders.lines.update', [$order->id, $line->id]), [
            'quantity' => '9.000000',
        ])->assertOk();

    $updatedLine = ($this->fetchSalesOrderLine)($line->id);

    expect((int) ($updatedLine?->unit_price_cents ?? 0))->toBe(450)
        ->and((string) ($updatedLine?->unit_price_currency_code ?? ''))->toBe('USD');
});

it('16. quantity edit is blocked for a completed sales order', function () {
    $tenant = ($this->makeTenant)();
    $user = ($this->makeUser)($tenant);
    $customer = ($this->createCustomer)($tenant);
    $order = ($this->createSalesOrder)($tenant, $customer->id, null, ['status' => 'COMPLETED']);
    $uom = ($this->makeUom)($tenant);
    $item = ($this->createItem)($tenant, $uom);
    $line = ($this->createSalesOrderLine)($tenant, $order->id, $item->id);

    ($this->grantPermission)($user, 'sales-sales-orders-manage');

    $this->actingAs($user)
        ->patchJson(route('sales.orders.lines.update', [$order->id, $line->id]), [
            'quantity' => '2.000000',
        ])->assertStatus(422)
        ->assertJson([
            'message' => 'Only draft or open sales orders can be edited.',
        ]);
});

it('17. quantity edit is blocked for cross tenant access', function () {
    $tenant = ($this->makeTenant)();
    $otherTenant = ($this->makeTenant)();
    $user = ($this->makeUser)($tenant);
    $otherCustomer = ($this->createCustomer)($otherTenant);
    $otherOrder = ($this->createSalesOrder)($otherTenant, $otherCustomer->id);
    $otherUom = ($this->makeUom)($otherTenant);
    $otherItem = ($this->createItem)($otherTenant, $otherUom);
    $otherLine = ($this->createSalesOrderLine)($otherTenant, $otherOrder->id, $otherItem->id);

    ($this->grantPermission)($user, 'sales-sales-orders-manage');

    $this->actingAs($user)
        ->patchJson(route('sales.orders.lines.update', [$otherOrder->id, $otherLine->id]), [
            'quantity' => '2.000000',
        ])->assertNotFound();
});

it('18. quantity validation rejects invalid values', function () {
    $tenant = ($this->makeTenant)();
    $user = ($this->makeUser)($tenant);
    $customer = ($this->createCustomer)($tenant);
    $order = ($this->createSalesOrder)($tenant, $customer->id);
    $uom = ($this->makeUom)($tenant);
    $item = ($this->createItem)($tenant, $uom);
    $line = ($this->createSalesOrderLine)($tenant, $order->id, $item->id);

    ($this->grantPermission)($user, 'sales-sales-orders-manage');

    $response = $this->actingAs($user)
        ->patchJson(route('sales.orders.lines.update', [$order->id, $line->id]), [
            'quantity' => 'abc',
        ])->assertStatus(422);

    ($this->assertStableLineErrors)($response);
    expect($response->json('errors.quantity'))->not->toBe([]);
});

it('19. quantity validation rejects zero values', function () {
    $tenant = ($this->makeTenant)();
    $user = ($this->makeUser)($tenant);
    $customer = ($this->createCustomer)($tenant);
    $order = ($this->createSalesOrder)($tenant, $customer->id);
    $uom = ($this->makeUom)($tenant);
    $item = ($this->createItem)($tenant, $uom);

    ($this->grantPermission)($user, 'sales-sales-orders-manage');

    $response = $this->actingAs($user)
        ->postJson(route('sales.orders.lines.store', $order->id), [
            'item_id' => $item->id,
            'quantity' => '0',
        ])->assertStatus(422);

    ($this->assertStableLineErrors)($response);
    expect($response->json('errors.quantity'))->not->toBe([]);
});

it('20. quantity validation rejects negative values', function () {
    $tenant = ($this->makeTenant)();
    $user = ($this->makeUser)($tenant);
    $customer = ($this->createCustomer)($tenant);
    $order = ($this->createSalesOrder)($tenant, $customer->id);
    $uom = ($this->makeUom)($tenant);
    $item = ($this->createItem)($tenant, $uom);

    ($this->grantPermission)($user, 'sales-sales-orders-manage');

    $response = $this->actingAs($user)
        ->postJson(route('sales.orders.lines.store', $order->id), [
            'item_id' => $item->id,
            'quantity' => '-1.000000',
        ])->assertStatus(422);

    ($this->assertStableLineErrors)($response);
    expect($response->json('errors.quantity'))->not->toBe([]);
});

it('21. line removal works on a draft sales order', function () {
    $tenant = ($this->makeTenant)();
    $user = ($this->makeUser)($tenant);
    $customer = ($this->createCustomer)($tenant);
    $order = ($this->createSalesOrder)($tenant, $customer->id);
    $uom = ($this->makeUom)($tenant);
    $item = ($this->createItem)($tenant, $uom);
    $line = ($this->createSalesOrderLine)($tenant, $order->id, $item->id);

    ($this->grantPermission)($user, 'sales-sales-orders-manage');

    $response = $this->actingAs($user)
        ->deleteJson(route('sales.orders.lines.destroy', [$order->id, $line->id]))
        ->assertOk();

    expect(($this->fetchSalesOrderLine)($line->id))->toBeNull()
        ->and($response->json('data.deleted_line_id'))->toBe($line->id)
        ->and($response->json('data.order.line_count'))->toBe(0);
});

it('22. line removal is blocked for a completed sales order', function () {
    $tenant = ($this->makeTenant)();
    $user = ($this->makeUser)($tenant);
    $customer = ($this->createCustomer)($tenant);
    $order = ($this->createSalesOrder)($tenant, $customer->id, null, ['status' => 'COMPLETED']);
    $uom = ($this->makeUom)($tenant);
    $item = ($this->createItem)($tenant, $uom);
    $line = ($this->createSalesOrderLine)($tenant, $order->id, $item->id);

    ($this->grantPermission)($user, 'sales-sales-orders-manage');

    $this->actingAs($user)
        ->deleteJson(route('sales.orders.lines.destroy', [$order->id, $line->id]))
        ->assertStatus(422)
        ->assertJson([
            'message' => 'Only draft or open sales orders can be edited.',
        ]);
});

it('23. line removal is blocked for unauthorized users', function () {
    $tenant = ($this->makeTenant)();
    $user = ($this->makeUser)($tenant);
    $customer = ($this->createCustomer)($tenant);
    $order = ($this->createSalesOrder)($tenant, $customer->id);
    $uom = ($this->makeUom)($tenant);
    $item = ($this->createItem)($tenant, $uom);
    $line = ($this->createSalesOrderLine)($tenant, $order->id, $item->id);

    $this->actingAs($user)
        ->deleteJson(route('sales.orders.lines.destroy', [$order->id, $line->id]))
        ->assertForbidden();
});

it('24. line removal is blocked for cross tenant access', function () {
    $tenant = ($this->makeTenant)();
    $otherTenant = ($this->makeTenant)();
    $user = ($this->makeUser)($tenant);
    $otherCustomer = ($this->createCustomer)($otherTenant);
    $otherOrder = ($this->createSalesOrder)($otherTenant, $otherCustomer->id);
    $otherUom = ($this->makeUom)($otherTenant);
    $otherItem = ($this->createItem)($otherTenant, $otherUom);
    $otherLine = ($this->createSalesOrderLine)($otherTenant, $otherOrder->id, $otherItem->id);

    ($this->grantPermission)($user, 'sales-sales-orders-manage');

    $this->actingAs($user)
        ->deleteJson(route('sales.orders.lines.destroy', [$otherOrder->id, $otherLine->id]))
        ->assertNotFound();
});

it('25. sales orders menu item is disabled when no sellable items exist even if a customer exists', function () {
    $tenant = ($this->makeTenant)();
    $user = ($this->makeUser)($tenant);
    ($this->createCustomer)($tenant);

    ($this->grantPermission)($user, 'sales-sales-orders-manage');

    $response = ($this->renderDashboard)($user)
        ->assertOk()
        ->assertSee('data-nav-dropdown-trigger="sales"', false)
        ->assertSee('cursor-not-allowed', false)
        ->assertSee('Orders');

    expect($response->getContent())->not->toContain('href="' . route('sales.orders.index') . '"');
});

it('26. sales orders menu item is enabled when a customer and sellable item both exist', function () {
    $tenant = ($this->makeTenant)();
    $user = ($this->makeUser)($tenant);
    ($this->createCustomer)($tenant);
    $uom = ($this->makeUom)($tenant);
    ($this->createItem)($tenant, $uom, ['is_sellable' => true]);

    ($this->grantPermission)($user, 'sales-sales-orders-manage');

    ($this->renderDashboard)($user)
        ->assertOk()
        ->assertSee(route('sales.orders.index'), false);
});

it('26b. customer detail create order button is disabled when no sellable items exist', function () {
    $tenant = ($this->makeTenant)();
    $user = ($this->makeUser)($tenant);
    $customer = ($this->createCustomer)($tenant, ['name' => 'Display Buyer']);

    ($this->grantPermission)($user, 'sales-customers-view');
    ($this->grantPermission)($user, 'sales-sales-orders-manage');

    $response = $this->actingAs($user)
        ->get(route('sales.customers.show', $customer->id))
        ->assertOk()
        ->assertSee('data-section="customer-orders"', false)
        ->assertSee('Add Order')
        ->assertSee('x-bind:disabled="orderItems.length === 0"', false)
        ->assertSee('cursor-not-allowed', false);

    $payload = ($this->extractPayload)($response, 'sales-customers-show-payload');

    expect($payload['orderItems'] ?? [])->toBe([]);
});

it('26c. customer detail create order form cannot be launched when disabled because the rendered ui contract guards on missing sellable items', function () {
    $tenant = ($this->makeTenant)();
    $user = ($this->makeUser)($tenant);
    $customer = ($this->createCustomer)($tenant, ['name' => 'Display Buyer']);

    ($this->grantPermission)($user, 'sales-customers-view');
    ($this->grantPermission)($user, 'sales-sales-orders-manage');

    $response = $this->actingAs($user)
        ->get(route('sales.customers.show', $customer->id))
        ->assertOk()
        ->assertSee('x-bind:disabled="orderItems.length === 0"', false)
        ->assertSee("x-bind:class=\"orderItems.length === 0 ? 'opacity-50 cursor-not-allowed' : ''\"", false);

    $payload = ($this->extractPayload)($response, 'sales-customers-show-payload');

    expect($payload['orderItems'] ?? [])->toBe([]);
});

it('26d. customer detail create order button is enabled when a sellable item exists', function () {
    $tenant = ($this->makeTenant)();
    $user = ($this->makeUser)($tenant);
    $customer = ($this->createCustomer)($tenant, ['name' => 'Display Buyer']);
    $uom = ($this->makeUom)($tenant);
    ($this->createItem)($tenant, $uom, ['is_sellable' => true, 'name' => 'Gift Box']);

    ($this->grantPermission)($user, 'sales-customers-view');
    ($this->grantPermission)($user, 'sales-sales-orders-manage');

    $response = $this->actingAs($user)
        ->get(route('sales.customers.show', $customer->id))
        ->assertOk()
        ->assertSee('data-section="customer-orders"', false)
        ->assertSee('Add Order')
        ->assertSee('x-bind:disabled="orderItems.length === 0"', false);

    $payload = ($this->extractPayload)($response, 'sales-customers-show-payload');

    expect($payload['orderItems'][0]['name'] ?? null)->toBe('Gift Box');
});

it('27. created lines appear in the sales orders index payload on readback', function () {
    $tenant = ($this->makeTenant)();
    $user = ($this->makeUser)($tenant);
    $customer = ($this->createCustomer)($tenant, ['name' => 'Retailer']);
    $order = ($this->createSalesOrder)($tenant, $customer->id);
    $uom = ($this->makeUom)($tenant);
    $item = ($this->createItem)($tenant, $uom, ['name' => 'Jam Jar']);

    ($this->grantPermission)($user, 'sales-sales-orders-manage');

    $storeResponse = $this->actingAs($user)
        ->postJson(route('sales.orders.lines.store', $order->id), [
            'item_id' => $item->id,
            'quantity' => '1.250000',
        ])->assertCreated();

    $lineId = (int) $storeResponse->json('data.line.id');

    $indexResponse = $this->actingAs($user)
        ->get(route('sales.orders.index'))
        ->assertOk();

    $payload = ($this->extractPayload)($indexResponse, 'sales-orders-index-payload');
    $orderPayload = collect($payload['orders'] ?? [])->firstWhere('id', $order->id);

    expect($orderPayload['line_count'] ?? null)->toBe(1)
        ->and($orderPayload['lines'][0]['id'] ?? null)->toBe($lineId)
        ->and($orderPayload['lines'][0]['item_name'] ?? null)->toBe('Jam Jar');
});

it('28. created lines appear in the customer detail orders mini index payload on readback', function () {
    $tenant = ($this->makeTenant)();
    $user = ($this->makeUser)($tenant);
    $customer = ($this->createCustomer)($tenant, ['name' => 'Wholesale Buyer']);
    $order = ($this->createSalesOrder)($tenant, $customer->id);
    $uom = ($this->makeUom)($tenant);
    $item = ($this->createItem)($tenant, $uom, ['name' => 'Granola Case']);

    ($this->grantPermission)($user, 'sales-customers-view');
    ($this->grantPermission)($user, 'sales-sales-orders-manage');

    $storeResponse = $this->actingAs($user)
        ->postJson(route('sales.orders.lines.store', $order->id), [
            'item_id' => $item->id,
            'quantity' => '4.000000',
        ])->assertCreated();

    $lineId = (int) $storeResponse->json('data.line.id');

    $showResponse = $this->actingAs($user)
        ->get(route('sales.customers.show', $customer->id))
        ->assertOk()
        ->assertSee('data-section="customer-orders"', false);

    $payload = ($this->extractPayload)($showResponse, 'sales-customers-show-payload');
    $orderPayload = collect($payload['orders'] ?? [])->firstWhere('id', $order->id);

    expect($orderPayload['line_count'] ?? null)->toBe(1)
        ->and($orderPayload['lines'][0]['id'] ?? null)->toBe($lineId)
        ->and($orderPayload['lines'][0]['item_name'] ?? null)->toBe('Granola Case');
});
