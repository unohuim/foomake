<?php

namespace Tests\Feature;

use App\Models\Uom;
use App\Models\UomCategory;
use App\Models\UomConversion;
use Illuminate\Foundation\Testing\RefreshDatabase;
use InvalidArgumentException;
use Tests\TestCase;

/**
 * Class UomConversionTest
 *
 * Tests UoM conversion category constraints.
 */
class UomConversionTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @return void
     */
    public function test_within_category_conversion_is_allowed(): void
    {
        $category = UomCategory::create([
            'name' => 'Mass',
        ]);

        $from = Uom::create([
            'uom_category_id' => $category->id,
            'name' => 'Kilogram',
            'symbol' => 'kg',
        ]);

        $to = Uom::create([
            'uom_category_id' => $category->id,
            'name' => 'Gram',
            'symbol' => 'g',
        ]);

        $conversion = UomConversion::create([
            'from_uom_id' => $from->id,
            'to_uom_id' => $to->id,
            'multiplier' => '1000',
        ]);

        $this->assertDatabaseHas('uom_conversions', [
            'id' => $conversion->id,
            'from_uom_id' => $from->id,
            'to_uom_id' => $to->id,
        ]);
    }

    /**
     * @return void
     */
    public function test_cross_category_conversion_is_rejected(): void
    {
        $mass = UomCategory::create([
            'name' => 'Mass',
        ]);

        $count = UomCategory::create([
            'name' => 'Count',
        ]);

        $from = Uom::create([
            'uom_category_id' => $mass->id,
            'name' => 'Gram',
            'symbol' => 'g',
        ]);

        $to = Uom::create([
            'uom_category_id' => $count->id,
            'name' => 'Each',
            'symbol' => 'ea',
        ]);

        $this->expectException(InvalidArgumentException::class);

        UomConversion::create([
            'from_uom_id' => $from->id,
            'to_uom_id' => $to->id,
            'multiplier' => '1',
        ]);
    }
}
