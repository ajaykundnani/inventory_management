<?php

namespace App\Http\Requests\Product;

use App\Enums\ProductStatus;
use App\Enums\StockStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class IndexProductRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'search' => ['nullable', 'string', 'max:100'],
            'stock_status' => ['nullable', Rule::enum(StockStatus::class)],
            'status' => ['nullable', Rule::enum(ProductStatus::class)],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
            'page' => ['nullable', 'integer', 'min:1'],
        ];
    }
}
