<?php

namespace Tests\Feature;

use App\Enums\OrderStatus;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\User;
use App\Services\OrderService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use RuntimeException;
use Tests\TestCase;

class OrderTest extends TestCase
{
    use RefreshDatabase;

    private User $staff;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->staff = User::factory()->create();
        $this->admin = User::factory()->admin()->create();
    }

    private function placeOrder(User $user, array $items): Order
    {
        return app(OrderService::class)->create($user, ['customer_name' => 'Test Customer', 'items' => $items]);
    }

    public function test_staff_creates_order_with_server_calculated_total(): void
    {
        $a = Product::factory()->create(['price' => '10.10', 'stock_quantity' => 10]);
        $b = Product::factory()->create(['price' => '0.20', 'stock_quantity' => 5]);

        $response = $this->actingAs($this->staff)->postJson('/api/orders', [
            'customer_name' => 'Jane Doe',
            'total' => '0.01',
            'items' => [
                ['product_id' => $a->id, 'quantity' => 3, 'unit_price' => '0.01'],
                ['product_id' => $b->id, 'quantity' => 2],
            ],
        ]);

        $response->assertCreated()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.status', 'pending')
            ->assertJsonPath('data.total', '30.70')
            ->assertJsonPath('data.items.0.unit_price', '10.10')
            ->assertJsonPath('data.items.0.line_total', '30.30')
            ->assertJsonPath('data.allowed_transitions', ['processing', 'cancelled']);

        $this->assertSame(7, $a->fresh()->stock_quantity);
        $this->assertSame(3, $b->fresh()->stock_quantity);
        $this->assertDatabaseHas('orders', ['user_id' => $this->staff->id, 'total' => '30.70']);
    }

    public function test_order_exactly_matching_stock_succeeds(): void
    {
        $product = Product::factory()->create(['stock_quantity' => 4]);

        $this->actingAs($this->staff)->postJson('/api/orders', [
            'customer_name' => 'Jane',
            'items' => [['product_id' => $product->id, 'quantity' => 4]],
        ])->assertCreated();

        $this->assertSame(0, $product->fresh()->stock_quantity);
    }

    public function test_insufficient_stock_rejects_whole_order(): void
    {
        $ok = Product::factory()->create(['stock_quantity' => 10]);
        $low = Product::factory()->create(['stock_quantity' => 2]);

        $this->actingAs($this->staff)->postJson('/api/orders', [
            'customer_name' => 'Jane',
            'items' => [
                ['product_id' => $ok->id, 'quantity' => 1],
                ['product_id' => $low->id, 'quantity' => 3],
            ],
        ])->assertStatus(422)
            ->assertJsonPath('success', false)
            ->assertJsonPath('message', 'Insufficient stock for one or more items.')
            ->assertJsonValidationErrors(['items.1.quantity']);

        $this->assertSame(10, $ok->fresh()->stock_quantity);
        $this->assertSame(2, $low->fresh()->stock_quantity);
        $this->assertDatabaseCount('orders', 0);
        $this->assertDatabaseCount('order_items', 0);
    }

    public function test_nonexistent_inactive_and_duplicate_products_are_rejected(): void
    {
        $inactive = Product::factory()->inactive()->create();
        $active = Product::factory()->create();
        $this->actingAs($this->staff);

        $this->postJson('/api/orders', [
            'customer_name' => 'Jane',
            'items' => [['product_id' => 999999, 'quantity' => 1]],
        ])->assertStatus(422)->assertJsonValidationErrors(['items.0.product_id']);

        $this->postJson('/api/orders', [
            'customer_name' => 'Jane',
            'items' => [['product_id' => $inactive->id, 'quantity' => 1]],
        ])->assertStatus(422)->assertJsonValidationErrors(['items.0.product_id']);

        $this->postJson('/api/orders', [
            'customer_name' => 'Jane',
            'items' => [
                ['product_id' => $active->id, 'quantity' => 1],
                ['product_id' => $active->id, 'quantity' => 1],
            ],
        ])->assertStatus(422)->assertJsonValidationErrors(['items.1.product_id']);

        $this->postJson('/api/orders', ['customer_name' => 'Jane', 'items' => []])
            ->assertStatus(422)->assertJsonValidationErrors(['items']);

        $this->postJson('/api/orders', [
            'customer_name' => 'Jane',
            'items' => [['product_id' => $active->id, 'quantity' => 0]],
        ])->assertStatus(422)->assertJsonValidationErrors(['items.0.quantity']);

        $this->assertDatabaseCount('orders', 0);
    }

    public function test_failure_mid_transaction_rolls_back_order_items_and_stock(): void
    {
        $first = Product::factory()->create(['stock_quantity' => 10]);
        $second = Product::factory()->create(['stock_quantity' => 10]);

        // Fail while saving the second item, after the first item's stock was already decremented.
        $created = 0;
        OrderItem::creating(function () use (&$created) {
            if (++$created === 2) {
                throw new RuntimeException('Simulated failure');
            }
        });

        try {
            $this->placeOrder($this->staff, [
                ['product_id' => $first->id, 'quantity' => 4],
                ['product_id' => $second->id, 'quantity' => 4],
            ]);
            $this->fail('Expected the order to fail.');
        } catch (RuntimeException $e) {
            $this->assertSame('Simulated failure', $e->getMessage());
        }

        $this->assertSame(10, $first->fresh()->stock_quantity);
        $this->assertSame(10, $second->fresh()->stock_quantity);
        $this->assertDatabaseCount('orders', 0);
        $this->assertDatabaseCount('order_items', 0);
    }

    public function test_unexpected_failure_returns_generic_500_envelope(): void
    {
        config(['app.debug' => false]);
        $product = Product::factory()->create(['stock_quantity' => 5]);
        OrderItem::creating(fn () => throw new RuntimeException('secret internals'));

        $this->actingAs($this->staff)->postJson('/api/orders', [
            'customer_name' => 'Jane',
            'items' => [['product_id' => $product->id, 'quantity' => 1]],
        ])->assertStatus(500)
            ->assertExactJson(['success' => false, 'message' => 'An unexpected error occurred.']);

        $this->assertSame(5, $product->fresh()->stock_quantity);
    }

    public function test_staff_cannot_update_order_status(): void
    {
        $order = $this->placeOrder($this->staff, [['product_id' => Product::factory()->create()->id, 'quantity' => 1]]);

        $this->actingAs($this->staff)
            ->patchJson("/api/orders/{$order->id}/status", ['status' => 'processing'])
            ->assertForbidden();

        $this->assertSame(OrderStatus::Pending, $order->fresh()->status);
    }

    public function test_admin_moves_order_through_valid_transitions_only(): void
    {
        $order = $this->placeOrder($this->staff, [['product_id' => Product::factory()->create()->id, 'quantity' => 1]]);
        $this->actingAs($this->admin);

        $this->patchJson("/api/orders/{$order->id}/status", ['status' => 'completed'])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['status']);

        $this->patchJson("/api/orders/{$order->id}/status", ['status' => 'processing'])
            ->assertOk()
            ->assertJsonPath('data.status', 'processing');

        $this->patchJson("/api/orders/{$order->id}/status", ['status' => 'completed'])
            ->assertOk()
            ->assertJsonPath('data.status', 'completed')
            ->assertJsonPath('data.allowed_transitions', []);

        $this->patchJson("/api/orders/{$order->id}/status", ['status' => 'cancelled'])->assertStatus(422);
        $this->patchJson("/api/orders/{$order->id}/status", ['status' => 'shipped'])->assertStatus(422);
    }

    public function test_cancellation_restores_stock_exactly_once(): void
    {
        $product = Product::factory()->create(['stock_quantity' => 10]);
        $order = $this->placeOrder($this->staff, [['product_id' => $product->id, 'quantity' => 6]]);
        $this->assertSame(4, $product->fresh()->stock_quantity);

        $this->actingAs($this->admin);

        $this->patchJson("/api/orders/{$order->id}/status", ['status' => 'cancelled'])
            ->assertOk()
            ->assertJsonPath('data.status', 'cancelled');
        $this->assertSame(10, $product->fresh()->stock_quantity);
        $this->assertNotNull($order->fresh()->cancelled_at);

        $this->patchJson("/api/orders/{$order->id}/status", ['status' => 'cancelled'])->assertStatus(422);
        $this->assertSame(10, $product->fresh()->stock_quantity);
    }

    public function test_staff_only_sees_own_orders_and_admin_sees_all(): void
    {
        $product = Product::factory()->create(['stock_quantity' => 100]);
        $mine = $this->placeOrder($this->staff, [['product_id' => $product->id, 'quantity' => 1]]);
        $other = $this->placeOrder(User::factory()->create(), [['product_id' => $product->id, 'quantity' => 1]]);

        $this->actingAs($this->staff);
        $this->getJson('/api/orders')->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.id', $mine->id);
        $this->getJson("/api/orders/{$mine->id}")->assertOk()->assertJsonCount(1, 'data.items');
        $this->getJson("/api/orders/{$other->id}")->assertForbidden();

        $this->actingAs($this->admin)->getJson('/api/orders')->assertJsonCount(2, 'data');
    }

    public function test_orders_can_be_filtered_by_status(): void
    {
        $product = Product::factory()->create(['stock_quantity' => 100]);
        $this->placeOrder($this->staff, [['product_id' => $product->id, 'quantity' => 1]]);
        $cancelled = $this->placeOrder($this->staff, [['product_id' => $product->id, 'quantity' => 1]]);
        app(OrderService::class)->updateStatus($cancelled, OrderStatus::Cancelled);

        $this->actingAs($this->admin);
        $this->getJson('/api/orders?status=cancelled')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $cancelled->id);
        $this->getJson('/api/orders?status=pending')->assertJsonCount(1, 'data');
        $this->getJson('/api/orders?status=unknown')->assertStatus(422);
    }
}
