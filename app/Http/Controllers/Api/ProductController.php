<?php

namespace App\Http\Controllers\Api;

use App\Enums\ProductStatus;
use App\Enums\StockStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Product\IndexProductRequest;
use App\Http\Requests\Product\StoreProductRequest;
use App\Http\Resources\ProductResource;
use App\Models\Product;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;

class ProductController extends Controller
{
    public function index(IndexProductRequest $request): JsonResponse
    {
        $products = Product::query()
            ->search($request->validated('search'))
            ->when($request->validated('stock_status'), fn ($q, $status) => $q->whereStockStatus(StockStatus::from($status)))
            ->when($request->validated('status'), fn ($q, $status) => $q->where('status', $status))
            ->orderBy('name')
            ->orderBy('id')
            ->paginate($request->integer('per_page', 15))
            ->withQueryString();

        return ApiResponse::paginated($products, ProductResource::class);
    }

    public function store(StoreProductRequest $request): JsonResponse
    {
        $product = Product::create($request->validated() + [
            'status' => ProductStatus::Active,
        ]);

        return ApiResponse::success(new ProductResource($product->refresh()), 'Product created.', 201);
    }

    public function show(Product $product): JsonResponse
    {
        return ApiResponse::success(new ProductResource($product));
    }
}
