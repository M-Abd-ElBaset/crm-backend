<?php

namespace Database\Seeders;

use App\Models\Customer;
use App\Models\Deal;
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
        User::factory()->create([
            'name' => 'Demo User',
            'email' => 'demo@example.com',
            'password' => 'password',
        ]);

        Customer::factory(12)->create()->each(
            fn ($customer) => Deal::factory(rand(1, 4))->for($customer)->create()
        );
    }
}
