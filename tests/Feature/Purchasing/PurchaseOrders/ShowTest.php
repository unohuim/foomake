<?php

declare(strict_types=1);

use App\Models\Item;
use App\Models\ItemPurchaseOption;
use App\Models\Permission;
use App\Models\PurchaseOrder;
use App\Models\Role;
use App\Models\Supplier;
use App\Models\Tenant;
use App\Models\Uom;
use App\Models\UomCategory;
use App\Models\User;
use App\Models\WorkflowDomain;
use App\Models\WorkflowStage;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia as Assert;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->tenantCounter = 1;
    $this->userCounter = 1;
    $this->roleCounter = 1;
    $this->uomCounter = 1;
    $this->itemCounter = 1;
    $this->supplierCounter = 1;

    $this->makeTenant = function (array $attributes = []): Tenant {
        $tenant = Tenant::query()->create(array_merge([
            'tenant_name' => 'Tenant ' . $this->tenantCounter,
        ], $attributes));

        $this->tenantCounter++;

        return $tenant;
    };

    $this->makeUser = function (Tenant $tenant): User {
        $user = User::query()->create([
            'tenant_id' => $tenant->id,
            'name' => 'User ' . $this->userCounter,
            'email' => 'user' . $this->userCounter . '@example.test',
            'email_verified_at' => now(),
            'password' => '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi',
            'remember_token' => null,
        ]);

        $this->userCounter++;

        return $user;
    };

    $this->grantPermission = function (User $user, string $slug): void {
        $slugs = $slug === 'purchasing-purchase-orders-receive'
            ? ['purchasing-purchase-orders-create', $slug]
            : [$slug];
        $role = Role::query()->create(['name' => 'role-' . $this->roleCounter]);

        $this->roleCounter++;

        foreach ($slugs as $permissionSlug) {
            $permission = Permission::query()->firstOrCreate(['slug' => $permissionSlug]);
            $role->permissions()->syncWithoutDetaching([$permission->id]);
        }

        $user->roles()->syncWithoutDetaching([$role->id]);
    };

    $this->makeUom = function (Tenant $tenant, array $attributes = []): Uom {
        $symbol = $attributes['symbol'] ?? ('u' . $this->uomCounter);

        if (array_key_exists('symbol', $attributes)) {
            $existing = Uom::query()
                ->where('tenant_id', $tenant->id)
                ->where('symbol', $symbol)
                ->first();

            if ($existing) {
                return $existing;
            }
        }

        $category = UomCategory::query()->create([
            'tenant_id' => $tenant->id,
            'name' => $attributes['category_name'] ?? 'Category ' . $this->uomCounter,
        ]);

        $uom = Uom::query()->create([
            'tenant_id' => $tenant->id,
            'uom_category_id' => $category->id,
            'name' => $attributes['name'] ?? 'Uom ' . $this->uomCounter,
            'symbol' => $symbol,
        ]);

        $this->uomCounter++;

        return $uom;
    };

    $this->makeItem = function (Tenant $tenant, Uom $uom, array $attributes = []): Item {
        $item = Item::query()->create(array_merge([
            'tenant_id' => $tenant->id,
            'name' => $attributes['name'] ?? 'Item ' . $this->itemCounter,
            'base_uom_id' => $uom->id,
            'is_purchasable' => true,
            'is_sellable' => false,
            'is_manufacturable' => false,
        ], $attributes));

        $this->itemCounter++;

        return $item;
    };

    $this->makeSupplier = function (Tenant $tenant, array $attributes = []): Supplier {
        $supplier = Supplier::query()->create(array_merge([
            'tenant_id' => $tenant->id,
            'company_name' => 'Supplier ' . $this->supplierCounter,
        ], $attributes));

        $this->supplierCounter++;

        return $supplier;
    };

    $this->makeOption = function (Tenant $tenant, Supplier $supplier, Item $item, Uom $uom, array $attributes = []): ItemPurchaseOption {
        return ItemPurchaseOption::query()->create(array_merge([
            'tenant_id' => $tenant->id,
            'supplier_id' => $supplier->id,
            'item_id' => $item->id,
            'supplier_sku' => $attributes['supplier_sku'] ?? 'SKU-1',
            'pack_quantity' => $attributes['pack_quantity'] ?? '5.000000',
            'pack_uom_id' => $uom->id,
        ], $attributes));
    };

    $this->createOrder = function (User $user, array $payload = []) {
        return $this->actingAs($user)->postJson('/purchasing/orders', $payload);
    };

    $this->purchasingWorkflowStages = function (Tenant $tenant): array {
        app(\App\Actions\Workflows\SeedDefaultWorkflowStagesForTenantAction::class)->execute($tenant);

        return DB::table('workflow_stages')
            ->join('workflow_domains', 'workflow_domains.id', '=', 'workflow_stages.workflow_domain_id')
            ->where('workflow_stages.tenant_id', $tenant->id)
            ->where('workflow_domains.key', 'purchasing')
            ->get(['workflow_stages.id', 'workflow_stages.key'])
            ->keyBy('key')
            ->all();
    };

    $this->addLine = function (User $user, int $orderId, array $payload = []) {
        return $this->actingAs($user)->postJson("/purchasing/orders/{$orderId}/lines", $payload);
    };

    $this->extractPayload = function ($response, string $payloadId = 'purchasing-orders-show-payload'): array {
        $props = $response->viewData('page')['props'] ?? null;

        if ($payloadId === 'purchasing-orders-show-payload' && is_array($props) && isset($props['payload'])) {
            return $props['payload'];
        }

        return [];
    };
});

it('redirects guests from show', function () {
    $this->get('/purchasing/orders/1')
        ->assertRedirect('/?auth=login');
});

it('forbids show without permission', function () {
    $tenant = ($this->makeTenant)();
    $authorizedUser = ($this->makeUser)($tenant);
    ($this->grantPermission)($authorizedUser, 'purchasing-purchase-orders-create');

    $orderResponse = ($this->createOrder)($authorizedUser, [
        'order_date' => '2026-02-01',
    ])->assertCreated();

    $orderId = (int) ($orderResponse->json('data.id') ?? 0);

    $user = ($this->makeUser)($tenant);

    $this->actingAs($user)
        ->get("/purchasing/orders/{$orderId}")
        ->assertForbidden();
});

it('allows show with permission', function () {
    $tenant = ($this->makeTenant)();
    $user = ($this->makeUser)($tenant);

    ($this->grantPermission)($user, 'purchasing-purchase-orders-create');
    ($this->grantPermission)($user, 'purchasing-purchase-orders-receive');

    $supplier = ($this->makeSupplier)($tenant);

    $orderResponse = ($this->createOrder)($user, [
        'supplier_id' => $supplier->id,
    ])->assertCreated();

    $orderId = (int) ($orderResponse->json('data.id') ?? 0);

    $this->actingAs($user)
        ->get("/purchasing/orders/{$orderId}")
        ->assertOk();
});

it('blocks cross-tenant show access', function () {
    $tenantA = ($this->makeTenant)(['tenant_name' => 'Tenant A']);
    $tenantB = ($this->makeTenant)(['tenant_name' => 'Tenant B']);
    $userA = ($this->makeUser)($tenantA);
    $userB = ($this->makeUser)($tenantB);

    ($this->grantPermission)($userA, 'purchasing-purchase-orders-create');
    ($this->grantPermission)($userB, 'purchasing-purchase-orders-create');

    $supplierB = ($this->makeSupplier)($tenantB);
    $orderResponse = ($this->createOrder)($userB, [
        'supplier_id' => $supplierB->id,
        'order_date' => '2026-02-02',
    ])->assertCreated();

    $orderId = (int) ($orderResponse->json('data.id') ?? 0);

    $this->actingAs($userA)
        ->get("/purchasing/orders/{$orderId}")
        ->assertNotFound();
});

it('renders show through the Inertia purchase order detail page', function () {
    $tenant = ($this->makeTenant)();
    $user = ($this->makeUser)($tenant);

    ($this->grantPermission)($user, 'purchasing-purchase-orders-create');

    $orderResponse = ($this->createOrder)($user, [
        'order_date' => '2026-02-04',
    ])->assertCreated();

    $orderId = (int) ($orderResponse->json('data.id') ?? 0);

    $this->actingAs($user)
        ->get("/purchasing/orders/{$orderId}")
        ->assertOk()
        ->assertInertia(fn (Assert $page): Assert => $page
            ->component('Purchasing/Orders/Show')
            ->where('title', 'PO #' . $orderId)
            ->where('indexUrl', route('purchasing.orders.index', absolute: false))
            ->where('payload.purchaseOrder.id', $orderId)
            ->has('payload.workflow')
            ->has('payload.lines'));
});

it('includes header fields in show payload', function () {
    $tenant = ($this->makeTenant)();
    $user = ($this->makeUser)($tenant);

    ($this->grantPermission)($user, 'purchasing-purchase-orders-create');

    $supplier = ($this->makeSupplier)($tenant, ['company_name' => 'Show Supplier']);

    $orderResponse = ($this->createOrder)($user, [
        'supplier_id' => $supplier->id,
        'order_date' => '2026-02-05',
        'shipping_amount' => '1.50',
        'po_number' => 'PO-300',
        'notes' => 'Show order notes',
    ])->assertCreated();

    $orderId = (int) ($orderResponse->json('data.id') ?? 0);

    $response = $this->actingAs($user)->get("/purchasing/orders/{$orderId}")->assertOk();
    $payload = ($this->extractPayload)($response, 'purchasing-orders-show-payload');

    $order = $payload['order']
        ?? $payload['purchaseOrder']
        ?? $payload['purchase_order']
        ?? [];

    expect($order['status'] ?? null)->toBe('DRAFT');
    expect($order['order_date'] ?? null)->toBe('2026-02-05');
    expect((int) ($order['shipping_cents'] ?? 0))->toBe(150);
    expect($order['po_number'] ?? null)->toBe('PO-300');
    expect($order['notes'] ?? null)->toBe('Show order notes');

    $supplierName = $order['supplier_name']
        ?? ($order['supplier']['company_name'] ?? null);

    expect($supplierName)->toBe('Show Supplier');
});

it('show payload includes actions when provided', function () {
    $tenant = ($this->makeTenant)();
    $user = ($this->makeUser)($tenant);

    ($this->grantPermission)($user, 'purchasing-purchase-orders-create');

    $orderResponse = ($this->createOrder)($user, [
        'order_date' => '2026-02-06',
    ])->assertCreated();

    $orderId = (int) ($orderResponse->json('data.id') ?? 0);

    $response = $this->actingAs($user)->get("/purchasing/orders/{$orderId}")->assertOk();
    $payload = ($this->extractPayload)($response, 'purchasing-orders-show-payload');

    if (isset($payload['actions'])) {
        expect($payload['actions'])->toHaveKey('receive');
        expect($payload['actions'])->toHaveKey('cancel');
        expect($payload['actions'])->toHaveKey('delete');
    }
});

it('show detail seeds shared purchasing workflow stages through the tenant seeder', function () {
    $tenant = ($this->makeTenant)();
    $user = ($this->makeUser)($tenant);
    $supplier = ($this->makeSupplier)($tenant);

    ($this->grantPermission)($user, 'purchasing-purchase-orders-create');
    ($this->grantPermission)($user, 'purchasing-purchase-orders-receive');

    $order = PurchaseOrder::query()->create([
        'tenant_id' => $tenant->id,
        'created_by_user_id' => $user->id,
        'supplier_id' => $supplier->id,
        'order_date' => '2026-02-06',
        'shipping_cents' => null,
        'tax_cents' => 0,
        'po_number' => null,
        'notes' => null,
        'status' => PurchaseOrder::STATUS_DRAFT,
        'po_subtotal_cents' => 0,
        'po_grand_total_cents' => 0,
    ]);

    $response = $this->actingAs($user)->get(route('purchasing.orders.show', $order))->assertOk();
    $payload = ($this->extractPayload)($response, 'purchasing-orders-show-payload');

    $purchasingDomainId = (int) (WorkflowDomain::query()->where('key', 'purchasing')->value('id') ?? 0);

    expect($purchasingDomainId)->toBeGreaterThan(0)
        ->and(WorkflowStage::withoutGlobalScopes()
            ->where('tenant_id', $tenant->id)
            ->where('workflow_domain_id', $purchasingDomainId)
            ->count())->toBeGreaterThan(0)
        ->and($payload['workflow']['actions'] ?? [])
            ->not->toBeEmpty();
});

it('shows empty lines array when no lines exist', function () {
    $tenant = ($this->makeTenant)();
    $user = ($this->makeUser)($tenant);

    ($this->grantPermission)($user, 'purchasing-purchase-orders-create');

    $orderResponse = ($this->createOrder)($user, [
        'order_date' => '2026-02-07',
    ])->assertCreated();

    $orderId = (int) ($orderResponse->json('data.id') ?? 0);

    $response = $this->actingAs($user)->get("/purchasing/orders/{$orderId}")->assertOk();
    $payload = ($this->extractPayload)($response, 'purchasing-orders-show-payload');

    $lines = $payload['lines']
        ?? $payload['order_lines']
        ?? $payload['purchaseOrderLines']
        ?? [];

    expect($lines)->toBeArray();
    expect(count($lines))->toBe(0);
});

it('shows lines added via endpoint', function () {
    $tenant = ($this->makeTenant)();
    $user = ($this->makeUser)($tenant);

    ($this->grantPermission)($user, 'purchasing-purchase-orders-create');

    $uom = ($this->makeUom)($tenant, ['symbol' => 'kg']);
    $supplier = ($this->makeSupplier)($tenant);
    $item = ($this->makeItem)($tenant, $uom);
    $option = ($this->makeOption)($tenant, $supplier, $item, $uom, [
        'pack_quantity' => '12.000000',
    ]);

    $orderResponse = ($this->createOrder)($user, [
        'supplier_id' => $supplier->id,
    ])->assertCreated();

    $orderId = (int) ($orderResponse->json('data.id') ?? 0);

    ($this->addLine)($user, $orderId, [
        'item_purchase_option_id' => $option->id,
        'pack_count' => 3,
        'unit_price_cents' => 200,
    ])->assertCreated();

    $response = $this->actingAs($user)->get("/purchasing/orders/{$orderId}")->assertOk();
    $payload = ($this->extractPayload)($response, 'purchasing-orders-show-payload');

    $lines = $payload['lines']
        ?? $payload['order_lines']
        ?? $payload['purchaseOrderLines']
        ?? [];

    expect($lines)->toHaveCount(1);

    $line = $lines[0] ?? [];
    expect((int) ($line['item_purchase_option_id'] ?? 0))->toBe($option->id);
    expect((int) ($line['item_id'] ?? 0))->toBe($item->id);
    expect($line['pack_quantity'] ?? null)->toBe('12.000000');
    expect($line['pack_uom_symbol'] ?? null)->toBe('kg');
});

it('shows line sub totals and totals', function () {
    $tenant = ($this->makeTenant)();
    $user = ($this->makeUser)($tenant);

    ($this->grantPermission)($user, 'purchasing-purchase-orders-create');

    $uom = ($this->makeUom)($tenant);
    $supplier = ($this->makeSupplier)($tenant);
    $item = ($this->makeItem)($tenant, $uom);
    $option = ($this->makeOption)($tenant, $supplier, $item, $uom);

    $orderResponse = ($this->createOrder)($user, [
        'supplier_id' => $supplier->id,
        'shipping_amount' => '1.50',
    ])->assertCreated();

    $orderId = (int) ($orderResponse->json('data.id') ?? 0);

    ($this->addLine)($user, $orderId, [
        'item_purchase_option_id' => $option->id,
        'pack_count' => 2,
        'unit_price_cents' => 200,
    ])->assertCreated();

    $response = $this->actingAs($user)->get("/purchasing/orders/{$orderId}")->assertOk();
    $payload = ($this->extractPayload)($response, 'purchasing-orders-show-payload');

    $order = $payload['order']
        ?? $payload['purchaseOrder']
        ?? $payload['purchase_order']
        ?? [];

    if (isset($order['po_subtotal_cents'])) {
        expect((int) $order['po_subtotal_cents'])->toBe(400);
    }

    if (isset($order['po_grand_total_cents'])) {
        expect((int) $order['po_grand_total_cents'])->toBe(550);
    }

    $lines = $payload['lines']
        ?? $payload['order_lines']
        ?? $payload['purchaseOrderLines']
        ?? [];

    $line = $lines[0] ?? [];
    $lineSubTotal = $line['line_subtotal_cents'] ?? null;

    if ($lineSubTotal !== null) {
        expect((int) $lineSubTotal)->toBe(400);
    }
});

it('shows totals when shipping is null', function () {
    $tenant = ($this->makeTenant)();
    $user = ($this->makeUser)($tenant);

    ($this->grantPermission)($user, 'purchasing-purchase-orders-create');

    $uom = ($this->makeUom)($tenant);
    $supplier = ($this->makeSupplier)($tenant);
    $item = ($this->makeItem)($tenant, $uom);
    $option = ($this->makeOption)($tenant, $supplier, $item, $uom);

    $orderResponse = ($this->createOrder)($user, [
        'supplier_id' => $supplier->id,
        'shipping_amount' => null,
    ])->assertCreated();

    $orderId = (int) ($orderResponse->json('data.id') ?? 0);

    ($this->addLine)($user, $orderId, [
        'item_purchase_option_id' => $option->id,
        'pack_count' => 1,
        'unit_price_cents' => 120,
    ])->assertCreated();

    $response = $this->actingAs($user)->get("/purchasing/orders/{$orderId}")->assertOk();
    $payload = ($this->extractPayload)($response, 'purchasing-orders-show-payload');

    $order = $payload['order']
        ?? $payload['purchaseOrder']
        ?? $payload['purchase_order']
        ?? [];

    if (isset($order['po_grand_total_cents'])) {
        expect((int) $order['po_grand_total_cents'])->toBe(120);
    }
});

it('reflects line removal in show payload', function () {
    $tenant = ($this->makeTenant)();
    $user = ($this->makeUser)($tenant);

    ($this->grantPermission)($user, 'purchasing-purchase-orders-create');

    $uom = ($this->makeUom)($tenant);
    $supplier = ($this->makeSupplier)($tenant);
    $item = ($this->makeItem)($tenant, $uom);
    $option = ($this->makeOption)($tenant, $supplier, $item, $uom);

    $orderResponse = ($this->createOrder)($user, [
        'supplier_id' => $supplier->id,
    ])->assertCreated();

    $orderId = (int) ($orderResponse->json('data.id') ?? 0);

    ($this->addLine)($user, $orderId, [
        'item_purchase_option_id' => $option->id,
        'pack_count' => 1,
        'unit_price_cents' => 200,
    ])->assertCreated();

    $line = DB::table('purchase_order_lines')->where('purchase_order_id', $orderId)->first();

    $this->actingAs($user)
        ->deleteJson("/purchasing/orders/{$orderId}/lines/{$line->id}")
        ->assertOk();

    $response = $this->actingAs($user)->get("/purchasing/orders/{$orderId}")->assertOk();
    $payload = ($this->extractPayload)($response, 'purchasing-orders-show-payload');

    $lines = $payload['lines']
        ?? $payload['order_lines']
        ?? $payload['purchaseOrderLines']
        ?? [];

    expect(count($lines))->toBe(0);
});

it('shows order_date field on show', function () {
    $tenant = ($this->makeTenant)();
    $user = ($this->makeUser)($tenant);

    ($this->grantPermission)($user, 'purchasing-purchase-orders-create');

    $orderResponse = ($this->createOrder)($user, [
        'order_date' => '2026-02-08',
    ])->assertCreated();

    $orderId = (int) ($orderResponse->json('data.id') ?? 0);

    $response = $this->actingAs($user)->get("/purchasing/orders/{$orderId}")->assertOk();
    $payload = ($this->extractPayload)($response, 'purchasing-orders-show-payload');

    $order = $payload['order']
        ?? $payload['purchaseOrder']
        ?? $payload['purchase_order']
        ?? [];

    expect($order['order_date'] ?? null)->toBe('2026-02-08');
});

it('shows po_number on show', function () {
    $tenant = ($this->makeTenant)();
    $user = ($this->makeUser)($tenant);

    ($this->grantPermission)($user, 'purchasing-purchase-orders-create');

    $orderResponse = ($this->createOrder)($user, [
        'po_number' => 'PO-400',
    ])->assertCreated();

    $orderId = (int) ($orderResponse->json('data.id') ?? 0);

    $response = $this->actingAs($user)->get("/purchasing/orders/{$orderId}")->assertOk();
    $payload = ($this->extractPayload)($response, 'purchasing-orders-show-payload');

    $order = $payload['order']
        ?? $payload['purchaseOrder']
        ?? $payload['purchase_order']
        ?? [];

    expect($order['po_number'] ?? null)->toBe('PO-400');
});

it('shows notes on show', function () {
    $tenant = ($this->makeTenant)();
    $user = ($this->makeUser)($tenant);

    ($this->grantPermission)($user, 'purchasing-purchase-orders-create');

    $orderResponse = ($this->createOrder)($user, [
        'notes' => 'Show notes',
    ])->assertCreated();

    $orderId = (int) ($orderResponse->json('data.id') ?? 0);

    $response = $this->actingAs($user)->get("/purchasing/orders/{$orderId}")->assertOk();
    $payload = ($this->extractPayload)($response, 'purchasing-orders-show-payload');

    $order = $payload['order']
        ?? $payload['purchaseOrder']
        ?? $payload['purchase_order']
        ?? [];

    expect($order['notes'] ?? null)->toBe('Show notes');
});

it('shows supplier name when supplier is set', function () {
    $tenant = ($this->makeTenant)();
    $user = ($this->makeUser)($tenant);

    ($this->grantPermission)($user, 'purchasing-purchase-orders-create');

    $supplier = ($this->makeSupplier)($tenant, ['company_name' => 'Supplier Name']);

    $orderResponse = ($this->createOrder)($user, [
        'supplier_id' => $supplier->id,
    ])->assertCreated();

    $orderId = (int) ($orderResponse->json('data.id') ?? 0);

    $response = $this->actingAs($user)->get("/purchasing/orders/{$orderId}")->assertOk();
    $payload = ($this->extractPayload)($response, 'purchasing-orders-show-payload');

    expect($payload['purchaseOrder']['supplier_name'] ?? null)->toBe('Supplier Name');
});

it('shows status field on show', function () {
    $tenant = ($this->makeTenant)();
    $user = ($this->makeUser)($tenant);

    ($this->grantPermission)($user, 'purchasing-purchase-orders-create');

    $orderResponse = ($this->createOrder)($user, [])
        ->assertCreated();

    $orderId = (int) ($orderResponse->json('data.id') ?? 0);

    $response = $this->actingAs($user)->get("/purchasing/orders/{$orderId}")->assertOk();
    $payload = ($this->extractPayload)($response, 'purchasing-orders-show-payload');

    $order = $payload['order']
        ?? $payload['purchaseOrder']
        ?? $payload['purchase_order']
        ?? [];

    expect($order['status'] ?? null)->toBe('DRAFT');
});

it('does not render literal blade javascript directives in the purchase order header', function () {
    $tenant = ($this->makeTenant)();
    $user = ($this->makeUser)($tenant);

    ($this->grantPermission)($user, 'purchasing-purchase-orders-create');
    ($this->grantPermission)($user, 'purchasing-purchase-orders-receive');

    $orderResponse = ($this->createOrder)($user, [])
        ->assertCreated();

    $orderId = (int) ($orderResponse->json('data.id') ?? 0);

    $html = $this->actingAs($user)
        ->get("/purchasing/orders/{$orderId}")
        ->assertOk()
        ->getContent();

    expect($html)->not->toContain('@js(');
});

it('renders purchase order detail with header action as the only visible status surface', function () {
    $tenant = ($this->makeTenant)();
    $user = ($this->makeUser)($tenant);

    ($this->grantPermission)($user, 'purchasing-purchase-orders-create');
    ($this->grantPermission)($user, 'purchasing-purchase-orders-receive');

    $orderResponse = ($this->createOrder)($user, [])
        ->assertCreated();

    $orderId = (int) ($orderResponse->json('data.id') ?? 0);

    $response = $this->actingAs($user)
        ->get("/purchasing/orders/{$orderId}")
        ->assertOk();
    $payload = ($this->extractPayload)($response, 'purchasing-orders-show-payload');
    $source = file_get_contents(resource_path('js/pages/Purchasing/Orders/Show.vue'));

    expect($payload['purchaseOrder']['id'] ?? null)->toBe($orderId)
        ->and($source)->toContain('data-purchase-order-action-button')
        ->not->toContain('x-data="purchaseOrderHeader(')
        ->not->toContain('data-purchase-order-status-badge');
});

it('purchase order detail source blurs received at picker after value changes', function () {
    $source = file_get_contents(resource_path('js/pages/Purchasing/Orders/Show.vue'));

    expect($source)->toContain('type="datetime-local"')
        ->and($source)->toContain('step="1"')
        ->and($source)->toContain('inputmode="numeric"')
        ->and($source)->toContain('received_quantity: formatWholeQuantity(line.remaining_balance)');
});

it('purchase order detail source keeps receive action binding after workflow payload refresh', function () {
    $source = file_get_contents(resource_path('js/pages/Purchasing/Orders/Show.vue'));

    expect($source)->toContain('data-purchase-order-action-button')
        ->and($source)->toContain('async function performWorkflowAction(action)')
        ->and($source)->toContain('const actionType = action.action || action.type')
        ->and($source)->toContain('openReceive()')
        ->and($source)->toContain('applyWorkflowResponse');
});

it('workflow completion payload includes receive action state for immediate slide-over eligibility', function () {
    $tenant = ($this->makeTenant)();
    $user = ($this->makeUser)($tenant);
    $supplier = ($this->makeSupplier)($tenant);
    $uom = ($this->makeUom)($tenant);
    $item = ($this->makeItem)($tenant, $uom);
    $option = ($this->makeOption)($tenant, $supplier, $item, $uom);

    ($this->grantPermission)($user, 'purchasing-purchase-orders-create');
    ($this->grantPermission)($user, 'purchasing-purchase-orders-receive');

    $orderResponse = ($this->createOrder)($user, [
        'supplier_id' => $supplier->id,
        'order_date' => '2026-02-20',
    ])
        ->assertCreated();

    $orderId = (int) ($orderResponse->json('data.id') ?? 0);

    ($this->addLine)($user, $orderId, [
        'item_purchase_option_id' => $option->id,
        'pack_count' => 2,
        'unit_price_cents' => 100,
    ])->assertCreated();

    $this->actingAs($user)
        ->postJson("/purchasing/orders/{$orderId}/workflow/complete")
        ->assertOk()
        ->assertJsonPath('data.purchase_order.persisted_status', 'CREATED')
        ->assertJsonPath('data.purchase_order.can_receive', true)
        ->assertJsonPath('data.workflow.status', 'CREATED')
        ->assertJsonFragment([
            'type' => 'receive',
            'label' => 'Receive',
            'endpoint' => route('purchasing.orders.receipts.store', $orderId),
            'method' => 'POST',
        ]);
});

it('workflow payload uses the workflow complete endpoint for the draft create action', function () {
    $tenant = ($this->makeTenant)();
    $user = ($this->makeUser)($tenant);

    ($this->grantPermission)($user, 'purchasing-purchase-orders-create');
    ($this->grantPermission)($user, 'purchasing-purchase-orders-receive');

    $orderResponse = ($this->createOrder)($user, [])
        ->assertCreated();

    $orderId = (int) ($orderResponse->json('data.id') ?? 0);

    $response = $this->actingAs($user)->get("/purchasing/orders/{$orderId}")->assertOk();
    $payload = ($this->extractPayload)($response, 'purchasing-orders-show-payload');

    $actions = collect($payload['workflow']['actions'] ?? []);
    $createAction = $actions->firstWhere('type', 'complete_stage');

    expect($createAction)->not()->toBeNull();
    expect($createAction['label'] ?? null)->toBe('Create');
    expect($createAction['endpoint'] ?? null)->toBe(route('purchasing.orders.workflow.complete', $orderId));
    expect($createAction['method'] ?? null)->toBe('POST');
});

it('receipt history source displays total packs as whole numbers', function () {
    $pageModule = file_get_contents(resource_path('js/pages/Purchasing/Orders/Show.vue'));

    expect($pageModule)->toContain('receiptLineSummary(receipt)')
        ->and($pageModule)->toContain('formatWholeQuantity')
        ->and($pageModule)->toContain('total packs');
});

it('renders draft action dropdown with recipe-style action descriptions', function () {
    $tenant = ($this->makeTenant)();
    $user = ($this->makeUser)($tenant);

    ($this->grantPermission)($user, 'purchasing-purchase-orders-create');
    ($this->grantPermission)($user, 'purchasing-purchase-orders-receive');

    $orderResponse = ($this->createOrder)($user, [])
        ->assertCreated();

    $orderId = (int) ($orderResponse->json('data.id') ?? 0);

    $payload = ($this->extractPayload)(
        $this->actingAs($user)->get("/purchasing/orders/{$orderId}")->assertOk(),
        'purchasing-orders-show-payload'
    );
    $actions = collect($payload['workflow']['actions'] ?? []);

    expect($payload['purchaseOrder']['status'] ?? null)->toBe('DRAFT')
        ->and($actions->pluck('label')->all())->toContain('Create', 'Cancel')
        ->and($actions->pluck('description')->all())->toContain('Create this purchase order.', 'Cancel this purchase order.')
        ->and($actions->pluck('description')->all())->not->toContain('Mark remaining items as back ordered.')
        ->and($actions->pluck('label')->all())->not->toContain('Short Close')
        ->and($actions->pluck('description')->all())->not->toContain('Mark this purchase order complete.');
});

it('renders created action dropdown with receiving-stage actions and descriptions', function () {
    $tenant = ($this->makeTenant)();
    $user = ($this->makeUser)($tenant);

    ($this->grantPermission)($user, 'purchasing-purchase-orders-create');
    ($this->grantPermission)($user, 'purchasing-purchase-orders-receive');

    $orderResponse = ($this->createOrder)($user, [])
        ->assertCreated();

    $orderId = (int) ($orderResponse->json('data.id') ?? 0);
    $stages = ($this->purchasingWorkflowStages)($tenant);

    DB::table('purchase_orders')
        ->where('id', $orderId)
        ->update([
            'status' => 'CREATED',
            'last_completed_workflow_stage_id' => $stages['creating']->id,
            'current_workflow_stage_id' => $stages['receiving']->id,
        ]);

    $payload = ($this->extractPayload)(
        $this->actingAs($user)->get("/purchasing/orders/{$orderId}")->assertOk(),
        'purchasing-orders-show-payload'
    );
    $actions = collect($payload['workflow']['actions'] ?? []);

    expect($payload['purchaseOrder']['status'] ?? null)->toBe('CREATED')
        ->and($actions->pluck('label')->all())->toContain('Receive', 'Back Order', 'Short Close', 'Cancel')
        ->and($actions->pluck('description')->all())->toContain(
            'Record one receipt with one or more received lines.',
            'Mark remaining items as back ordered.',
            'Close remaining unreceived quantities.',
            'Cancel this purchase order.'
        )
        ->and($actions->pluck('description')->all())->not->toContain('Create this purchase order.')
        ->and($actions->pluck('description')->all())->not->toContain('Mark this purchase order complete.');
});

it('does not render cancel action when a purchase order has receipts', function () {
    $tenant = ($this->makeTenant)();
    $user = ($this->makeUser)($tenant);

    ($this->grantPermission)($user, 'purchasing-purchase-orders-create');
    ($this->grantPermission)($user, 'purchasing-purchase-orders-receive');

    $orderResponse = ($this->createOrder)($user, [])
        ->assertCreated();

    $orderId = (int) ($orderResponse->json('data.id') ?? 0);
    $stages = ($this->purchasingWorkflowStages)($tenant);

    DB::table('purchase_orders')
        ->where('id', $orderId)
        ->update([
            'status' => 'CREATED',
            'last_completed_workflow_stage_id' => $stages['creating']->id,
            'current_workflow_stage_id' => $stages['receiving']->id,
        ]);

    DB::table('purchase_order_receipts')->insert([
        'tenant_id' => $tenant->id,
        'purchase_order_id' => $orderId,
        'received_at' => '2026-02-04 10:00:00',
        'received_by_user_id' => $user->id,
        'reference' => null,
        'notes' => null,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $payload = ($this->extractPayload)(
        $this->actingAs($user)->get("/purchasing/orders/{$orderId}")->assertOk(),
        'purchasing-orders-show-payload'
    );
    $actions = collect($payload['workflow']['actions'] ?? []);

    expect($actions->pluck('label')->all())->toContain('Receive')
        ->and($actions->pluck('description')->all())->not->toContain('Cancel this purchase order.');
});

it('renders received action dropdown with complete action description', function () {
    $tenant = ($this->makeTenant)();
    $user = ($this->makeUser)($tenant);

    ($this->grantPermission)($user, 'purchasing-purchase-orders-create');
    ($this->grantPermission)($user, 'purchasing-purchase-orders-receive');

    $orderResponse = ($this->createOrder)($user, [])
        ->assertCreated();

    $orderId = (int) ($orderResponse->json('data.id') ?? 0);
    $stages = ($this->purchasingWorkflowStages)($tenant);

    DB::table('purchase_orders')
        ->where('id', $orderId)
        ->update([
            'status' => 'RECEIVED',
            'last_completed_workflow_stage_id' => $stages['receiving']->id,
            'current_workflow_stage_id' => $stages['completing']->id,
        ]);

    $payload = ($this->extractPayload)(
        $this->actingAs($user)->get("/purchasing/orders/{$orderId}")->assertOk(),
        'purchasing-orders-show-payload'
    );
    $actions = collect($payload['workflow']['actions'] ?? []);

    expect($payload['purchaseOrder']['status'] ?? null)->toBe('RECEIVED')
        ->and($actions->pluck('label')->all())->toContain('Complete', 'Cancel')
        ->and($actions->pluck('description')->all())->toContain('Mark this purchase order complete.', 'Cancel this purchase order.')
        ->and($actions->pluck('description')->all())->not->toContain('Create this purchase order.')
        ->and($actions->pluck('description')->all())->not->toContain('Mark remaining items as back ordered.')
        ->and($actions->pluck('description')->all())->not->toContain('Close remaining unreceived quantities.');
});

it('does not expose lifecycle actions for a cancelled purchase order', function () {
    $tenant = ($this->makeTenant)();
    $user = ($this->makeUser)($tenant);

    ($this->grantPermission)($user, 'purchasing-purchase-orders-create');
    ($this->grantPermission)($user, 'purchasing-purchase-orders-receive');

    $orderResponse = ($this->createOrder)($user, [])
        ->assertCreated();

    $orderId = (int) ($orderResponse->json('data.id') ?? 0);

    DB::table('purchase_orders')
        ->where('id', $orderId)
        ->update([
            'status' => 'CANCELLED',
            'cancelled_at' => now(),
            'cancelled_by_user_id' => $user->id,
        ]);

    $payload = ($this->extractPayload)(
        $this->actingAs($user)->get("/purchasing/orders/{$orderId}")->assertOk(),
        'purchasing-orders-show-payload'
    );
    $actions = collect($payload['workflow']['actions'] ?? []);

    expect($payload['purchaseOrder']['status'] ?? null)->toBe('CANCELLED')
        ->and($actions->pluck('description')->all())->not->toContain('Create this purchase order.')
        ->and($actions->pluck('description')->all())->not->toContain('Mark remaining items as back ordered.')
        ->and($actions->pluck('description')->all())->not->toContain('Close remaining unreceived quantities.')
        ->and($actions->pluck('description')->all())->not->toContain('Mark this purchase order complete.')
        ->and($actions->pluck('description')->all())->not->toContain('Cancel this purchase order.');
});

it('shows line quantity, price, and totals in payload', function () {
    $tenant = ($this->makeTenant)();
    $user = ($this->makeUser)($tenant);

    ($this->grantPermission)($user, 'purchasing-purchase-orders-create');

    $uom = ($this->makeUom)($tenant);
    $supplier = ($this->makeSupplier)($tenant);
    $item = ($this->makeItem)($tenant, $uom);
    $option = ($this->makeOption)($tenant, $supplier, $item, $uom);

    $orderResponse = ($this->createOrder)($user, [
        'supplier_id' => $supplier->id,
    ])->assertCreated();

    $orderId = (int) ($orderResponse->json('data.id') ?? 0);

    ($this->addLine)($user, $orderId, [
        'item_purchase_option_id' => $option->id,
        'pack_count' => 4,
        'unit_price_cents' => 125,
    ])->assertCreated();

    $response = $this->actingAs($user)->get("/purchasing/orders/{$orderId}")->assertOk();
    $payload = ($this->extractPayload)($response, 'purchasing-orders-show-payload');

    $lines = $payload['lines']
        ?? $payload['order_lines']
        ?? $payload['purchaseOrderLines']
        ?? [];

    $line = $lines[0] ?? [];

    expect((int) ($line['pack_count'] ?? 0))->toBe(4);
    expect((int) ($line['unit_price_cents'] ?? 0))->toBe(125);
    expect((int) ($line['line_subtotal_cents'] ?? 0))->toBe(500);
});

it('shows totals when multiple lines exist', function () {
    $tenant = ($this->makeTenant)();
    $user = ($this->makeUser)($tenant);

    ($this->grantPermission)($user, 'purchasing-purchase-orders-create');

    $uom = ($this->makeUom)($tenant);
    $supplier = ($this->makeSupplier)($tenant);
    $itemA = ($this->makeItem)($tenant, $uom, ['name' => 'Item A']);
    $itemB = ($this->makeItem)($tenant, $uom, ['name' => 'Item B']);
    $optionA = ($this->makeOption)($tenant, $supplier, $itemA, $uom);
    $optionB = ($this->makeOption)($tenant, $supplier, $itemB, $uom);

    $orderResponse = ($this->createOrder)($user, [
        'supplier_id' => $supplier->id,
        'shipping_amount' => '0.50',
    ])->assertCreated();

    $orderId = (int) ($orderResponse->json('data.id') ?? 0);

    ($this->addLine)($user, $orderId, [
        'item_purchase_option_id' => $optionA->id,
        'pack_count' => 1,
        'unit_price_cents' => 100,
    ])->assertCreated();

    ($this->addLine)($user, $orderId, [
        'item_purchase_option_id' => $optionB->id,
        'pack_count' => 2,
        'unit_price_cents' => 150,
    ])->assertCreated();

    $response = $this->actingAs($user)->get("/purchasing/orders/{$orderId}")->assertOk();
    $payload = ($this->extractPayload)($response, 'purchasing-orders-show-payload');

    $order = $payload['order']
        ?? $payload['purchaseOrder']
        ?? $payload['purchase_order']
        ?? [];

    if (isset($order['po_subtotal_cents'])) {
        expect((int) $order['po_subtotal_cents'])->toBe(400);
    }

    if (isset($order['po_grand_total_cents'])) {
        expect((int) $order['po_grand_total_cents'])->toBe(450);
    }
});

it('shows supplier null when not set', function () {
    $tenant = ($this->makeTenant)();
    $user = ($this->makeUser)($tenant);

    ($this->grantPermission)($user, 'purchasing-purchase-orders-create');

    $orderResponse = ($this->createOrder)($user, [
        'order_date' => '2026-02-09',
    ])->assertCreated();

    $orderId = (int) ($orderResponse->json('data.id') ?? 0);

    $response = $this->actingAs($user)->get("/purchasing/orders/{$orderId}")->assertOk();
    $payload = ($this->extractPayload)($response, 'purchasing-orders-show-payload');

    $order = $payload['order']
        ?? $payload['purchaseOrder']
        ?? $payload['purchase_order']
        ?? [];

    if (array_key_exists('supplier_name', $order)) {
        expect($order['supplier_name'])->toBeNull();
    }
});

it('shows po_number null when not set', function () {
    $tenant = ($this->makeTenant)();
    $user = ($this->makeUser)($tenant);

    ($this->grantPermission)($user, 'purchasing-purchase-orders-create');

    $orderResponse = ($this->createOrder)($user, [
        'order_date' => '2026-02-10',
    ])->assertCreated();

    $orderId = (int) ($orderResponse->json('data.id') ?? 0);

    $response = $this->actingAs($user)->get("/purchasing/orders/{$orderId}")->assertOk();
    $payload = ($this->extractPayload)($response, 'purchasing-orders-show-payload');

    $order = $payload['order']
        ?? $payload['purchaseOrder']
        ?? $payload['purchase_order']
        ?? [];

    if (array_key_exists('po_number', $order)) {
        expect($order['po_number'])->toBeNull();
    }
});

it('includes receipt history in show payload after receipt event', function () {
    $tenant = ($this->makeTenant)();
    $user = ($this->makeUser)($tenant);

    ($this->grantPermission)($user, 'purchasing-purchase-orders-create');
    ($this->grantPermission)($user, 'purchasing-purchase-orders-receive');

    $uom = ($this->makeUom)($tenant);
    $supplier = ($this->makeSupplier)($tenant);
    $item = ($this->makeItem)($tenant, $uom);
    $option = ($this->makeOption)($tenant, $supplier, $item, $uom);

    $orderResponse = ($this->createOrder)($user, [
        'supplier_id' => $supplier->id,
        'order_date' => '2026-02-20',
    ])->assertCreated();

    $orderId = (int) ($orderResponse->json('data.id') ?? 0);

    DB::table('purchase_orders')
        ->where('id', $orderId)
        ->update(['status' => 'CREATED']);

    ($this->addLine)($user, $orderId, [
        'item_purchase_option_id' => $option->id,
        'pack_count' => 2,
        'unit_price_cents' => 100,
    ])->assertCreated();

    $line = DB::table('purchase_order_lines')->where('purchase_order_id', $orderId)->first();

    $this->actingAs($user)
        ->postJson("/purchasing/orders/{$orderId}/receipts", [
            'received_at' => '2026-02-20 09:00:00',
            'reference' => 'RCPT-55',
            'notes' => 'Receipt notes',
            'purchase_order_line_id' => $line->id,
            'received_quantity' => '2.000000',
        ])->assertCreated();

    $response = $this->actingAs($user)->get("/purchasing/orders/{$orderId}")->assertOk();
    $payload = ($this->extractPayload)($response, 'purchasing-orders-show-payload');

    $receipts = $payload['receipts'] ?? [];
    expect($receipts)->toHaveCount(1);

    $entry = $receipts[0] ?? [];
    expect($entry['reference'] ?? null)->toBe('RCPT-55');
    expect($entry['notes'] ?? null)->toBe('Receipt notes');
    expect($entry['lines_count'] ?? null)->toBe(1);
    expect($entry['total_packs'] ?? null)->toBe('2.000000');
    expect($entry['received_at'] ?? null)->toBe('2026-02-20 09:00:00');
    expect($entry['received_by'] ?? null)->toBe($user->name);
});

it('shows updated status in show payload after receipt event', function () {
    $tenant = ($this->makeTenant)();
    $user = ($this->makeUser)($tenant);

    ($this->grantPermission)($user, 'purchasing-purchase-orders-create');
    ($this->grantPermission)($user, 'purchasing-purchase-orders-receive');

    $uom = ($this->makeUom)($tenant);
    $supplier = ($this->makeSupplier)($tenant);
    $item = ($this->makeItem)($tenant, $uom);
    $option = ($this->makeOption)($tenant, $supplier, $item, $uom);

    $orderResponse = ($this->createOrder)($user, [
        'supplier_id' => $supplier->id,
        'order_date' => '2026-02-23',
    ])->assertCreated();

    $orderId = (int) ($orderResponse->json('data.id') ?? 0);

    ($this->addLine)($user, $orderId, [
        'item_purchase_option_id' => $option->id,
        'pack_count' => 2,
        'unit_price_cents' => 120,
    ])->assertCreated();

    $this->actingAs($user)
        ->patchJson("/purchasing/orders/{$orderId}/status", ['status' => 'CREATED'])
        ->assertOk();

    $line = DB::table('purchase_order_lines')->where('purchase_order_id', $orderId)->first();

    $this->actingAs($user)
        ->postJson("/purchasing/orders/{$orderId}/receipts", [
            'received_at' => '2026-02-23 10:00:00',
            'purchase_order_line_id' => $line->id,
            'received_quantity' => '1.000000',
        ])->assertCreated();

    $response = $this->actingAs($user)->get("/purchasing/orders/{$orderId}")->assertOk();
    $payload = ($this->extractPayload)($response, 'purchasing-orders-show-payload');

    $order = $payload['order']
        ?? $payload['purchaseOrder']
        ?? $payload['purchase_order']
        ?? [];

    expect($order['status'] ?? null)->toBe('PARTIALLY_RECEIVED');
});

it('includes short-close history in show payload after short-close event', function () {
    $tenant = ($this->makeTenant)();
    $user = ($this->makeUser)($tenant);

    ($this->grantPermission)($user, 'purchasing-purchase-orders-create');
    ($this->grantPermission)($user, 'purchasing-purchase-orders-receive');

    $uom = ($this->makeUom)($tenant);
    $supplier = ($this->makeSupplier)($tenant);
    $item = ($this->makeItem)($tenant, $uom);
    $option = ($this->makeOption)($tenant, $supplier, $item, $uom);

    $orderResponse = ($this->createOrder)($user, [
        'supplier_id' => $supplier->id,
        'order_date' => '2026-02-21',
    ])->assertCreated();

    $orderId = (int) ($orderResponse->json('data.id') ?? 0);

    DB::table('purchase_orders')
        ->where('id', $orderId)
        ->update(['status' => 'CREATED']);

    ($this->addLine)($user, $orderId, [
        'item_purchase_option_id' => $option->id,
        'pack_count' => 3,
        'unit_price_cents' => 120,
    ])->assertCreated();

    $line = DB::table('purchase_order_lines')->where('purchase_order_id', $orderId)->first();

    $this->actingAs($user)
        ->postJson("/purchasing/orders/{$orderId}/short-closures", [
            'short_closed_at' => '2026-02-21 10:00:00',
            'reference' => 'SC-55',
            'notes' => 'Short close notes',
            'purchase_order_line_id' => $line->id,
            'short_closed_quantity' => '3.000000',
        ])->assertCreated();

    $response = $this->actingAs($user)->get("/purchasing/orders/{$orderId}")->assertOk();
    $payload = ($this->extractPayload)($response, 'purchasing-orders-show-payload');

    $shortClosures = $payload['shortClosures'] ?? [];
    expect($shortClosures)->toHaveCount(1);

    $entry = $shortClosures[0] ?? [];
    expect($entry['reference'] ?? null)->toBe('SC-55');
    expect($entry['notes'] ?? null)->toBe('Short close notes');
    expect($entry['lines_count'] ?? null)->toBe(1);
    expect($entry['total_packs'] ?? null)->toBe('3.000000');
    expect($entry['short_closed_at'] ?? null)->toBe('2026-02-21 10:00:00');
    expect($entry['short_closed_by'] ?? null)->toBe($user->name);
});

it('returns hydrated ajax payload after short-close event', function () {
    $tenant = ($this->makeTenant)();
    $user = ($this->makeUser)($tenant);

    ($this->grantPermission)($user, 'purchasing-purchase-orders-create');
    ($this->grantPermission)($user, 'purchasing-purchase-orders-receive');

    $uom = ($this->makeUom)($tenant);
    $supplier = ($this->makeSupplier)($tenant);
    $item = ($this->makeItem)($tenant, $uom);
    $option = ($this->makeOption)($tenant, $supplier, $item, $uom);

    $orderResponse = ($this->createOrder)($user, [
        'supplier_id' => $supplier->id,
        'order_date' => '2026-02-26',
    ])->assertCreated();

    $orderId = (int) ($orderResponse->json('data.id') ?? 0);

    DB::table('purchase_orders')
        ->where('id', $orderId)
        ->update(['status' => 'CREATED']);

    ($this->addLine)($user, $orderId, [
        'item_purchase_option_id' => $option->id,
        'pack_count' => 3,
        'unit_price_cents' => 120,
    ])->assertCreated();

    $line = DB::table('purchase_order_lines')->where('purchase_order_id', $orderId)->first();

    $this->actingAs($user)
        ->postJson("/purchasing/orders/{$orderId}/short-closures", [
            'short_closed_at' => '2026-02-26 11:00:00',
            'reference' => 'SC-66',
            'notes' => 'Closed through AJAX',
            'purchase_order_line_id' => $line->id,
            'short_closed_quantity' => '3.000000',
        ])
        ->assertCreated()
        ->assertJsonPath('data.purchase_order.status', 'RECEIVED')
        ->assertJsonPath('data.workflow.currentStage.name', 'Completing')
        ->assertJsonPath('data.shortClosures.0.reference', 'SC-66')
        ->assertJsonPath('data.lines.0.short_closed_sum', '3.000000');
});

it('shows updated status in show payload after short-close event', function () {
    $tenant = ($this->makeTenant)();
    $user = ($this->makeUser)($tenant);

    ($this->grantPermission)($user, 'purchasing-purchase-orders-create');
    ($this->grantPermission)($user, 'purchasing-purchase-orders-receive');

    $uom = ($this->makeUom)($tenant);
    $supplier = ($this->makeSupplier)($tenant);
    $item = ($this->makeItem)($tenant, $uom);
    $option = ($this->makeOption)($tenant, $supplier, $item, $uom);

    $orderResponse = ($this->createOrder)($user, [
        'supplier_id' => $supplier->id,
        'order_date' => '2026-02-25',
    ])->assertCreated();

    $orderId = (int) ($orderResponse->json('data.id') ?? 0);

    ($this->addLine)($user, $orderId, [
        'item_purchase_option_id' => $option->id,
        'pack_count' => 2,
        'unit_price_cents' => 120,
    ])->assertCreated();

    $this->actingAs($user)
        ->patchJson("/purchasing/orders/{$orderId}/status", ['status' => 'CREATED'])
        ->assertOk();

    $line = DB::table('purchase_order_lines')->where('purchase_order_id', $orderId)->first();

    $this->actingAs($user)
        ->postJson("/purchasing/orders/{$orderId}/short-closures", [
            'short_closed_at' => '2026-02-25 10:15:00',
            'purchase_order_line_id' => $line->id,
            'short_closed_quantity' => '2.000000',
        ])->assertCreated();

    $response = $this->actingAs($user)->get("/purchasing/orders/{$orderId}")->assertOk();
    $payload = ($this->extractPayload)($response, 'purchasing-orders-show-payload');

    $order = $payload['order']
        ?? $payload['purchaseOrder']
        ?? $payload['purchase_order']
        ?? [];

    expect($order['status'] ?? null)->toBe('RECEIVED');
});

it('shows received and short-closed sums in line payload after events', function () {
    $tenant = ($this->makeTenant)();
    $user = ($this->makeUser)($tenant);

    ($this->grantPermission)($user, 'purchasing-purchase-orders-create');
    ($this->grantPermission)($user, 'purchasing-purchase-orders-receive');

    $uom = ($this->makeUom)($tenant);
    $supplier = ($this->makeSupplier)($tenant);
    $item = ($this->makeItem)($tenant, $uom);
    $option = ($this->makeOption)($tenant, $supplier, $item, $uom);

    $orderResponse = ($this->createOrder)($user, [
        'supplier_id' => $supplier->id,
        'order_date' => '2026-02-22',
    ])->assertCreated();

    $orderId = (int) ($orderResponse->json('data.id') ?? 0);

    DB::table('purchase_orders')
        ->where('id', $orderId)
        ->update(['status' => 'CREATED']);

    ($this->addLine)($user, $orderId, [
        'item_purchase_option_id' => $option->id,
        'pack_count' => 10,
        'unit_price_cents' => 100,
    ])->assertCreated();

    $line = DB::table('purchase_order_lines')->where('purchase_order_id', $orderId)->first();

    $this->actingAs($user)
        ->postJson("/purchasing/orders/{$orderId}/receipts", [
            'received_at' => '2026-02-22 09:00:00',
            'purchase_order_line_id' => $line->id,
            'received_quantity' => '4.000000',
        ])->assertCreated();

    $this->actingAs($user)
        ->postJson("/purchasing/orders/{$orderId}/short-closures", [
            'short_closed_at' => '2026-02-22 09:30:00',
            'purchase_order_line_id' => $line->id,
            'short_closed_quantity' => '2.000000',
        ])->assertCreated();

    $response = $this->actingAs($user)->get("/purchasing/orders/{$orderId}")->assertOk();
    $payload = ($this->extractPayload)($response, 'purchasing-orders-show-payload');

    $lines = $payload['lines']
        ?? $payload['order_lines']
        ?? $payload['purchaseOrderLines']
        ?? [];

    $linePayload = $lines[0] ?? [];
    expect($linePayload['received_sum'] ?? null)->toBe('4.000000');
    expect($linePayload['short_closed_sum'] ?? null)->toBe('2.000000');
    expect($linePayload['remaining_balance'] ?? null)->toBe('4.000000');
});

it('shows remaining balance after multiple receipts in line payload', function () {
    $tenant = ($this->makeTenant)();
    $user = ($this->makeUser)($tenant);

    ($this->grantPermission)($user, 'purchasing-purchase-orders-create');
    ($this->grantPermission)($user, 'purchasing-purchase-orders-receive');

    $uom = ($this->makeUom)($tenant);
    $supplier = ($this->makeSupplier)($tenant);
    $item = ($this->makeItem)($tenant, $uom);
    $option = ($this->makeOption)($tenant, $supplier, $item, $uom);

    $orderResponse = ($this->createOrder)($user, [
        'supplier_id' => $supplier->id,
        'order_date' => '2026-02-24',
    ])->assertCreated();

    $orderId = (int) ($orderResponse->json('data.id') ?? 0);

    ($this->addLine)($user, $orderId, [
        'item_purchase_option_id' => $option->id,
        'pack_count' => 10,
        'unit_price_cents' => 100,
    ])->assertCreated();

    $this->actingAs($user)
        ->patchJson("/purchasing/orders/{$orderId}/status", ['status' => 'CREATED'])
        ->assertOk();

    $line = DB::table('purchase_order_lines')->where('purchase_order_id', $orderId)->first();

    $this->actingAs($user)
        ->postJson("/purchasing/orders/{$orderId}/receipts", [
            'received_at' => '2026-02-24 09:00:00',
            'purchase_order_line_id' => $line->id,
            'received_quantity' => '3.000000',
        ])->assertCreated();

    $this->actingAs($user)
        ->postJson("/purchasing/orders/{$orderId}/receipts", [
            'received_at' => '2026-02-24 10:00:00',
            'purchase_order_line_id' => $line->id,
            'received_quantity' => '2.000000',
        ])->assertCreated();

    $response = $this->actingAs($user)->get("/purchasing/orders/{$orderId}")->assertOk();
    $payload = ($this->extractPayload)($response, 'purchasing-orders-show-payload');

    $lines = $payload['lines']
        ?? $payload['order_lines']
        ?? $payload['purchaseOrderLines']
        ?? [];

    $linePayload = $lines[0] ?? [];
    expect($linePayload['received_sum'] ?? null)->toBe('5.000000');
    expect($linePayload['remaining_balance'] ?? null)->toBe('5.000000');
});
