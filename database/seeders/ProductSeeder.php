<?php

namespace Database\Seeders;

use App\Models\Product;
use Illuminate\Database\Seeder;

class ProductSeeder extends Seeder
{
    /**
     * Demo catalog covering every stock status. Existing SKUs are left untouched so re-seeding never resets live stock.
     */
    public function run(): void
    {
        $catalog = [
            ['Wireless Mouse', 'WM-1001', '24.99', 120],
            ['Mechanical Keyboard', 'KB-2002', '89.50', 45],
            ['27" Monitor', 'MN-3003', '249.00', 8],
            ['USB-C Hub', 'HB-4004', '39.95', 3],
            ['Laptop Stand', 'LS-5005', '32.00', 0],
            ['Noise Cancelling Headphones', 'HP-6006', '179.99', 25],
            ['Webcam 1080p', 'WC-7007', '59.00', 60],
            ['Ethernet Cable 2m', 'EC-8008', '6.49', 500],
        ];

        foreach ($catalog as [$name, $sku, $price, $stock]) {
            Product::firstOrCreate(['sku' => $sku], [
                'name' => $name,
                'description' => "Demo product: {$name}.",
                'price' => $price,
                'stock_quantity' => $stock,
                'low_stock_threshold' => 10,
            ]);
        }
    }
}
