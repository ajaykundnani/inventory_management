<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Order\IndexOrderRequest;
use App\Http\Requests\Order\StoreOrderRequest;
use App\Http\Requests\Order\UpdateOrderStatusRequest;
use App\Http\Resources\OrderResource;
use App\Models\Order;
use App\Services\OrderService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Gate;

class OrderController extends Controller
{
    public function __construct(private readonly OrderService $orders) {}

    public function index(IndexOrderRequest $request): JsonResponse
    {
        $search = trim((string) $request->validated('search'));

        $orders = Order::query()
            ->visibleTo($request->user())
            ->with('user')
            ->withCount('items')
            ->when($request->validated('status'), fn ($q, $status) => $q->where('status', $status))
            ->when($search !== '', fn ($q) => $q->where(fn ($q) => $q
                ->where('order_number', 'like', '%'.addcslashes($search, '%_\\').'%')
                ->orWhere('customer_name', 'like', '%'.addcslashes($search, '%_\\').'%')))
            ->latest()
            ->orderByDesc('id')
            ->paginate($request->integer('per_page', 15))
            ->withQueryString();

        return ApiResponse::paginated($orders, OrderResource::class);
    }

    public function store(StoreOrderRequest $request): JsonResponse
    {
        $order = $this->orders->create($request->user(), $request->orderData());

        return ApiResponse::success(new OrderResource($order), 'Order created.', 201);
    }

    public function show(Order $order): JsonResponse
    {
        Gate::authorize('view', $order);

        return ApiResponse::success(new OrderResource($order->load(['items', 'user'])));
    }

    public function updateStatus(UpdateOrderStatusRequest $request, Order $order): JsonResponse
    {
        $order = $this->orders->updateStatus($order, $request->status());

        return ApiResponse::success(new OrderResource($order), 'Order status updated.');
    }
}
