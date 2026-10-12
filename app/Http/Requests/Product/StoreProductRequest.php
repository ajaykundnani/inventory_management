<?php

namespace App\Http\Requests\Product;

use App\Enums\ProductStatus;
use App\Models\Product;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreProductRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('create', Product::class);
    }

    /**
     * SKUs are stored upper-case so uniqueness is case-insensitive on every database.
     */
    protected function prepareForValidation(): void
    {
        if (is_string($this->sku)) {
            $this->merge(['sku' => strtoupper(trim($this->sku))]);
        }
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'sku' => ['required', 'string', 'max:64', 'regex:/^[A-Z0-9][A-Z0-9\-_.]*$/', Rule::unique('products', 'sku')],
            'description' => ['nullable', 'string', 'max:2000'],
            'price' => ['required', 'numeric', 'decimal:0,2', 'min:0', 'max:99999999.99'],
            'stock_quantity' => ['required', 'integer', 'min:0', 'max:1000000'],
            'low_stock_threshold' => ['sometimes', 'integer', 'min:0', 'max:1000000'],
            'status' => ['sometimes', Rule::enum(ProductStatus::class)],
        ];
    }

    public function messages(): array
    {
        return [
            'sku.regex' => 'The SKU may only contain letters, numbers, dashes, underscores and dots.',
        ];
    }
}
