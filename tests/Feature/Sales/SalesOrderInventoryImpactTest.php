<?php

declare(strict_types=1);

use App\Models\Item;
use App\Models\Permission;
use App\Models\Role;
use App\Models\SalesOrder;
use App\Models\SalesOrderLine;
use App\Models\StockMove;
use App\Models\Tenant;
use App\Models\Uom;
use App\Models\UomCategory;
use App\Models\User;
use App\Actions\Sales\CompleteSalesOrderAction;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

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
            'email' => 'sales-order-inventory-' . $this->userCounter . '@example.test',
            'email_verified_at' => now(),
            'password' => '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi',
            'remember_token' => null,
        ], $attributes));

        $this->userCounter++;

        return $user;
    };

    $this->grantPermission = function (User $user, string $slug): void {
        $permission = Permission::query()->firstOrCreate(['slug' => $slug]);
        $role = Role::query()->create(['name' => 'role-inventory-impact-' . $this->roleCounter]);

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
        array $attributes = []
    ): object {
        $orderId = DB::table('sales_orders')->insertGetId(array_merge([
            'tenant_id' => $tenant->id,
            'customer_id' => $customerId,
            'contact_id' => null,
            'status' => 'DRAFT',
            'created_at' => now(),
            'updated_at' => now(),
        ], $attributes));

        $this->orderCounter++;

        return DB::table('sales_orders')->where('id', $orderId)->first();
    };

    $this->makeUom = function (Tenant $tenant, array $attributes = []): Uom {
        $symbol = $attributes['symbol'] ?? 'SOI-UOM-' . $this->uomCounter;
        $existing = Uom::query()
            ->where('tenant_id', $tenant->id)
            ->where('symbol', $symbol)
            ->first();

        if ($existing) {
            return $existing;
        }

        $category = UomCategory::query()->create([
            'tenant_id' => $tenant->id,
            'name' => $attributes['category_name'] ?? 'SOI Category ' . $this->uomCounter,
        ]);

        $uom = Uom::query()->create([
            'tenant_id' => $tenant->id,
            'uom_category_id' => $category->id,
            'name' => $attributes['name'] ?? 'SOI UOM ' . $this->uomCounter,
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

    $this->fetchSalesOrder = function (int $orderId): ?object {
        return DB::table('sales_orders')->where('id', $orderId)->first();
    };

    $this->fetchStockMovesForOrder = function (int $orderId) {
        $lineIds = DB::table('sales_order_lines')
            ->where('sales_order_id', $orderId)
            ->pluck('id')
            ->all();

        return DB::table('stock_moves')
            ->where('source_type', SalesOrderLine::class)
            ->whereIn('source_id', $lineIds === [] ? [0] : $lineIds)
            ->orderBy('id')
            ->get();
    };

    $this->assertStatusValidationErrors = function ($response): void {
        $response->assertJsonStructure([
            'message',
            'errors' => [
                'status',
            ],
        ]);

        expect($response->json('errors.status'))->toBeArray();
    };

    $this->completeOrder = function (User $user, int $orderId) {
        return $this->actingAs($user)->patchJson(route('sales.orders.status.update', $orderId), [
            'status' => 'COMPLETED',
        ]);
    };

    $this->asSixDecimals = function (string $value): string {
        return bcadd($value, '0', 6);
    };

    $this->issueQuantity = function (string $value): string {
        return bcsub('0.000000', bcadd($value, '0', 6), 6);
    };
});

it('1. completing an open sales order creates stock moves', function () {
    $tenant = ($this->makeTenant)();
    $user = ($this->makeUser)($tenant);
    $customer = ($this->createCustomer)($tenant);
    $order = ($this->createSalesOrder)($tenant, $customer->id, ['status' => 'OPEN']);
    $uom = ($this->makeUom)($tenant);
    $item = ($this->createItem)($tenant, $uom);
    ($this->createSalesOrderLine)($tenant, $order->id, $item->id, ['quantity' => '2.500000']);

    ($this->grantPermission)($user, 'sales-sales-orders-manage');

    (($this->completeOrder)($user, $order->id))->assertOk();

    expect(($this->fetchStockMovesForOrder)($order->id))->toHaveCount(1);
});

it('2. completing creates exactly one issue stock move per sales order line', function () {
    $tenant = ($this->makeTenant)();
    $user = ($this->makeUser)($tenant);
    $customer = ($this->createCustomer)($tenant);
    $order = ($this->createSalesOrder)($tenant, $customer->id, ['status' => 'OPEN']);
    $uom = ($this->makeUom)($tenant);
    $itemA = ($this->createItem)($tenant, $uom);
    $itemB = ($this->createItem)($tenant, $uom);
    ($this->createSalesOrderLine)($tenant, $order->id, $itemA->id);
    ($this->createSalesOrderLine)($tenant, $order->id, $itemB->id);

    ($this->grantPermission)($user, 'sales-sales-orders-manage');

    (($this->completeOrder)($user, $order->id))->assertOk();

    expect(($this->fetchStockMovesForOrder)($order->id))->toHaveCount(2);
});

it('3. issue stock move uses the sales order line item', function () {
    $tenant = ($this->makeTenant)();
    $user = ($this->makeUser)($tenant);
    $customer = ($this->createCustomer)($tenant);
    $order = ($this->createSalesOrder)($tenant, $customer->id, ['status' => 'OPEN']);
    $uom = ($this->makeUom)($tenant);
    $item = ($this->createItem)($tenant, $uom);
    $line = ($this->createSalesOrderLine)($tenant, $order->id, $item->id);

    ($this->grantPermission)($user, 'sales-sales-orders-manage');

    (($this->completeOrder)($user, $order->id))->assertOk();

    $stockMove = ($this->fetchStockMovesForOrder)($order->id)->first();

    expect((int) ($stockMove?->item_id ?? 0))->toBe((int) $line->item_id);
});

it('4. issue stock move uses the item base uom', function () {
    $tenant = ($this->makeTenant)();
    $user = ($this->makeUser)($tenant);
    $customer = ($this->createCustomer)($tenant);
    $order = ($this->createSalesOrder)($tenant, $customer->id, ['status' => 'OPEN']);
    $uom = ($this->makeUom)($tenant);
    $item = ($this->createItem)($tenant, $uom);
    ($this->createSalesOrderLine)($tenant, $order->id, $item->id);

    ($this->grantPermission)($user, 'sales-sales-orders-manage');

    (($this->completeOrder)($user, $order->id))->assertOk();

    $stockMove = ($this->fetchStockMovesForOrder)($order->id)->first();

    expect((int) ($stockMove?->uom_id ?? 0))->toBe($item->base_uom_id);
});

it('5. issue stock move quantity equals the signed sales order line quantity', function () {
    $tenant = ($this->makeTenant)();
    $user = ($this->makeUser)($tenant);
    $customer = ($this->createCustomer)($tenant);
    $order = ($this->createSalesOrder)($tenant, $customer->id, ['status' => 'OPEN']);
    $uom = ($this->makeUom)($tenant);
    $item = ($this->createItem)($tenant, $uom);
    $line = ($this->createSalesOrderLine)($tenant, $order->id, $item->id, ['quantity' => '4.250000']);

    ($this->grantPermission)($user, 'sales-sales-orders-manage');

    (($this->completeOrder)($user, $order->id))->assertOk();

    $stockMove = ($this->fetchStockMovesForOrder)($order->id)->first();
    $expectedQuantity = ($this->issueQuantity)((string) $line->quantity);

    expect((string) ($stockMove?->quantity ?? ''))->toBe($expectedQuantity);
});

it('6. issue stock move uses type issue', function () {
    $tenant = ($this->makeTenant)();
    $user = ($this->makeUser)($tenant);
    $customer = ($this->createCustomer)($tenant);
    $order = ($this->createSalesOrder)($tenant, $customer->id, ['status' => 'OPEN']);
    $uom = ($this->makeUom)($tenant);
    $item = ($this->createItem)($tenant, $uom);
    ($this->createSalesOrderLine)($tenant, $order->id, $item->id);

    ($this->grantPermission)($user, 'sales-sales-orders-manage');

    (($this->completeOrder)($user, $order->id))->assertOk();

    expect(($this->fetchStockMovesForOrder)($order->id)->first()?->type)->toBe('issue');
});

it('7. issue stock move uses posted status', function () {
    $tenant = ($this->makeTenant)();
    $user = ($this->makeUser)($tenant);
    $customer = ($this->createCustomer)($tenant);
    $order = ($this->createSalesOrder)($tenant, $customer->id, ['status' => 'OPEN']);
    $uom = ($this->makeUom)($tenant);
    $item = ($this->createItem)($tenant, $uom);
    ($this->createSalesOrderLine)($tenant, $order->id, $item->id);

    ($this->grantPermission)($user, 'sales-sales-orders-manage');

    (($this->completeOrder)($user, $order->id))->assertOk();

    expect((string) (($this->fetchStockMovesForOrder)($order->id)->first()?->status ?? ''))->toBe('POSTED');
});

it('8. issue stock move belongs to the same tenant as the sales order', function () {
    $tenant = ($this->makeTenant)();
    $user = ($this->makeUser)($tenant);
    $customer = ($this->createCustomer)($tenant);
    $order = ($this->createSalesOrder)($tenant, $customer->id, ['status' => 'OPEN']);
    $uom = ($this->makeUom)($tenant);
    $item = ($this->createItem)($tenant, $uom);
    ($this->createSalesOrderLine)($tenant, $order->id, $item->id);

    ($this->grantPermission)($user, 'sales-sales-orders-manage');

    (($this->completeOrder)($user, $order->id))->assertOk();

    expect((int) (($this->fetchStockMovesForOrder)($order->id)->first()?->tenant_id ?? 0))->toBe($tenant->id);
});

it('9. issue stock move links back to the sales order line source', function () {
    $tenant = ($this->makeTenant)();
    $user = ($this->makeUser)($tenant);
    $customer = ($this->createCustomer)($tenant);
    $order = ($this->createSalesOrder)($tenant, $customer->id, ['status' => 'OPEN']);
    $uom = ($this->makeUom)($tenant);
    $item = ($this->createItem)($tenant, $uom);
    $line = ($this->createSalesOrderLine)($tenant, $order->id, $item->id);

    ($this->grantPermission)($user, 'sales-sales-orders-manage');

    (($this->completeOrder)($user, $order->id))->assertOk();

    $stockMove = ($this->fetchStockMovesForOrder)($order->id)->first();

    expect((string) ($stockMove?->source_type ?? ''))->toBe(SalesOrderLine::class)
        ->and((int) ($stockMove?->source_id ?? 0))->toBe($line->id);
});

it('10. multi line sales order creates one correctly linked stock move per line', function () {
    $tenant = ($this->makeTenant)();
    $user = ($this->makeUser)($tenant);
    $customer = ($this->createCustomer)($tenant);
    $order = ($this->createSalesOrder)($tenant, $customer->id, ['status' => 'OPEN']);
    $uom = ($this->makeUom)($tenant);
    $itemA = ($this->createItem)($tenant, $uom, ['name' => 'Item A']);
    $itemB = ($this->createItem)($tenant, $uom, ['name' => 'Item B']);
    $lineA = ($this->createSalesOrderLine)($tenant, $order->id, $itemA->id, ['quantity' => '1.500000']);
    $lineB = ($this->createSalesOrderLine)($tenant, $order->id, $itemB->id, ['quantity' => '3.000000']);

    ($this->grantPermission)($user, 'sales-sales-orders-manage');

    (($this->completeOrder)($user, $order->id))->assertOk();

    $stockMoves = ($this->fetchStockMovesForOrder)($order->id)->keyBy('source_id');

    expect($stockMoves->keys()->all())->toEqualCanonicalizing([$lineA->id, $lineB->id])
        ->and((string) ($stockMoves[$lineA->id]->quantity ?? ''))->toBe('-1.500000')
        ->and((string) ($stockMoves[$lineB->id]->quantity ?? ''))->toBe('-3.000000');
});

it('11. completing succeeds even when on hand inventory is insufficient', function () {
    $tenant = ($this->makeTenant)();
    $user = ($this->makeUser)($tenant);
    $customer = ($this->createCustomer)($tenant);
    $order = ($this->createSalesOrder)($tenant, $customer->id, ['status' => 'OPEN']);
    $uom = ($this->makeUom)($tenant);
    $item = ($this->createItem)($tenant, $uom);
    ($this->createSalesOrderLine)($tenant, $order->id, $item->id, ['quantity' => '9.000000']);

    ($this->grantPermission)($user, 'sales-sales-orders-manage');

    (($this->completeOrder)($user, $order->id))->assertOk()
        ->assertJsonPath('data.status', 'COMPLETED');
});

it('12. completing can result in negative on hand inventory', function () {
    $tenant = ($this->makeTenant)();
    $user = ($this->makeUser)($tenant);
    $customer = ($this->createCustomer)($tenant);
    $order = ($this->createSalesOrder)($tenant, $customer->id, ['status' => 'OPEN']);
    $uom = ($this->makeUom)($tenant);
    $item = ($this->createItem)($tenant, $uom);
    ($this->createSalesOrderLine)($tenant, $order->id, $item->id, ['quantity' => '2.000000']);

    ($this->grantPermission)($user, 'sales-sales-orders-manage');

    (($this->completeOrder)($user, $order->id))->assertOk();

    expect($item->fresh()?->onHandQuantity())->toBe('-2.000000');
});

it('13. insufficient inventory does not return a validation error', function () {
    $tenant = ($this->makeTenant)();
    $user = ($this->makeUser)($tenant);
    $customer = ($this->createCustomer)($tenant);
    $order = ($this->createSalesOrder)($tenant, $customer->id, ['status' => 'OPEN']);
    $uom = ($this->makeUom)($tenant);
    $item = ($this->createItem)($tenant, $uom);
    ($this->createSalesOrderLine)($tenant, $order->id, $item->id, ['quantity' => '99.000000']);

    ($this->grantPermission)($user, 'sales-sales-orders-manage');

    $response = ($this->completeOrder)($user, $order->id)->assertOk();

    expect($response->json('errors'))->toBeNull();
});

it('14. draft to open creates no stock moves', function () {
    $tenant = ($this->makeTenant)();
    $user = ($this->makeUser)($tenant);
    $customer = ($this->createCustomer)($tenant);
    $order = ($this->createSalesOrder)($tenant, $customer->id);

    ($this->grantPermission)($user, 'sales-sales-orders-manage');

    $this->actingAs($user)
        ->patchJson(route('sales.orders.status.update', $order->id), [
            'status' => 'OPEN',
        ])->assertOk();

    expect(($this->fetchStockMovesForOrder)($order->id))->toHaveCount(0);
});

it('15. draft to cancelled creates no stock moves', function () {
    $tenant = ($this->makeTenant)();
    $user = ($this->makeUser)($tenant);
    $customer = ($this->createCustomer)($tenant);
    $order = ($this->createSalesOrder)($tenant, $customer->id);

    ($this->grantPermission)($user, 'sales-sales-orders-manage');

    $this->actingAs($user)
        ->patchJson(route('sales.orders.status.update', $order->id), [
            'status' => 'CANCELLED',
        ])->assertOk();

    expect(($this->fetchStockMovesForOrder)($order->id))->toHaveCount(0);
});

it('16. open to cancelled creates no stock moves', function () {
    $tenant = ($this->makeTenant)();
    $user = ($this->makeUser)($tenant);
    $customer = ($this->createCustomer)($tenant);
    $order = ($this->createSalesOrder)($tenant, $customer->id, ['status' => 'OPEN']);

    ($this->grantPermission)($user, 'sales-sales-orders-manage');

    $this->actingAs($user)
        ->patchJson(route('sales.orders.status.update', $order->id), [
            'status' => 'CANCELLED',
        ])->assertOk();

    expect(($this->fetchStockMovesForOrder)($order->id))->toHaveCount(0);
});

it('17. cancelled orders cannot create stock moves through later status changes', function () {
    $tenant = ($this->makeTenant)();
    $user = ($this->makeUser)($tenant);
    $customer = ($this->createCustomer)($tenant);
    $order = ($this->createSalesOrder)($tenant, $customer->id, ['status' => 'CANCELLED']);

    ($this->grantPermission)($user, 'sales-sales-orders-manage');

    $response = ($this->completeOrder)($user, $order->id)->assertStatus(422);

    ($this->assertStatusValidationErrors)($response);
    expect(($this->fetchStockMovesForOrder)($order->id))->toHaveCount(0);
});

it('18. completed orders cannot be completed again', function () {
    $tenant = ($this->makeTenant)();
    $user = ($this->makeUser)($tenant);
    $customer = ($this->createCustomer)($tenant);
    $order = ($this->createSalesOrder)($tenant, $customer->id, ['status' => 'OPEN']);
    $uom = ($this->makeUom)($tenant);
    $item = ($this->createItem)($tenant, $uom);
    ($this->createSalesOrderLine)($tenant, $order->id, $item->id);

    ($this->grantPermission)($user, 'sales-sales-orders-manage');

    (($this->completeOrder)($user, $order->id))->assertOk();

    $response = ($this->completeOrder)($user, $order->id)->assertStatus(422);

    ($this->assertStatusValidationErrors)($response);
    expect(($this->fetchStockMovesForOrder)($order->id))->toHaveCount(1);
});

it('19. retrying completion does not create duplicate stock moves', function () {
    $tenant = ($this->makeTenant)();
    $user = ($this->makeUser)($tenant);
    $customer = ($this->createCustomer)($tenant);
    $order = ($this->createSalesOrder)($tenant, $customer->id, ['status' => 'OPEN']);
    $uom = ($this->makeUom)($tenant);
    $item = ($this->createItem)($tenant, $uom);
    ($this->createSalesOrderLine)($tenant, $order->id, $item->id);

    ($this->grantPermission)($user, 'sales-sales-orders-manage');

    (($this->completeOrder)($user, $order->id))->assertOk();
    (($this->completeOrder)($user, $order->id))->assertStatus(422);

    expect(($this->fetchStockMovesForOrder)($order->id))->toHaveCount(1);
});

it('20. unauthorized user cannot complete a sales order', function () {
    $tenant = ($this->makeTenant)();
    $user = ($this->makeUser)($tenant);
    $customer = ($this->createCustomer)($tenant);
    $order = ($this->createSalesOrder)($tenant, $customer->id, ['status' => 'OPEN']);

    ($this->completeOrder)($user, $order->id)->assertForbidden();
});

it('21. unauthorized completion creates no stock moves', function () {
    $tenant = ($this->makeTenant)();
    $user = ($this->makeUser)($tenant);
    $customer = ($this->createCustomer)($tenant);
    $order = ($this->createSalesOrder)($tenant, $customer->id, ['status' => 'OPEN']);
    $uom = ($this->makeUom)($tenant);
    $item = ($this->createItem)($tenant, $uom);
    ($this->createSalesOrderLine)($tenant, $order->id, $item->id);

    ($this->completeOrder)($user, $order->id)->assertForbidden();

    expect(($this->fetchStockMovesForOrder)($order->id))->toHaveCount(0);
});

it('22. cross tenant user cannot complete another tenants sales order', function () {
    $tenant = ($this->makeTenant)();
    $otherTenant = ($this->makeTenant)();
    $user = ($this->makeUser)($tenant);
    $otherCustomer = ($this->createCustomer)($otherTenant);
    $otherOrder = ($this->createSalesOrder)($otherTenant, $otherCustomer->id, ['status' => 'OPEN']);

    ($this->grantPermission)($user, 'sales-sales-orders-manage');

    ($this->completeOrder)($user, $otherOrder->id)->assertNotFound();
});

it('23. cross tenant completion attempt creates no stock moves', function () {
    $tenant = ($this->makeTenant)();
    $otherTenant = ($this->makeTenant)();
    $user = ($this->makeUser)($tenant);
    $otherCustomer = ($this->createCustomer)($otherTenant);
    $otherOrder = ($this->createSalesOrder)($otherTenant, $otherCustomer->id, ['status' => 'OPEN']);
    $otherUom = ($this->makeUom)($otherTenant);
    $otherItem = ($this->createItem)($otherTenant, $otherUom);
    ($this->createSalesOrderLine)($otherTenant, $otherOrder->id, $otherItem->id);

    ($this->grantPermission)($user, 'sales-sales-orders-manage');

    ($this->completeOrder)($user, $otherOrder->id)->assertNotFound();

    expect(($this->fetchStockMovesForOrder)($otherOrder->id))->toHaveCount(0);
});

it('24. completion response remains json and does not redirect', function () {
    $tenant = ($this->makeTenant)();
    $user = ($this->makeUser)($tenant);
    $customer = ($this->createCustomer)($tenant);
    $order = ($this->createSalesOrder)($tenant, $customer->id, ['status' => 'OPEN']);
    $uom = ($this->makeUom)($tenant);
    $item = ($this->createItem)($tenant, $uom);
    ($this->createSalesOrderLine)($tenant, $order->id, $item->id);

    ($this->grantPermission)($user, 'sales-sales-orders-manage');

    ($this->completeOrder)($user, $order->id)
        ->assertOk()
        ->assertHeader('content-type', 'application/json');
});

it('25. failed stock move creation rolls back the status change', function () {
    $tenant = ($this->makeTenant)();
    $user = ($this->makeUser)($tenant);
    $customer = ($this->createCustomer)($tenant);
    $order = ($this->createSalesOrder)($tenant, $customer->id, ['status' => 'OPEN']);
    $uom = ($this->makeUom)($tenant);
    $validItem = ($this->createItem)($tenant, $uom, ['name' => 'Valid Item']);
    $secondItem = ($this->createItem)($tenant, $uom, ['name' => 'Second Item']);
    ($this->createSalesOrderLine)($tenant, $order->id, $validItem->id);
    ($this->createSalesOrderLine)($tenant, $order->id, $secondItem->id);

    ($this->grantPermission)($user, 'sales-sales-orders-manage');

    $this->app->bind(CompleteSalesOrderAction::class, function (): CompleteSalesOrderAction {
        return new class extends CompleteSalesOrderAction
        {
            private int $createdCount = 0;

            protected function afterStockMoveCreated(
                StockMove $stockMove,
                SalesOrder $salesOrder,
                SalesOrderLine $line
            ): void {
                parent::afterStockMoveCreated($stockMove, $salesOrder, $line);

                $this->createdCount++;

                if ($this->createdCount === 1) {
                    throw new \DomainException('Simulated stock move failure.');
                }
            }
        };
    });

    ($this->completeOrder)($user, $order->id)
        ->assertStatus(422)
        ->assertJsonPath('message', 'Simulated stock move failure.');

    expect(($this->fetchSalesOrder)($order->id)?->status)->toBe('OPEN');
});

it('26. failed stock move creation persists no partial stock moves', function () {
    $tenant = ($this->makeTenant)();
    $user = ($this->makeUser)($tenant);
    $customer = ($this->createCustomer)($tenant);
    $order = ($this->createSalesOrder)($tenant, $customer->id, ['status' => 'OPEN']);
    $uom = ($this->makeUom)($tenant);
    $validItem = ($this->createItem)($tenant, $uom, ['name' => 'Valid Item']);
    $secondItem = ($this->createItem)($tenant, $uom, ['name' => 'Second Item']);
    ($this->createSalesOrderLine)($tenant, $order->id, $validItem->id);
    ($this->createSalesOrderLine)($tenant, $order->id, $secondItem->id);

    ($this->grantPermission)($user, 'sales-sales-orders-manage');

    $this->app->bind(CompleteSalesOrderAction::class, function (): CompleteSalesOrderAction {
        return new class extends CompleteSalesOrderAction
        {
            private int $createdCount = 0;

            protected function afterStockMoveCreated(
                StockMove $stockMove,
                SalesOrder $salesOrder,
                SalesOrderLine $line
            ): void {
                parent::afterStockMoveCreated($stockMove, $salesOrder, $line);

                $this->createdCount++;

                if ($this->createdCount === 1) {
                    throw new \DomainException('Simulated stock move failure.');
                }
            }
        };
    });

    ($this->completeOrder)($user, $order->id)->assertStatus(422);

    expect(($this->fetchStockMovesForOrder)($order->id))->toHaveCount(0);
});

it('27. completion does not create invoice records', function () {
    $tenant = ($this->makeTenant)();
    $user = ($this->makeUser)($tenant);
    $customer = ($this->createCustomer)($tenant);
    $order = ($this->createSalesOrder)($tenant, $customer->id, ['status' => 'OPEN']);
    $uom = ($this->makeUom)($tenant);
    $item = ($this->createItem)($tenant, $uom);
    ($this->createSalesOrderLine)($tenant, $order->id, $item->id);

    ($this->grantPermission)($user, 'sales-sales-orders-manage');

    (($this->completeOrder)($user, $order->id))->assertOk();

    expect(Schema::hasTable('invoices'))->toBeFalse();
});

it('28. completion does not create payment records', function () {
    $tenant = ($this->makeTenant)();
    $user = ($this->makeUser)($tenant);
    $customer = ($this->createCustomer)($tenant);
    $order = ($this->createSalesOrder)($tenant, $customer->id, ['status' => 'OPEN']);
    $uom = ($this->makeUom)($tenant);
    $item = ($this->createItem)($tenant, $uom);
    ($this->createSalesOrderLine)($tenant, $order->id, $item->id);

    ($this->grantPermission)($user, 'sales-sales-orders-manage');

    (($this->completeOrder)($user, $order->id))->assertOk();

    expect(Schema::hasTable('payments'))->toBeFalse();
});

it('29. completion does not create shipping or fulfillment records', function () {
    $tenant = ($this->makeTenant)();
    $user = ($this->makeUser)($tenant);
    $customer = ($this->createCustomer)($tenant);
    $order = ($this->createSalesOrder)($tenant, $customer->id, ['status' => 'OPEN']);
    $uom = ($this->makeUom)($tenant);
    $item = ($this->createItem)($tenant, $uom);
    ($this->createSalesOrderLine)($tenant, $order->id, $item->id);

    ($this->grantPermission)($user, 'sales-sales-orders-manage');

    (($this->completeOrder)($user, $order->id))->assertOk();

    expect(Schema::hasTable('shipments'))->toBeFalse()
        ->and(Schema::hasTable('fulfillments'))->toBeFalse();
});

it('30. completing an open sales order with no lines succeeds and creates zero stock moves', function () {
    $tenant = ($this->makeTenant)();
    $user = ($this->makeUser)($tenant);
    $customer = ($this->createCustomer)($tenant);
    $order = ($this->createSalesOrder)($tenant, $customer->id, ['status' => 'OPEN']);

    ($this->grantPermission)($user, 'sales-sales-orders-manage');

    ($this->completeOrder)($user, $order->id)
        ->assertOk()
        ->assertJsonPath('data.status', 'COMPLETED');

    expect(($this->fetchSalesOrder)($order->id)?->status)->toBe('COMPLETED')
        ->and(($this->fetchStockMovesForOrder)($order->id))->toHaveCount(0);
});
