<?php

use App\Models\InventoryCount;
use App\Models\InventoryCountLine;
use App\Models\Item;
use App\Models\Permission;
use App\Models\Role;
use App\Models\StockMove;
use App\Models\Tenant;
use App\Models\Uom;
use App\Models\UomCategory;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->tenant = Tenant::factory()->create();

    $this->user = User::factory()->create([
        'tenant_id' => $this->tenant->id,
    ]);

    $this->uomCategory = UomCategory::query()->forceCreate([
        'name' => 'Mass '.$this->tenant->id,
    ]);

    $this->uom = Uom::query()->forceCreate([
        'uom_category_id' => $this->uomCategory->id,
        'name' => 'Gram '.$this->tenant->id,
        'symbol' => 'g'.$this->tenant->id,
    ]);

    $this->item = Item::query()->forceCreate([
        'tenant_id' => $this->tenant->id,
        'name' => 'Flour',
        'base_uom_id' => $this->uom->id,
        'is_purchasable' => false,
        'is_sellable' => false,
        'is_manufacturable' => false,
    ]);

    $this->grantPermission = function (User $user, string $slug): void {
        $permission = Permission::query()->firstOrCreate([
            'slug' => $slug,
        ]);

        $role = Role::query()->forceCreate([
            'name' => $slug.'-'.$user->id,
        ]);

        $role->permissions()->syncWithoutDetaching([$permission->id]);
        $user->roles()->syncWithoutDetaching([$role->id]);
    };

    $this->makeCount = function (array $overrides = []): InventoryCount {
        return InventoryCount::query()->forceCreate(array_merge([
            'tenant_id' => $this->tenant->id,
            'counted_at' => now(),
            'notes' => null,
        ], $overrides));
    };

    $this->makeLine = function (InventoryCount $count, Item $item, array $overrides = []): InventoryCountLine {
        return InventoryCountLine::query()->forceCreate(array_merge([
            'tenant_id' => $this->tenant->id,
            'inventory_count_id' => $count->id,
            'item_id' => $item->id,
            'counted_quantity' => '5.000000',
            'notes' => null,
        ], $overrides));
    };
});

test('guests cannot access inventory count routes', function () {
    $count = ($this->makeCount)();
    $line = ($this->makeLine)($count, $this->item);

    $this->get(route('inventory.counts.index'))
        ->assertRedirect(route('login'));

    $this->get(route('inventory.counts.show', $count))
        ->assertRedirect(route('login'));

    $this->post(route('inventory.counts.store'), [
        'counted_at' => now()->format('Y-m-d H:i'),
    ])->assertRedirect(route('login'));

    $this->patch(route('inventory.counts.update', $count), [
        'counted_at' => now()->format('Y-m-d H:i'),
    ])->assertRedirect(route('login'));

    $this->delete(route('inventory.counts.destroy', $count))
        ->assertRedirect(route('login'));

    $this->post(route('inventory.counts.post', $count))
        ->assertRedirect(route('login'));

    $this->post(route('inventory.counts.lines.store', $count), [
        'item_id' => $this->item->id,
        'counted_quantity' => '5.000000',
    ])->assertRedirect(route('login'));

    $this->patch(route('inventory.counts.lines.update', [$count, $line]), [
        'item_id' => $this->item->id,
        'counted_quantity' => '6.000000',
    ])->assertRedirect(route('login'));

    $this->delete(route('inventory.counts.lines.destroy', [$count, $line]))
        ->assertRedirect(route('login'));
});

test('users without view permission cannot see counts', function () {
    $count = ($this->makeCount)();

    $this->actingAs($this->user)
        ->get(route('inventory.counts.index'))
        ->assertForbidden();

    $this->actingAs($this->user)
        ->get(route('inventory.counts.show', $count))
        ->assertForbidden();
});

test('users with execute permission can create a draft and manage lines', function () {
    ($this->grantPermission)($this->user, 'inventory-adjustments-execute');

    $response = $this->actingAs($this->user)
        ->postJson(route('inventory.counts.store'), [
            'counted_at' => now()->format('Y-m-d H:i'),
            'notes' => 'Initial count.',
        ]);

    $response->assertCreated();
    $countId = $response->json('count.id');

    expect($countId)->not()->toBeNull();

    $lineResponse = $this->actingAs($this->user)
        ->postJson(route('inventory.counts.lines.store', $countId), [
            'item_id' => $this->item->id,
            'counted_quantity' => '5.000000',
            'notes' => 'First line.',
        ]);

    $lineResponse->assertCreated();
    $lineId = $lineResponse->json('line.id');

    $this->actingAs($this->user)
        ->patchJson(route('inventory.counts.lines.update', [$countId, $lineId]), [
            'item_id' => $this->item->id,
            'counted_quantity' => '6.000000',
            'notes' => 'Updated line.',
        ])->assertOk();

    $this->actingAs($this->user)
        ->deleteJson(route('inventory.counts.lines.destroy', [$countId, $lineId]))
        ->assertOk();
});

test('posting succeeds once and locks the count', function () {
    ($this->grantPermission)($this->user, 'inventory-adjustments-execute');

    $count = ($this->makeCount)();
    ($this->makeLine)($count, $this->item, [
        'counted_quantity' => '8.000000',
    ]);

    $this->actingAs($this->user)
        ->postJson(route('inventory.counts.post', $count))
        ->assertOk();

    $count->refresh();

    expect($count->posted_at)->not()->toBeNull();

    expect(StockMove::query()
        ->where('source_type', InventoryCount::class)
        ->where('source_id', $count->id)
        ->where('type', 'inventory_count_adjustment')
        ->exists())->toBeTrue();

    $this->actingAs($this->user)
        ->postJson(route('inventory.counts.post', $count))
        ->assertStatus(422);

    $this->actingAs($this->user)
        ->patchJson(route('inventory.counts.update', $count), [
            'counted_at' => now()->format('Y-m-d H:i'),
        ])->assertStatus(422);

    $this->actingAs($this->user)
        ->deleteJson(route('inventory.counts.destroy', $count))
        ->assertStatus(422);

    $this->actingAs($this->user)
        ->postJson(route('inventory.counts.lines.store', $count), [
            'item_id' => $this->item->id,
            'counted_quantity' => '2.000000',
        ])->assertStatus(422);
});

test('cross tenant isolation is enforced', function () {
    ($this->grantPermission)($this->user, 'inventory-adjustments-view');

    $ownCount = ($this->makeCount)([
        'counted_at' => now()->subDay(),
    ]);

    $otherTenant = Tenant::factory()->create();

    $otherCount = InventoryCount::query()->forceCreate([
        'tenant_id' => $otherTenant->id,
        'counted_at' => now()->addDay(),
        'notes' => null,
    ]);

    $response = $this->actingAs($this->user)
        ->get(route('inventory.counts.index'));

    $response->assertOk();
    $response->assertSee($ownCount->counted_at->format('Y-m-d H:i'));
    $response->assertDontSee($otherCount->counted_at->format('Y-m-d H:i'));

    $this->actingAs($this->user)
        ->get(route('inventory.counts.show', $otherCount))
        ->assertNotFound();

    $otherLine = InventoryCountLine::query()->forceCreate([
        'tenant_id' => $otherTenant->id,
        'inventory_count_id' => $otherCount->id,
        'item_id' => $this->item->id,
        'counted_quantity' => '1.000000',
        'notes' => null,
    ]);

    ($this->grantPermission)($this->user, 'inventory-adjustments-execute');

    $this->actingAs($this->user)
        ->patchJson(route('inventory.counts.lines.update', [$otherCount, $otherLine]), [
            'item_id' => $this->item->id,
            'counted_quantity' => '2.000000',
        ])->assertNotFound();
});
