<?php

namespace Database\Seeders;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Seeder;

/**
 * Class DatabaseSeeder
 */
class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $this->call(SystemUomDefaultsSeeder::class);

        $this->call(TenancyRolesPermissionsSeeder::class);
        // Model::withoutEvents(function (): void {
        //     $this->call(TenancyRolesPermissionsSeeder::class);
        // });

        $this->call(AdminLoginUserSeeder::class);
    }
}
