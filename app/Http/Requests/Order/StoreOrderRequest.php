<?php

namespace App\Http\Requests\Order;

use App\Models\Order;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Only product ids and quantities are accepted; any price or total sent by the client is ignored.
 */
class StoreOrderRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('create', Order::class);
    }

    public function rules(): array
    {
        return [
            'customer_name' => ['required', 'string', 'max:255'],
            'notes' => ['nullable', 'string', 'max:1000'],
            'items' => ['required', 'array', 'min:1', 'max:50'],
            'items.*.product_id' => ['required', 'integer', 'distinct', 'exists:products,id'],
            'items.*.quantity' => ['required', 'integer', 'min:1', 'max:10000'],
        ];
    }

    public function messages(): array
    {
        return [
            'items.*.product_id.distinct' => 'Each product may only appear once per order.',
            'items.*.product_id.exists' => 'The selected product does not exist.',
        ];
    }

    /**
     * @return array{customer_name: string, notes: ?string, items: list<array{product_id: int, quantity: int}>}
     */
    public function orderData(): array
    {
        $validated = $this->validated();

        return [
            'customer_name' => $validated['customer_name'],
            'notes' => $validated['notes'] ?? null,
            'items' => array_map(fn (array $item) => [
                'product_id' => (int) $item['product_id'],
                'quantity' => (int) $item['quantity'],
            ], array_values($validated['items'])),
        ];
    }
}
