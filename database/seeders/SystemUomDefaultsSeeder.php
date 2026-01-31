<?php

namespace Database\Seeders;

use App\Services\Uom\SystemUomCloner;
use Illuminate\Database\Seeder;

/**
 * Class SystemUomDefaultsSeeder
 */
class SystemUomDefaultsSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        app(SystemUomCloner::class)->seedSystemDefaults();
    }
}
