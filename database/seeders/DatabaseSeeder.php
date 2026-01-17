<?php

namespace Database\Seeders;

use App\Models\Tenant;
use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $this->call(TenancyRolesPermissionsSeeder::class);

        $tenant = Tenant::where('tenant_name', 'FooMake')->first();

        if ($tenant) {
            User::factory()->create([
                'name' => 'Test User',
                'email' => 'test@example.com',
                'tenant_id' => $tenant->id,
            ]);
        }
    }
}
