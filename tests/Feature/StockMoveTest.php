<?php

namespace Tests\Feature;

use App\Models\Item;
use App\Models\StockMove;
use App\Models\Tenant;
use App\Models\Uom;
use App\Models\UomCategory;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use InvalidArgumentException;
use Tests\TestCase;

class StockMoveTest extends TestCase
{
    use RefreshDatabase;

    public function test_receipt_increases_on_hand_quantity(): void
    {
        $tenant = Tenant::factory()->create();
        $this->actingAs(User::factory()->create(['tenant_id' => $tenant->id]));

        $category = UomCategory::create(['name' => 'Mass']);
        $grams = Uom::create([
            'uom_category_id' => $category->id,
            'name' => 'Gram',
            'symbol' => 'g',
        ]);

        $item = Item::create([
            'tenant_id' => $tenant->id,
            'name' => 'Flour',
            'base_uom_id' => $grams->id,
        ]);

        StockMove::create([
            'tenant_id' => $tenant->id,
            'item_id' => $item->id,
            'uom_id' => $grams->id,
            'quantity' => '10.000000',
            'type' => 'receipt',
        ]);

        $this->assertSame('10.000000', $item->onHandQuantity());
    }

    public function test_adjustment_changes_on_hand_quantity(): void
    {
        $tenant = Tenant::factory()->create();
        $this->actingAs(User::factory()->create(['tenant_id' => $tenant->id]));

        $category = UomCategory::create(['name' => 'Mass']);
        $grams = Uom::create([
            'uom_category_id' => $category->id,
            'name' => 'Gram',
            'symbol' => 'g',
        ]);

        $item = Item::create([
            'tenant_id' => $tenant->id,
            'name' => 'Salt',
            'base_uom_id' => $grams->id,
        ]);

        StockMove::create([
            'tenant_id' => $tenant->id,
            'item_id' => $item->id,
            'uom_id' => $grams->id,
            'quantity' => '10.000000',
            'type' => 'receipt',
        ]);

        StockMove::create([
            'tenant_id' => $tenant->id,
            'item_id' => $item->id,
            'uom_id' => $grams->id,
            'quantity' => '-2.500000',
            'type' => 'adjustment',
        ]);

        $this->assertSame('7.500000', $item->onHandQuantity());
    }

    public function test_on_hand_quantity_equals_sum_of_stock_moves(): void
    {
        $tenant = Tenant::factory()->create();
        $this->actingAs(User::factory()->create(['tenant_id' => $tenant->id]));

        $category = UomCategory::create(['name' => 'Mass']);
        $grams = Uom::create([
            'uom_category_id' => $category->id,
            'name' => 'Gram',
            'symbol' => 'g',
        ]);

        $item = Item::create([
            'tenant_id' => $tenant->id,
            'name' => 'Sugar',
            'base_uom_id' => $grams->id,
        ]);

        StockMove::create([
            'tenant_id' => $tenant->id,
            'item_id' => $item->id,
            'uom_id' => $grams->id,
            'quantity' => '5.000000',
            'type' => 'receipt',
        ]);

        StockMove::create([
            'tenant_id' => $tenant->id,
            'item_id' => $item->id,
            'uom_id' => $grams->id,
            'quantity' => '-1.250000',
            'type' => 'issue',
        ]);

        StockMove::create([
            'tenant_id' => $tenant->id,
            'item_id' => $item->id,
            'uom_id' => $grams->id,
            'quantity' => '0.500000',
            'type' => 'adjustment',
        ]);

        $this->assertSame('4.250000', $item->onHandQuantity());
    }

    public function test_stock_move_uom_must_match_item_base_uom(): void
    {
        $tenant = Tenant::factory()->create();
        $this->actingAs(User::factory()->create(['tenant_id' => $tenant->id]));

        $category = UomCategory::create(['name' => 'Mass']);
        $grams = Uom::create([
            'uom_category_id' => $category->id,
            'name' => 'Gram',
            'symbol' => 'g',
        ]);

        $kg = Uom::create([
            'uom_category_id' => $category->id,
            'name' => 'Kilogram',
            'symbol' => 'kg',
        ]);

        $item = Item::create([
            'tenant_id' => $tenant->id,
            'name' => 'Yeast',
            'base_uom_id' => $grams->id,
        ]);

        $this->expectException(InvalidArgumentException::class);

        StockMove::create([
            'tenant_id' => $tenant->id,
            'item_id' => $item->id,
            'uom_id' => $kg->id,
            'quantity' => '1.000000',
            'type' => 'receipt',
        ]);
    }
}
