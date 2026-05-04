<?php

namespace Database\Factories;

use App\Models\Customer;
use App\Models\SalesOrder;
use App\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\SalesOrder>
 */
class SalesOrderFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var class-string<SalesOrder>
     */
    protected $model = SalesOrder::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'tenant_id' => Tenant::factory(),
            'customer_id' => function (array $attributes): int {
                return Customer::factory()->create([
                    'tenant_id' => $attributes['tenant_id'],
                ])->id;
            },
            'contact_id' => null,
            'status' => SalesOrder::STATUS_DRAFT,
        ];
    }
}
