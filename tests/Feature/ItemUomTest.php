<?php

namespace Tests\Feature;

use App\Models\Item;
use App\Models\Tenant;
use App\Models\Uom;
use App\Models\UomCategory;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Class ItemUomTest
 *
 * Tests item base unit of measure requirements.
 */
class ItemUomTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @return void
     */
    public function test_item_requires_base_uom(): void
    {
        $tenant = Tenant::factory()->create();
        $user = User::factory()->for($tenant)->create();

        $this->actingAs($user);

        $this->expectException(QueryException::class);

        Item::create([
            'tenant_id' => $tenant->id,
            'name' => 'Test Item',
        ]);
    }

    /**
     * @return void
     */
    public function test_item_can_be_created_with_base_uom(): void
    {
        $tenant = Tenant::factory()->create();
        $user = User::factory()->for($tenant)->create();

        $this->actingAs($user);

        $category = UomCategory::create([
            'name' => 'Mass',
        ]);

        $uom = Uom::create([
            'uom_category_id' => $category->id,
            'name' => 'Gram',
            'symbol' => 'g',
        ]);

        $item = Item::create([
            'tenant_id' => $tenant->id,
            'name' => 'Flour',
            'base_uom_id' => $uom->id,
        ]);

        $this->assertDatabaseHas('items', [
            'id' => $item->id,
            'tenant_id' => $tenant->id,
            'base_uom_id' => $uom->id,
        ]);
    }
}
