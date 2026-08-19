<?php

declare(strict_types=1);

use App\Models\InventoryCount;
use App\Models\Item;
use App\Models\Permission;
use App\Models\Role;
use App\Models\StockMove;
use App\Models\Tenant;
use App\Models\Uom;
use App\Models\UomCategory;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->tenant = Tenant::factory()->create([
        'currency_code' => 'USD',
    ]);

    $this->user = User::factory()->create([
        'tenant_id' => $this->tenant->id,
    ]);

    $this->grantPermission = function (User $user, string $permissionSlug): void {
        $permission = Permission::query()->firstOrCreate([
            'slug' => $permissionSlug,
        ]);

        $role = Role::query()->firstOrCreate([
            'name' => $permissionSlug . '-' . $user->id,
        ]);

        $role->permissions()->syncWithoutDetaching([$permission->id]);
        $user->roles()->syncWithoutDetaching([$role->id]);
    };

    $this->makeUom = function (Tenant $tenant): Uom {
        $suffix = (string) Str::uuid();

        $category = UomCategory::query()->create([
            'tenant_id' => $tenant->id,
            'name' => 'Category ' . $suffix,
        ]);

        return Uom::query()->create([
            'tenant_id' => $tenant->id,
            'uom_category_id' => $category->id,
            'name' => 'Uom ' . $suffix,
            'symbol' => 'u' . str_replace('-', '', $suffix),
            'display_precision' => 3,
        ]);
    };

    $this->postCreate = function (User $user, array $payload = []) {
        if (array_key_exists('starting_quantity', $payload) && ! array_key_exists('is_stockable', $payload)) {
            $payload['is_stockable'] = true;
        }

        return $this->actingAs($user)->postJson(route('materials.store'), $payload);
    };
});

test('1. guests are redirected to login when posting materials with starting quantity data', function (): void {
    $uom = ($this->makeUom)($this->tenant);

    $this->post(route('materials.store'), [
        'name' => 'Flour',
        'base_uom_id' => $uom->id,
        'starting_quantity' => '5.000000',
    ])->assertRedirect('/?auth=login');
});

test('2. material store forbids authenticated users without inventory materials manage permission', function (): void {
    $uom = ($this->makeUom)($this->tenant);

    ($this->postCreate)($this->user, [
        'name' => 'Flour',
        'base_uom_id' => $uom->id,
        'starting_quantity' => '5.000000',
    ])->assertForbidden();
});

test('3. material store rejects a base uom from another tenant when starting quantity is submitted', function (): void {
    ($this->grantPermission)($this->user, 'inventory-materials-manage');

    $otherTenant = Tenant::factory()->create();
    $otherUom = ($this->makeUom)($otherTenant);

    ($this->postCreate)($this->user, [
        'name' => 'Flour',
        'base_uom_id' => $otherUom->id,
        'starting_quantity' => '5.000000',
    ])->assertUnprocessable()->assertJsonValidationErrors(['base_uom_id']);
});

test('4. material can be created without starting quantity', function (): void {
    ($this->grantPermission)($this->user, 'inventory-materials-manage');
    $uom = ($this->makeUom)($this->tenant);

    ($this->postCreate)($this->user, [
        'name' => 'Flour',
        'base_uom_id' => $uom->id,
    ])->assertCreated()->assertJsonPath('data.name', 'Flour');
});

test('5. null starting quantity creates the material without stock history records', function (): void {
    ($this->grantPermission)($this->user, 'inventory-materials-manage');
    $uom = ($this->makeUom)($this->tenant);

    ($this->postCreate)($this->user, [
        'name' => 'Sugar',
        'base_uom_id' => $uom->id,
        'starting_quantity' => null,
    ])->assertCreated();

    $item = Item::query()->where('name', 'Sugar')->firstOrFail();

    expect($item->stockMoves()->count())->toBe(0)
        ->and(InventoryCount::query()->where('tenant_id', $this->tenant->id)->count())->toBe(0)
        ->and($item->onHandQuantity())->toBe('0.000000');
});

test('6. blank starting quantity creates the material without stock history records', function (): void {
    ($this->grantPermission)($this->user, 'inventory-materials-manage');
    $uom = ($this->makeUom)($this->tenant);

    ($this->postCreate)($this->user, [
        'name' => 'Salt',
        'base_uom_id' => $uom->id,
        'starting_quantity' => '',
    ])->assertCreated();

    $item = Item::query()->where('name', 'Salt')->firstOrFail();

    expect($item->stockMoves()->count())->toBe(0)
        ->and(InventoryCount::query()->where('tenant_id', $this->tenant->id)->count())->toBe(0)
        ->and($item->onHandQuantity())->toBe('0.000000');
});

test('7. zero starting quantity does not create unnecessary stock history records', function (): void {
    ($this->grantPermission)($this->user, 'inventory-materials-manage');
    $uom = ($this->makeUom)($this->tenant);

    ($this->postCreate)($this->user, [
        'name' => 'Oil',
        'base_uom_id' => $uom->id,
        'starting_quantity' => '0',
    ])->assertCreated();

    $item = Item::query()->where('name', 'Oil')->firstOrFail();

    expect($item->stockMoves()->count())->toBe(0)
        ->and(InventoryCount::query()->where('tenant_id', $this->tenant->id)->count())->toBe(0)
        ->and($item->onHandQuantity())->toBe('0.000000');
});

test('8. zero decimal starting quantity does not create unnecessary stock history records', function (): void {
    ($this->grantPermission)($this->user, 'inventory-materials-manage');
    $uom = ($this->makeUom)($this->tenant);

    ($this->postCreate)($this->user, [
        'name' => 'Milk Powder',
        'base_uom_id' => $uom->id,
        'starting_quantity' => '0.000000',
    ])->assertCreated();

    $item = Item::query()->where('name', 'Milk Powder')->firstOrFail();

    expect($item->stockMoves()->count())->toBe(0)
        ->and(InventoryCount::query()->where('tenant_id', $this->tenant->id)->count())->toBe(0)
        ->and($item->onHandQuantity())->toBe('0.000000');
});

test('9. positive starting quantity creates one initial stock inventory count record', function (): void {
    ($this->grantPermission)($this->user, 'inventory-materials-manage');
    $uom = ($this->makeUom)($this->tenant);

    ($this->postCreate)($this->user, [
        'name' => 'Butter',
        'base_uom_id' => $uom->id,
        'starting_quantity' => '5.000000',
    ])->assertCreated();

    $count = InventoryCount::query()->where('tenant_id', $this->tenant->id)->firstOrFail();

    expect(InventoryCount::query()->where('tenant_id', $this->tenant->id)->count())->toBe(1);

    $this->assertDatabaseHas('notes', [
        'tenant_id' => $this->tenant->id,
        'noteable_type' => InventoryCount::class,
        'noteable_id' => $count->id,
        'author_user_id' => $this->user->id,
        'body' => 'Initial Stock',
        'visibility' => 'internal',
        'is_pinned' => false,
    ]);
});

test('10. initial stock inventory count is posted immediately', function (): void {
    ($this->grantPermission)($this->user, 'inventory-materials-manage');
    $uom = ($this->makeUom)($this->tenant);

    ($this->postCreate)($this->user, [
        'name' => 'Cream',
        'base_uom_id' => $uom->id,
        'starting_quantity' => '7.250000',
    ])->assertCreated();

    $count = InventoryCount::query()->where('tenant_id', $this->tenant->id)->firstOrFail();

    expect($count->posted_at)->not->toBeNull()
        ->and((int) $count->posted_by_user_id)->toBe((int) $this->user->id);
});

test('11. initial stock inventory count line stores canonical counted quantity', function (): void {
    ($this->grantPermission)($this->user, 'inventory-materials-manage');
    $uom = ($this->makeUom)($this->tenant);

    ($this->postCreate)($this->user, [
        'name' => 'Cocoa',
        'base_uom_id' => $uom->id,
        'starting_quantity' => '1.250000',
    ])->assertCreated();

    $countedQuantity = DB::table('inventory_count_lines')->value('counted_quantity');

    expect(bcadd((string) $countedQuantity, '0', 6))->toBe('1.250000');
});

test('12. initial stock stock move stores canonical scale safe quantity text', function (): void {
    ($this->grantPermission)($this->user, 'inventory-materials-manage');
    $uom = ($this->makeUom)($this->tenant);

    ($this->postCreate)($this->user, [
        'name' => 'Vanilla',
        'base_uom_id' => $uom->id,
        'starting_quantity' => '3.000000',
    ])->assertCreated();

    $item = Item::query()->where('name', 'Vanilla')->firstOrFail();
    $stockMove = DB::table('stock_moves')->where('item_id', $item->id)->first();

    expect((string) $stockMove->quantity)->toBe('3.000000');
});

test('13. initial stock stock move uses the material base uom id', function (): void {
    ($this->grantPermission)($this->user, 'inventory-materials-manage');
    $uom = ($this->makeUom)($this->tenant);

    ($this->postCreate)($this->user, [
        'name' => 'Honey',
        'base_uom_id' => $uom->id,
        'starting_quantity' => '3.000000',
    ])->assertCreated();

    $item = Item::query()->where('name', 'Honey')->firstOrFail();
    $stockMove = DB::table('stock_moves')->where('item_id', $item->id)->first();

    expect((int) $stockMove->uom_id)->toBe($uom->id);
});

test('14. initial stock stock move uses the canonical inventory count adjustment type', function (): void {
    ($this->grantPermission)($this->user, 'inventory-materials-manage');
    $uom = ($this->makeUom)($this->tenant);

    ($this->postCreate)($this->user, [
        'name' => 'Yeast',
        'base_uom_id' => $uom->id,
        'starting_quantity' => '4.000000',
    ])->assertCreated();

    $item = Item::query()->where('name', 'Yeast')->firstOrFail();
    $stockMove = DB::table('stock_moves')->where('item_id', $item->id)->first();

    expect((string) $stockMove->type)->toBe('inventory_count_adjustment');
});

test('15. inventory counts index shows the initial stock record', function (): void {
    ($this->grantPermission)($this->user, 'inventory-materials-manage');
    ($this->grantPermission)($this->user, 'inventory-adjustments-view');
    $uom = ($this->makeUom)($this->tenant);

    ($this->postCreate)($this->user, [
        'name' => 'Oats',
        'base_uom_id' => $uom->id,
        'starting_quantity' => '2.500000',
    ])->assertCreated();

    $response = $this->actingAs($this->user)->getJson(route('inventory.counts.list'))->assertOk();

    expect(collect($response->json('data'))->pluck('notes')->all())->toContain('Initial Stock');
});

test('16. available inventory reflects the submitted starting quantity', function (): void {
    ($this->grantPermission)($this->user, 'inventory-materials-manage');
    $uom = ($this->makeUom)($this->tenant);

    ($this->postCreate)($this->user, [
        'name' => 'Pepper',
        'base_uom_id' => $uom->id,
        'starting_quantity' => '9.875000',
    ])->assertCreated();

    $item = Item::query()->where('name', 'Pepper')->firstOrFail();

    expect($item->onHandQuantity())->toBe('9.875000');
});

test('17. starting quantity does not create duplicate stock moves', function (): void {
    ($this->grantPermission)($this->user, 'inventory-materials-manage');
    $uom = ($this->makeUom)($this->tenant);

    ($this->postCreate)($this->user, [
        'name' => 'Nutmeg',
        'base_uom_id' => $uom->id,
        'starting_quantity' => '2.000000',
    ])->assertCreated();

    $item = Item::query()->where('name', 'Nutmeg')->firstOrFail();

    expect(DB::table('stock_moves')->where('item_id', $item->id)->count())->toBe(1)
        ->and(DB::table('inventory_count_lines')->count())->toBe(1);
});

test('18. initial stock stays tenant scoped to the created material tenant', function (): void {
    ($this->grantPermission)($this->user, 'inventory-materials-manage');
    $uom = ($this->makeUom)($this->tenant);

    ($this->postCreate)($this->user, [
        'name' => 'Rice Flour',
        'base_uom_id' => $uom->id,
        'starting_quantity' => '6.000000',
    ])->assertCreated();

    $item = Item::query()->where('name', 'Rice Flour')->firstOrFail();
    $count = InventoryCount::query()->firstOrFail();
    $stockMove = StockMove::query()->where('item_id', $item->id)->firstOrFail();

    expect((int) $count->tenant_id)->toBe((int) $this->tenant->id)
        ->and((int) $stockMove->tenant_id)->toBe((int) $this->tenant->id);
});

test('19. negative starting quantity is rejected', function (): void {
    ($this->grantPermission)($this->user, 'inventory-materials-manage');
    $uom = ($this->makeUom)($this->tenant);

    ($this->postCreate)($this->user, [
        'name' => 'Saffron',
        'base_uom_id' => $uom->id,
        'starting_quantity' => '-1.000000',
    ])->assertUnprocessable()->assertJsonValidationErrors(['starting_quantity']);
});

test('20. starting quantity with more than six decimals is rejected', function (): void {
    ($this->grantPermission)($this->user, 'inventory-materials-manage');
    $uom = ($this->makeUom)($this->tenant);

    ($this->postCreate)($this->user, [
        'name' => 'Paprika',
        'base_uom_id' => $uom->id,
        'starting_quantity' => '1.0000001',
    ])->assertUnprocessable()->assertJsonValidationErrors(['starting_quantity']);
});
