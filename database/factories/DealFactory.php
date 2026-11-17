<?php

namespace Database\Factories;

use App\Models\Deal;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Deal>
 */
class DealFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $stage = fake()->randomElement(\App\Enums\DealStage::cases());
        
        return [
            'customer_id' => \App\Models\Customer::factory(),
            'title' => fake()->randomElement(['CRM Rollout', 'Annual License', 'Support Contract', 'Consulting Package', 'Hardware Upgrade']),
            'value' => fake()->randomFloat(2, 2000, 150000),
            'stage' => $stage,
            'expected_close_date' => fake()->dateTimeBetween('-1 month', '+3 months'),
            'description' => fake()->sentence(12),
            'lost_reason' => $stage === \App\Enums\DealStage::Lost
                ? fake()->randomElement(['Budget constraints', 'Chose a competitor', 'No response from client', 'Project postponed'])
                : null,
        ];
    }
}
