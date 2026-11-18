<?php

namespace Database\Factories;

use App\Models\Customer;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Customer>
 */
class CustomerFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->name(),
            'email' => fake()->unique()->safeEmail(),
            'phone' => fake()->phoneNumber(),
            'company' => fake()->company(),
            'industry' => fake()->randomElement([
                'Technology', 'Healthcare', 'Finance', 'Retail', 'Manufacturing', 'Education',
            ]),
            'status' => fake()->randomElement(\App\Enums\CustomerStatus::cases()),
            'notes' => fake()->randomElement([
                'Decision maker is the CFO. Prefers email over calls. Very price sensitive.',
                'Existing customer for two years. Interested in expanding to more users.',
                'Contract renewal is coming up. Mentioned a competitor is offering a discount.',
                'Slow to respond. Needs a follow-up call every two weeks.',
                'Strong technical team. Wants an on-premise option, which we do not offer.',
                'Referred by another client. Budget approved for this quarter.',
            ]),
        ];
    }
}
