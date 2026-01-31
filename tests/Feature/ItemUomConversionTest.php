<?php

namespace Tests\Feature;

use App\Models\Item;
use App\Models\ItemUomConversion;
use App\Models\Tenant;
use App\Models\Uom;
use App\Models\UomCategory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use InvalidArgumentException;
use Tests\TestCase;

class ItemUomConversionTest extends TestCase
{
    use RefreshDatabase;

    public function test_cross_category_conversion_is_allowed_when_item_specific(): void
    {
        $tenant = Tenant::factory()->create();

        $mass = UomCategory::firstOrCreate(['tenant_id' => $tenant->id, 'name' => 'Mass']);
        $count = UomCategory::firstOrCreate(['tenant_id' => $tenant->id, 'name' => 'Count']);

        $grams = Uom::firstOrCreate(
            [
                'tenant_id' => $tenant->id,
                'symbol' => 'g',
            ],
            [
                'uom_category_id' => $mass->id,
                'name' => 'Gram',
            ]
        );

        $each = Uom::firstOrCreate(
            [
                'tenant_id' => $tenant->id,
                'symbol' => 'ea',
            ],
            [
                'uom_category_id' => $count->id,
                'name' => 'Each',
            ]
        );

        $item = Item::create([
            'tenant_id' => $tenant->id,
            'name' => 'Beef Patty',
            'base_uom_id' => $grams->id,
        ]);

        $conversion = ItemUomConversion::create([
            'tenant_id' => $tenant->id,
            'item_id' => $item->id,
            'from_uom_id' => $each->id,
            'to_uom_id' => $grams->id,
            'conversion_factor' => '113',
        ]);

        $this->assertDatabaseHas('item_uom_conversions', [
            'id' => $conversion->id,
            'item_id' => $item->id,
        ]);
    }

    public function test_cross_category_conversion_is_not_shared_between_items(): void
    {
        $tenant = Tenant::factory()->create();

        $mass = UomCategory::firstOrCreate(['tenant_id' => $tenant->id, 'name' => 'Mass']);
        $count = UomCategory::firstOrCreate(['tenant_id' => $tenant->id, 'name' => 'Count']);

        $grams = Uom::firstOrCreate(
            [
                'tenant_id' => $tenant->id,
                'symbol' => 'g',
            ],
            [
                'uom_category_id' => $mass->id,
                'name' => 'Gram',
            ]
        );

        $each = Uom::firstOrCreate(
            [
                'tenant_id' => $tenant->id,
                'symbol' => 'ea',
            ],
            [
                'uom_category_id' => $count->id,
                'name' => 'Each',
            ]
        );

        $itemA = Item::create([
            'tenant_id' => $tenant->id,
            'name' => 'Beef Patty',
            'base_uom_id' => $grams->id,
        ]);

        $itemB = Item::create([
            'tenant_id' => $tenant->id,
            'name' => 'Chicken Patty',
            'base_uom_id' => $grams->id,
        ]);

        ItemUomConversion::create([
            'tenant_id' => $tenant->id,
            'item_id' => $itemA->id,
            'from_uom_id' => $each->id,
            'to_uom_id' => $grams->id,
            'conversion_factor' => '113',
        ]);

        $this->assertNull(
            $itemB->itemUomConversions()
                ->where('from_uom_id', $each->id)
                ->where('to_uom_id', $grams->id)
                ->first()
        );
    }

    public function test_conversion_factor_must_be_positive(): void
    {
        $tenant = Tenant::factory()->create();

        $category = UomCategory::firstOrCreate(['tenant_id' => $tenant->id, 'name' => 'Mass']);

        $kg = Uom::firstOrCreate(
            [
                'tenant_id' => $tenant->id,
                'symbol' => 'kg',
            ],
            [
                'uom_category_id' => $category->id,
                'name' => 'Kilogram',
            ]
        );

        $item = Item::create([
            'tenant_id' => $tenant->id,
            'name' => 'Salt',
            'base_uom_id' => $kg->id,
        ]);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Conversion factor must be greater than zero.');

        ItemUomConversion::create([
            'tenant_id' => $tenant->id,
            'item_id' => $item->id,
            'from_uom_id' => $kg->id,
            'to_uom_id' => $kg->id,
            'conversion_factor' => '0',
        ]);
    }
}
