<?php

namespace App\Services;

use App\Enums\OrderStatus;
use App\Enums\StockStatus;
use App\Models\Order;
use App\Models\Product;
use App\Models\User;

class DashboardService
{
    /**
     * Headline counts; order figures respect the same visibility rules as the order list.
     */
    public function summary(User $user): array
    {
        $orders = Order::query()->visibleTo($user);

        return [
            'total_products' => Product::count(),
            'total_orders' => (clone $orders)->count(),
            'pending_orders' => (clone $orders)->where('status', OrderStatus::Pending)->count(),
            'low_stock_products' => Product::whereStockStatus(StockStatus::LowStock)->count(),
            'out_of_stock_products' => Product::whereStockStatus(StockStatus::OutOfStock)->count(),
        ];
    }
}
