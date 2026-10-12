<?php

namespace Tests\Feature;

use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProductTest extends TestCase
{
    use RefreshDatabase;

    private function validPayload(array $overrides = []): array
    {
        return array_merge([
            'name' => 'Desk Lamp',
            'sku' => 'dl-100',
            'price' => '19.99',
            'stock_quantity' => 30,
        ], $overrides);
    }

    public function test_admin_can_create_product(): void
    {
        $this->actingAs(User::factory()->admin()->create())
            ->postJson('/api/products', $this->validPayload())
            ->assertCreated()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.sku', 'DL-100')
            ->assertJsonPath('data.price', '19.99')
            ->assertJsonPath('data.status', 'active')
            ->assertJsonPath('data.stock_status', 'in_stock');

        $this->assertDatabaseHas('products', ['sku' => 'DL-100', 'stock_quantity' => 30]);
    }

    public function test_staff_cannot_create_product(): void
    {
        $this->actingAs(User::factory()->create())
            ->postJson('/api/products', $this->validPayload())
            ->assertForbidden()
            ->assertJsonPath('success', false);

        $this->assertDatabaseCount('products', 0);
    }

    public function test_required_fields_are_validated(): void
    {
        $this->actingAs(User::factory()->admin()->create())
            ->postJson('/api/products', [])
            ->assertStatus(422)
            ->assertJsonPath('success', false)
            ->assertJsonValidationErrors(['name', 'sku', 'price', 'stock_quantity']);
    }

    public function test_negative_price_and_stock_are_rejected(): void
    {
        $this->actingAs(User::factory()->admin()->create())
            ->postJson('/api/products', $this->validPayload(['price' => -1, 'stock_quantity' => -5]))
            ->assertStatus(422)
            ->assertJsonValidationErrors(['price', 'stock_quantity']);
    }

    public function test_price_with_more_than_two_decimals_is_rejected(): void
    {
        $this->actingAs(User::factory()->admin()->create())
            ->postJson('/api/products', $this->validPayload(['price' => '1.999']))
            ->assertStatus(422)
            ->assertJsonValidationErrors(['price']);
    }

    public function test_duplicate_sku_is_rejected_case_insensitively(): void
    {
        Product::factory()->create(['sku' => 'DL-100']);

        $this->actingAs(User::factory()->admin()->create())
            ->postJson('/api/products', $this->validPayload(['sku' => 'dl-100']))
            ->assertStatus(422)
            ->assertJsonValidationErrors(['sku']);
    }

    public function test_staff_can_list_search_and_filter_products(): void
    {
        Product::factory()->create(['name' => 'Blue Pen', 'sku' => 'PEN-1', 'stock_quantity' => 50]);
        Product::factory()->create(['name' => 'Red Pen', 'sku' => 'PEN-2', 'stock_quantity' => 5]);
        Product::factory()->create(['name' => 'Stapler', 'sku' => 'STP-1', 'stock_quantity' => 0]);

        $this->actingAs(User::factory()->create());

        $this->getJson('/api/products?search=pen')
            ->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('meta.total', 2);

        $this->getJson('/api/products?search=stp')->assertJsonCount(1, 'data');

        $this->getJson('/api/products?stock_status=low_stock')
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.sku', 'PEN-2');

        $this->getJson('/api/products?stock_status=out_of_stock')
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.sku', 'STP-1');

        $this->getJson('/api/products?stock_status=in_stock')
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.sku', 'PEN-1');

        $this->getJson('/api/products?stock_status=bogus')->assertStatus(422);
    }

    public function test_product_details_and_not_found(): void
    {
        $product = Product::factory()->create();
        $this->actingAs(User::factory()->create());

        $this->getJson("/api/products/{$product->id}")
            ->assertOk()
            ->assertJsonPath('data.id', $product->id);

        $this->getJson('/api/products/999999')
            ->assertNotFound()
            ->assertExactJson(['success' => false, 'message' => 'Resource not found.']);
    }
}
