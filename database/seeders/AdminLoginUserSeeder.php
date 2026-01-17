<?php

namespace Database\Seeders;

use App\Models\Tenant;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class AdminLoginUserSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $tenant = Tenant::firstOrCreate([
            'tenant_name' => 'FooMake',
        ]);

        User::updateOrCreate(
            ['email' => 'colquhoun.r@gmail.com'],
            [
                'name' => 'admin',
                'password' => Hash::make('password'),
                'tenant_id' => $tenant->id,
            ]
        );
    }
}
