<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\Deal;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class CustomerApiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Sanctum::actingAs(User::factory()->create());
    }

    private function payload(array $overrides = []): array
    {
        return array_merge([
            'name' => 'Jane Doe',
            'email' => 'jane@example.com',
            'phone' => '123456',
            'company' => 'Acme',
            'industry' => 'Retail',
            'status' => 'active',
            'notes' => 'A note.',
        ], $overrides);
    }

    public function test_index_lists_customers_with_their_deal_counts(): void
    {
        $withDeals = Customer::factory()->create(['name' => 'A First']);
        Deal::factory()->count(2)->for($withDeals)->create();
        Customer::factory()->create(['name' => 'B Second']);

        $this->getJson('/api/customers')
            ->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('data.0.deals_count', 2)
            ->assertJsonPath('data.1.deals_count', 0);
    }

    public function test_search_is_case_insensitive(): void
    {
        Customer::factory()->create(['name' => 'Someone', 'company' => 'Acme Corp']);
        Customer::factory()->create(['name' => 'Other', 'company' => 'Globex']);

        $this->getJson('/api/customers?search=acme')
            ->assertOk()
            ->assertJsonCount(1, 'data');
    }

    public function test_show_returns_the_customer_with_their_deals(): void
    {
        $customer = Customer::factory()->create();
        Deal::factory()->count(2)->for($customer)->create();

        $this->getJson("/api/customers/{$customer->id}")
            ->assertOk()
            ->assertJsonPath('data.id', $customer->id)
            ->assertJsonCount(2, 'data.deals');
    }

    public function test_show_returns_404_for_a_missing_customer(): void
    {
        $this->getJson('/api/customers/9999')->assertNotFound();
    }

    public function test_store_creates_a_customer(): void
    {
        $this->postJson('/api/customers', $this->payload())
            ->assertCreated()
            ->assertJsonPath('data.email', 'jane@example.com');

        $this->assertDatabaseHas('customers', ['email' => 'jane@example.com']);
    }

    public function test_store_validates_input(): void
    {
        $this->postJson('/api/customers', $this->payload([
            'email' => 'not-an-email',
            'status' => 'banana',
            'company' => '',
        ]))->assertUnprocessable()
            ->assertJsonValidationErrors(['email', 'status', 'company']);
    }

    public function test_store_rejects_a_duplicate_email(): void
    {
        Customer::factory()->create(['email' => 'jane@example.com']);

        $this->postJson('/api/customers', $this->payload())
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['email']);
    }

    public function test_update_allows_a_customer_to_keep_their_own_email(): void
    {
        $customer = Customer::factory()->create();

        $this->putJson("/api/customers/{$customer->id}", $this->payload([
            'email' => $customer->email,
            'name' => 'Renamed',
        ]))->assertOk()->assertJsonPath('data.name', 'Renamed');
    }

    public function test_destroy_deletes_the_customer_and_their_deals(): void
    {
        $customer = Customer::factory()->create();
        Deal::factory()->count(2)->for($customer)->create();

        $this->deleteJson("/api/customers/{$customer->id}")->assertNoContent();

        $this->assertDatabaseMissing('customers', ['id' => $customer->id]);
        $this->assertDatabaseCount('deals', 0);
    }
}