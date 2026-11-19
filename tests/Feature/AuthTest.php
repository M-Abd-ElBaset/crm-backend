<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class AuthTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_login_and_receives_a_token(): void
    {
        User::factory()->create(['email' => 'a@example.com', 'password' => 'secret123']);

        $this->postJson('/api/login', ['email' => 'a@example.com', 'password' => 'secret123'])
            ->assertOk()
            ->assertJsonStructure(['token', 'user' => ['id', 'name', 'email']]);
    }

    public function test_wrong_password_and_unknown_email_give_the_same_error(): void
    {
        User::factory()->create(['email' => 'a@example.com', 'password' => 'secret123']);

        $wrongPassword = $this->postJson('/api/login', ['email' => 'a@example.com', 'password' => 'nope']);
        $unknownEmail = $this->postJson('/api/login', ['email' => 'x@example.com', 'password' => 'nope']);

        $wrongPassword->assertUnauthorized();
        $unknownEmail->assertUnauthorized();
        $this->assertSame($wrongPassword->json('message'), $unknownEmail->json('message'));
    }

    public function test_login_requires_email_and_password(): void
    {
        $this->postJson('/api/login', [])->assertUnprocessable()
            ->assertJsonValidationErrors(['email', 'password']);
    }

    #[DataProvider('protectedEndpoints')]
    public function test_protected_endpoints_reject_unauthenticated_requests(string $method, string $uri): void
    {
        $this->json($method, $uri)->assertUnauthorized();
    }

    public static function protectedEndpoints(): array
    {
        return [
            ['GET', '/api/me'],
            ['GET', '/api/customers'],
            ['GET', '/api/customers/1'],
            ['POST', '/api/customers'],
            ['GET', '/api/deals'],
            ['GET', '/api/deals/1'],
            ['POST', '/api/deals'],
            ['POST', '/api/customers/1/summary'],
            ['GET', '/api/reports/sales'],
            ['POST', '/api/reports/sales/summary'],
            ['POST', '/api/assistant/deals/ask'],
        ];
    }
}