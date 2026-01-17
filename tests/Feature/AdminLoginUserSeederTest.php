<?php

use App\Models\User;
use Database\Seeders\AdminLoginUserSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;

uses(RefreshDatabase::class);

test('admin login user seeder is idempotent and hashes password', function () {
    $this->seed(AdminLoginUserSeeder::class);
    $this->seed(AdminLoginUserSeeder::class);

    $users = User::where('email', 'colquhoun.r@gmail.com')->get();

    expect($users)->toHaveCount(1);

    $user = $users->first();

    expect(Hash::check('password', $user->password))->toBeTrue();
    expect($user->name)->toBe('admin');
});
