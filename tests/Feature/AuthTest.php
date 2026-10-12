<?php

namespace Tests\Feature;

use App\Models\Product;
use App\Models\User;
use App\Services\OrderService;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuthTest extends TestCase
{
    use RefreshDatabase;

    /** Referer from a Sanctum stateful domain so the request gets a session like the SPA does. */
    private array $spa = ['Referer' => 'http://localhost', 'Accept' => 'application/json'];

    public function test_user_can_log_in_and_fetch_profile(): void
    {
        $this->withoutMiddleware(ValidateCsrfToken::class);
        $user = User::factory()->admin()->create(['email' => 'admin@test.dev']);

        $this->withHeaders($this->spa)
            ->postJson('/api/login', ['email' => 'admin@test.dev', 'password' => 'password'])
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.role', 'admin');

        $this->assertAuthenticatedAs($user, 'web');
        $this->withHeaders($this->spa)->getJson('/api/user')->assertOk()->assertJsonPath('data.email', 'admin@test.dev');
    }

    public function test_invalid_credentials_return_validation_error(): void
    {
        $this->withoutMiddleware(ValidateCsrfToken::class);
        User::factory()->create(['email' => 'staff@test.dev']);

        $this->withHeaders($this->spa)
            ->postJson('/api/login', ['email' => 'staff@test.dev', 'password' => 'wrong'])
            ->assertStatus(422)
            ->assertJsonPath('success', false)
            ->assertJsonValidationErrors('email');

        $this->assertGuest('web');
    }

    public function test_protected_endpoints_require_authentication(): void
    {
        foreach (['/api/user', '/api/dashboard', '/api/products', '/api/orders'] as $uri) {
            $this->getJson($uri)
                ->assertStatus(401)
                ->assertExactJson(['success' => false, 'message' => 'Unauthenticated.']);
        }
    }

    public function test_dashboard_returns_backend_counts(): void
    {
        $staff = User::factory()->create();
        Product::factory()->create(['stock_quantity' => 100]);
        Product::factory()->create(['stock_quantity' => 3]);
        Product::factory()->create(['stock_quantity' => 0]);
        $product = Product::factory()->create(['stock_quantity' => 50]);
        app(OrderService::class)->create($staff, ['customer_name' => 'A', 'items' => [['product_id' => $product->id, 'quantity' => 1]]]);
        app(OrderService::class)->create(User::factory()->create(), ['customer_name' => 'B', 'items' => [['product_id' => $product->id, 'quantity' => 1]]]);

        $this->actingAs(User::factory()->admin()->create())
            ->getJson('/api/dashboard')
            ->assertOk()
            ->assertExactJson(['success' => true, 'data' => [
                'total_products' => 4,
                'total_orders' => 2,
                'pending_orders' => 2,
                'low_stock_products' => 1,
                'out_of_stock_products' => 1,
            ]]);

        $this->actingAs($staff)->getJson('/api/dashboard')->assertJsonPath('data.total_orders', 1);
    }
}
