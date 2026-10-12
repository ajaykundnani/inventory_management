<?php

namespace Database\Seeders;

use App\Models\Order;
use App\Models\Product;
use App\Models\User;
use App\Services\OrderService;
use Illuminate\Database\Seeder;

class OrderSeeder extends Seeder
{
    /**
     * Orders and their items go through OrderService so totals and stock
     * decrements follow the same rules as real orders. Requires UserSeeder and ProductSeeder.
     */
    public function run(OrderService $orders): void
    {
        if (Order::exists()) {
            return;
        }

        $admin = User::where('email', 'admin@example.com')->firstOrFail();
        $staff = User::where('email', 'staff@example.com')->firstOrFail();
        $ids = Product::whereIn('sku', ['WM-1001', 'KB-2002', 'EC-8008'])->pluck('id', 'sku');

        $orders->create($staff, [
            'customer_name' => 'Acme Corp',
            'items' => [
                ['product_id' => $ids['WM-1001'], 'quantity' => 2],
                ['product_id' => $ids['EC-8008'], 'quantity' => 5],
            ],
        ]);

        $orders->create($admin, [
            'customer_name' => 'Globex Ltd',
            'items' => [['product_id' => $ids['KB-2002'], 'quantity' => 1]],
        ]);
    }
}
